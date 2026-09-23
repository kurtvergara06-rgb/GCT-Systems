"""Live fuel-consumption prediction service.

Loads the saved Random Forest model plus its feature layout and route
metadata, then predicts the fuel consumed (liters) for a trip from its
operational measurements. Inference is side-effect free: it never queries
the database and never touches the schedule / analytics code.

The predicted volume is clipped to the observed training range (stored in
the model state) so a prediction always returns a plausible, explainable
value. If the model is not ready the service refuses to invent numbers.
"""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional

import joblib
import numpy as np
import pandas as pd

from ml_version_guard import safe_load_model
from .config import model_paths
from .training_data import FUEL_FEATURE_COLUMNS

logger = logging.getLogger(__name__)

_paths = model_paths()


@dataclass
class FuelReadiness:
    ml_ready: bool = False
    source: str = "not_trained"  # ml | not_trained
    reason: str = ""
    sample_count: int = 0
    model_path: Optional[Path] = None


@dataclass
class FuelPrediction:
    predicted_fuel_liters: float
    source: str = "ml"
    feature_inputs: Dict[str, float] = field(default_factory=dict)


# Module-level lazy caches.
_model = None
_metadata = None  # dict(features, target, route_metadata)
_state = None
_load_error = None
_loaded = False


def _load_artifacts() -> None:
    global _model, _metadata, _state, _load_error, _loaded
    if _loaded:
        return
    _loaded = True
    _state = {}
    _metadata = None
    _model = None
    _load_error = None
    try:
        if _paths["state"].exists():
            _state = json.loads(_paths["state"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read fuel state: %s", exc)
    try:
        if _paths["features"].exists():
            _metadata = json.loads(_paths["features"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read fuel feature layout: %s", exc)
    try:
        if _paths["model"].exists():
            _model, reason = safe_load_model(_paths["model"], "fuel_liters_rf", _state)
            if _model is None:
                _load_error = reason
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to load fuel model %s: %s", _paths["model"], exc)
        _model = None
        _load_error = str(exc)


def reset_cache() -> None:
    """Clear lazy caches (used by tests to force a reload)."""
    global _model, _metadata, _state, _load_error, _loaded
    _model = None
    _metadata = None
    _state = None
    _load_error = None
    _loaded = False


def fuel_readiness() -> FuelReadiness:
    _load_artifacts()
    sample_count = int(_state.get("sample_count", 0) or 0)

    if _model is None or _metadata is None:
        return FuelReadiness(
            ml_ready=False,
            source="not_trained",
            reason=_load_error or "Fuel model is not trained or could not be loaded.",
            sample_count=sample_count,
            model_path=_paths["model"],
        )
    return FuelReadiness(
        ml_ready=True,
        source="ml",
        reason="Fuel Random Forest model is ready.",
        sample_count=sample_count,
        model_path=_paths["model"],
    )


def _route_lookup() -> Dict[str, Dict[str, float]]:
    if _metadata is None:
        return {}
    return _metadata.get("route_metadata", {}) or {}


def _normalize_route(route: str) -> str:
    return " ".join(str(route).strip().lower().split())


def _resolve_distance(route_meta: Optional[Dict[str, float]], distance_km: Optional[float]) -> float:
    """Resolve the trip distance; falls back to the route's training mean."""
    if distance_km is not None:
        return float(distance_km)
    if route_meta and route_meta.get("distance_km_mean") is not None:
        return float(route_meta["distance_km_mean"])
    return -1.0


def _encode_features(
    route: str,
    trip_started_at: Optional[datetime],
    bus_no: Optional[str],
    distance_km: Optional[float],
    trip_duration_minutes: Optional[float],
    in_motion_minutes: Optional[float],
    idling_minutes: Optional[float],
    engine_on_hours: Optional[float],
) -> Dict[str, float]:
    """Encode operational inputs into the exact training feature order."""
    _load_artifacts()
    route_lookup = _route_lookup()

    # Route label-encoding rebuilt identically to training time (sorted keys,
    # same ordering as the trainer's route_map).
    normalized_lookup = {_normalize_route(name): meta for name, meta in route_lookup.items()}
    route_map = {
        name: idx for idx, name in enumerate(sorted(normalized_lookup.keys()))
    }
    route_encoded = float(route_map.get(_normalize_route(route), -1.0))
    route_meta = normalized_lookup.get(_normalize_route(route))

    resolved_distance = _resolve_distance(route_meta, distance_km)

    # Measured trip quantities; missing values are encoded as -1 (unknown),
    # exactly like the training-time convention. Nothing is invented.
    duration = -1.0
    if trip_duration_minutes is not None and float(trip_duration_minutes) > 0:
        duration = float(trip_duration_minutes)

    speed = -1.0
    if resolved_distance > 0 and duration > 0:
        speed = float(resolved_distance / (duration / 60.0))

    def _num(value: Optional[float]) -> float:
        return -1.0 if value is None else float(value)

    tz_naive = trip_started_at.replace(tzinfo=None) if trip_started_at else None
    departure_hour = float(tz_naive.hour) if tz_naive else -1.0
    day_of_week = float(tz_naive.weekday()) if tz_naive else -1.0
    is_weekend = 1.0 if tz_naive is not None and tz_naive.weekday() >= 5 else 0.0
    if tz_naive is None:
        is_weekend = -1.0

    # Bus encoding uses the persisted training-time map; unseen buses map to -1.
    bus_encoded = -1.0
    known_buses = (_metadata or {}).get("bus_encodings") or {}
    if bus_no is not None:
        normalized_bus = str(bus_no).strip()
        if normalized_bus in known_buses:
            bus_encoded = float(known_buses[normalized_bus])

    return {
        "route_encoded": route_encoded,
        "bus_no_encoded": bus_encoded,
        "distance_km": resolved_distance if resolved_distance > 0 else -1.0,
        "trip_duration_minutes": duration,
        "in_motion_minutes": _num(in_motion_minutes),
        "idling_minutes": _num(idling_minutes),
        "engine_on_hours": _num(engine_on_hours),
        "average_speed_kmh": speed,
        "departure_hour": departure_hour,
        "day_of_week": day_of_week,
        "is_weekend": is_weekend,
    }


def predict_fuel_consumption(
    route: str,
    trip_started_at: Optional[datetime],
    bus_no: Optional[str] = None,
    distance_km: Optional[float] = None,
    trip_duration_minutes: Optional[float] = None,
    in_motion_minutes: Optional[float] = None,
    idling_minutes: Optional[float] = None,
    engine_on_hours: Optional[float] = None,
) -> Optional[FuelPrediction]:
    """Predict fuel consumed (liters) for a trip.

    Returns None when the model is not ready. Predictions are clipped to the
    observed training target range so the number is always plausible.
    """
    _load_artifacts()
    if _model is None or _metadata is None:
        return None

    features = _encode_features(
        route,
        trip_started_at,
        bus_no,
        distance_km,
        trip_duration_minutes,
        in_motion_minutes,
        idling_minutes,
        engine_on_hours,
    )
    features_expected = _metadata.get("features") or FUEL_FEATURE_COLUMNS
    try:
        frame = pd.DataFrame(
            [[features[name] for name in features_expected]],
            columns=features_expected,
        )
        predicted = float(_model.predict(frame)[0])
    except Exception as exc:  # noqa: BLE001
        logger.warning("Fuel prediction failed: %s", exc)
        return None

    state_range = _state.get("target_range") or {}
    lo = float(state_range.get("min", 0.0)) if state_range else 0.0
    hi = float(state_range.get("max", 60.0)) if state_range else 60.0
    predicted = max(min(predicted, hi), lo)
    predicted_rounded = round(predicted, 2)

    return FuelPrediction(
        predicted_fuel_liters=predicted_rounded,
        source="ml",
        feature_inputs={name: features[name] for name in features_expected},
    )