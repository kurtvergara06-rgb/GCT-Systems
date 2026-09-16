"""Live ETA / trip-duration prediction service.

Loads the saved Random Forest model plus its feature layout and route
metadata, then predicts trip duration from pre-trip inputs only. Inference is
side-effect free: it never queries the database and never touches the
schedule / analytics code.

The predicted duration is clipped to the observed training range (stored in
the model state) so a prediction always returns a plausible, explainable
value. If the model is not ready the service refuses to invent numbers.
"""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional

import joblib
import numpy as np
import pandas as pd

from .config import model_paths
from .training_data import ETA_FEATURE_COLUMNS, SHIFT_MAP

logger = logging.getLogger(__name__)

_paths = model_paths()


@dataclass
class EtaReadiness:
    ml_ready: bool = False
    source: str = "not_trained"  # ml | not_trained
    reason: str = ""
    sample_count: int = 0
    model_path: Optional[Path] = None


@dataclass
class EtaPrediction:
    predicted_duration_minutes: float
    estimated_arrival_at: Optional[datetime]
    departure_at: Optional[datetime]
    source: str = "ml"
    feature_inputs: Dict[str, float] = field(default_factory=dict)


# Module-level lazy caches.
_model = None
_metadata = None  # dict(features, target, route_metadata)
_state = None
_loaded = False


def _load_artifacts() -> None:
    global _model, _metadata, _state, _loaded
    if _loaded:
        return
    _loaded = True
    _state = {}
    _metadata = None
    _model = None
    try:
        if _paths["state"].exists():
            _state = json.loads(_paths["state"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read ETA state: %s", exc)
    try:
        if _paths["features"].exists():
            _metadata = json.loads(_paths["features"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read ETA feature layout: %s", exc)
    try:
        if _paths["model"].exists():
            _model = joblib.load(_paths["model"])
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to load ETA model %s: %s", _paths["model"], exc)
        _model = None


def reset_cache() -> None:
    """Clear lazy caches (used by tests to force a reload)."""
    global _model, _metadata, _state, _loaded
    _model = None
    _metadata = None
    _state = None
    _loaded = False


def eta_readiness() -> EtaReadiness:
    _load_artifacts()
    sample_count = int(_state.get("sample_count", 0) or 0)

    if _model is None or _metadata is None:
        return EtaReadiness(
            ml_ready=False,
            source="not_trained",
            reason="ETA model is not trained or could not be loaded.",
            sample_count=sample_count,
            model_path=_paths["model"],
        )
    return EtaReadiness(
        ml_ready=True,
        source="ml",
        reason="ETA Random Forest model is ready.",
        sample_count=sample_count,
        model_path=_paths["model"],
    )


def _route_lookup() -> Dict[str, Dict[str, float]]:
    if _metadata is None:
        return {}
    return _metadata.get("route_metadata", {}) or {}


def _normalize_route(route: str) -> str:
    return " ".join(str(route).strip().lower().split())


def _encode_features(
    route: str,
    departure_at: Optional[datetime],
    shift: Optional[str],
    bus_no: Optional[str],
    distance_km: Optional[float],
    route_estimated_time_minutes: Optional[float],
) -> Dict[str, float]:
    """Encode pre-trip inputs into the exact training feature order."""
    _load_artifacts()
    route_lookup = _route_lookup()

    # Resolve route metadata from training-time captures, allowing a caller
    # override for routes the model has not seen. Lookup keys are normalized
    # exactly like the route index so both stay consistent with training.
    normalized_lookup = {_normalize_route(name): meta for name, meta in route_lookup.items()}
    route_meta = normalized_lookup.get(_normalize_route(route))
    resolved_distance = distance_km
    resolved_est_time = route_estimated_time_minutes
    if route_meta:
        if resolved_distance is None:
            resolved_distance = route_meta["distance_km"]
        if resolved_est_time is None:
            resolved_est_time = route_meta["estimated_time_minutes"]

    # Route label-encoding rebuilt identically to training time (sorted keys,
    # same ordering as the trainer's route_map).
    route_map = {
        name: idx
        for idx, name in enumerate(sorted(normalized_lookup.keys()))
    }
    route_encoded = float(route_map.get(_normalize_route(route), -1.0))

    tz_naive = departure_at.replace(tzinfo=None) if departure_at else None
    departure_hour = float(tz_naive.hour) if tz_naive else -1.0
    day_of_week = float(tz_naive.weekday()) if tz_naive else -1.0
    is_weekend = 1.0 if tz_naive is not None and tz_naive.weekday() >= 5 else 0.0
    if tz_naive is None:
        is_weekend = -1.0

    shift_encoded = float(SHIFT_MAP.get((shift or "").strip().lower(), -1.0))

    # Bus encoding uses the persisted training-time map; unseen buses map to -1.
    bus_encoded = -1.0
    known_buses = (_metadata or {}).get("bus_encodings") or {}
    if bus_no is not None:
        normalized_bus = str(bus_no).strip()
        if normalized_bus in known_buses:
            bus_encoded = float(known_buses[normalized_bus])

    return {
        "route_encoded": route_encoded,
        "distance_km": float(resolved_distance if resolved_distance is not None else -1.0),
        "route_estimated_time_minutes": float(
            resolved_est_time if resolved_est_time is not None else -1.0
        ),
        "departure_hour": departure_hour,
        "day_of_week": day_of_week,
        "is_weekend": is_weekend,
        "shift_encoded": shift_encoded,
        "bus_no_encoded": bus_encoded,
    }


def predict_trip_duration(
    route: str,
    departure_at: Optional[datetime],
    shift: Optional[str] = None,
    bus_no: Optional[str] = None,
    distance_km: Optional[float] = None,
    route_estimated_time_minutes: Optional[float] = None,
) -> Optional[EtaPrediction]:
    """Predict actual trip duration (minutes) for a new trip.

    Returns None when the model is not ready. Predictions are clipped to the
    observed training target range so the number is always plausible.
    """
    _load_artifacts()
    if _model is None or _metadata is None:
        return None

    features = _encode_features(
        route,
        departure_at,
        shift,
        bus_no,
        distance_km,
        route_estimated_time_minutes,
    )
    features_expected = _metadata.get("features") or ETA_FEATURE_COLUMNS
    try:
        frame = pd.DataFrame(
            [[features[name] for name in features_expected]],
            columns=features_expected,
        )
        predicted = float(_model.predict(frame)[0])
    except Exception as exc:  # noqa: BLE001
        logger.warning("ETA prediction failed: %s", exc)
        return None

    target_range = _state.get("target_range") or {}
    lo = float(target_range.get("min", 1.0)) if target_range else 1.0
    hi = float(target_range.get("max", 240.0)) if target_range else 240.0
    predicted = max(min(predicted, hi), lo)
    predicted_rounded = round(predicted, 1)

    tz_naive = departure_at.replace(tzinfo=None) if departure_at else None
    if tz_naive is not None:
        arrival = tz_naive + timedelta(minutes=predicted_rounded)
    else:
        arrival = None

    return EtaPrediction(
        predicted_duration_minutes=predicted_rounded,
        estimated_arrival_at=arrival,
        departure_at=tz_naive,
        source="ml",
        feature_inputs={name: features[name] for name in features_expected},
    )