"""ETA / trip-duration inference with production provenance safeguards."""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any, Dict, List, Optional

import pandas as pd

from ml_runtime_policy import evaluate_model_source, normalize_data_source
from ml_version_guard import safe_load_model
from .config import data_thresholds, model_paths
from .training_data import ETA_FEATURE_COLUMNS, SHIFT_MAP

logger = logging.getLogger(__name__)

_paths = model_paths()
SUPPORTED_MODEL_NAMES = {
    "eta_duration_rf",  # Backward compatibility for the tracked v1.0 artifact.
    "eta_duration_linear_regression",
    "eta_duration_random_forest",
    "eta_duration_hist_gradient_boosting",
}
EXPECTED_FEATURE_SCHEMA_VERSION = "1.1"


@dataclass
class EtaReadiness:
    ml_ready: bool = False
    source: str = "not_trained"
    reason: str = ""
    sample_count: int = 0
    distinct_routes: int = 0
    data_source: str = "unknown"
    dataset_type: str = "UNVERIFIED ETA DATA"
    is_production_model: bool = False
    model_path: Optional[Path] = None
    model_version: str = ""
    split_strategy: str = ""
    selected_model: Dict[str, str] = field(default_factory=dict)
    metrics: Dict[str, Optional[float]] = field(default_factory=dict)
    candidate_models: List[Dict[str, Any]] = field(default_factory=list)


@dataclass
class EtaPrediction:
    predicted_duration_minutes: float
    estimated_arrival_at: Optional[datetime]
    departure_at: Optional[datetime]
    source: str = "ml"
    feature_inputs: Dict[str, float] = field(default_factory=dict)


_model = None
_metadata = None
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
        _load_error = f"Failed to read ETA state: {exc}"
        logger.warning(_load_error)

    try:
        if _paths["features"].exists():
            _metadata = json.loads(_paths["features"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        _load_error = f"Failed to read ETA feature schema: {exc}"
        logger.warning(_load_error)

    try:
        if _paths["model"].exists():
            model_name = str((_state or {}).get("model_name", ""))
            if model_name not in SUPPORTED_MODEL_NAMES:
                _load_error = "MODEL NOT READY: ETA artifact model identity is unsupported."
                return
            _model, reason = safe_load_model(_paths["model"], model_name, _state)
            if _model is None:
                _load_error = reason
    except Exception as exc:  # noqa: BLE001
        _model = None
        _load_error = f"Failed to load ETA model: {exc}"
        logger.warning(_load_error)


def reset_cache() -> None:
    global _model, _metadata, _state, _load_error, _loaded
    _model = None
    _metadata = None
    _state = None
    _load_error = None
    _loaded = False


def _dataset_type(source: str) -> str:
    if source == "genuine":
        return "GENUINE GCT GPS RECORDS"
    if source == "synthetic":
        return "SAMPLE / DEMO ETA DATA"
    return "UNVERIFIED ETA DATA"


def eta_readiness() -> EtaReadiness:
    """Return runtime readiness; production fails closed on provenance."""
    _load_artifacts()
    state = _state or {}
    metadata = _metadata or {}
    sample_count = int(state.get("sample_count", 0) or 0)
    distinct_routes = int(state.get("distinct_routes", 0) or 0)
    data_source = normalize_data_source(state.get("training_source"))
    metrics = state.get("metrics") if isinstance(state.get("metrics"), dict) else {}
    selected_model = (
        state.get("selected_model")
        if isinstance(state.get("selected_model"), dict)
        else {
            "key": "random_forest",
            "name": "Random Forest",
            "family": "Legacy nonlinear ensemble",
        }
        if state.get("model_name") == "eta_duration_rf"
        else {}
    )
    candidate_models = (
        state.get("candidate_models")
        if isinstance(state.get("candidate_models"), list)
        else []
    )
    if not candidate_models and state.get("model_name") == "eta_duration_rf" and metrics:
        candidate_models = [
            {
                "key": "random_forest",
                "name": "Random Forest",
                "family": "Legacy nonlinear ensemble",
                "selected": True,
                "status": "evaluated",
                "reason": "Legacy artifact; rerun the v1.2 trainer for full candidate benchmarking.",
                "metrics": metrics,
            }
        ]
    common = dict(
        sample_count=sample_count,
        distinct_routes=distinct_routes,
        data_source=data_source,
        dataset_type=_dataset_type(data_source),
        model_path=_paths["model"],
        model_version=str(state.get("model_version", "")),
        split_strategy=str(state.get("split_strategy", "")),
        selected_model=selected_model,
        metrics=metrics,
        candidate_models=candidate_models,
    )

    if _model is None or _metadata is None or not state:
        return EtaReadiness(
            reason=_load_error or "ETA model artifacts are missing or unreadable.",
            **common,
        )

    if state.get("model_ready") is not True:
        return EtaReadiness(reason="MODEL NOT READY: latest ETA training run did not produce a usable artifact.", **common)

    if state.get("model_name") not in SUPPORTED_MODEL_NAMES:
        return EtaReadiness(reason="MODEL NOT READY: ETA artifact model identity does not match runtime expectations.", **common)

    if state.get("feature_schema_version") != EXPECTED_FEATURE_SCHEMA_VERSION:
        return EtaReadiness(reason="MODEL NOT READY: ETA feature schema version is incompatible with this runtime.", **common)

    expected_features = metadata.get("features")
    if expected_features != ETA_FEATURE_COLUMNS:
        return EtaReadiness(reason="MODEL NOT READY: ETA feature order/schema does not match the trainer contract.", **common)

    route_encodings = metadata.get("route_encodings") or {}
    if not isinstance(route_encodings, dict) or not route_encodings:
        return EtaReadiness(reason="MODEL NOT READY: persisted ETA route encodings are missing.", **common)

    thresholds = data_thresholds()
    if sample_count < thresholds["min_records"]:
        return EtaReadiness(
            reason=(
                f"MODEL NOT READY: {sample_count} ETA records available; "
                f"minimum is {thresholds['min_records']}."
            ),
            **common,
        )
    if distinct_routes < thresholds["min_routes"]:
        return EtaReadiness(
            reason=(
                f"MODEL NOT READY: {distinct_routes} ETA routes available; "
                f"minimum is {thresholds['min_routes']}."
            ),
            **common,
        )

    source_policy = evaluate_model_source("ETA model", data_source)
    if not source_policy.allowed:
        return EtaReadiness(reason=source_policy.reason, **common)

    # A genuine production model must prove that its newest-trip holdout beats
    # the existing operator route-time baseline. Demo models may still be used
    # in development, but can never receive the production flag.
    quality_ready = state.get("quality_ready") is True
    if data_source == "genuine" and not quality_ready:
        return EtaReadiness(
            reason="MODEL NOT READY: genuine ETA artifact has not passed the production quality gate.",
            **common,
        )

    production_model = data_source == "genuine" and quality_ready
    return EtaReadiness(
        ml_ready=True,
        source="ml",
        reason=(
            f"ETA {common['selected_model'].get('name', 'selected model')} is production-ready."
            if production_model
            else "ETA development/demo model is loaded; it is not production-ready."
        ),
        is_production_model=production_model,
        **common,
    )


def _normalize_route(route: object) -> str:
    return " ".join(str(route or "").strip().lower().split())


def route_supported(route: str) -> bool:
    _load_artifacts()
    encodings = (_metadata or {}).get("route_encodings") or {}
    return _normalize_route(route) in encodings


def _encode_features(
    route: str,
    departure_at: Optional[datetime],
    shift: Optional[str],
    bus_no: Optional[str],
    distance_km: Optional[float],
    route_estimated_time_minutes: Optional[float],
) -> Dict[str, float]:
    """Encode inputs from persisted training-time maps only."""
    _load_artifacts()
    metadata = _metadata or {}

    route_lookup = metadata.get("route_metadata") or {}
    normalized_lookup = {_normalize_route(name): meta for name, meta in route_lookup.items()}
    route_key = _normalize_route(route)
    route_meta = normalized_lookup.get(route_key)

    resolved_distance = distance_km
    resolved_est_time = route_estimated_time_minutes
    if route_meta:
        if resolved_distance is None:
            resolved_distance = route_meta.get("distance_km")
        if resolved_est_time is None:
            resolved_est_time = route_meta.get("estimated_time_minutes")

    route_encodings = metadata.get("route_encodings") or {}
    route_encoded = float(route_encodings.get(route_key, -1.0))

    tz_naive = departure_at.replace(tzinfo=None) if departure_at else None
    departure_hour = float(tz_naive.hour) if tz_naive else -1.0
    day_of_week = float(tz_naive.weekday()) if tz_naive else -1.0
    is_weekend = 1.0 if tz_naive is not None and tz_naive.weekday() >= 5 else 0.0
    if tz_naive is None:
        is_weekend = -1.0

    shift_encoded = float(SHIFT_MAP.get((shift or "").strip().lower(), -1.0))

    bus_encoded = -1.0
    known_buses = metadata.get("bus_encodings") or {}
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
    readiness = eta_readiness()
    if not readiness.ml_ready or not route_supported(route):
        return None

    features = _encode_features(
        route,
        departure_at,
        shift,
        bus_no,
        distance_km,
        route_estimated_time_minutes,
    )
    features_expected = (_metadata or {}).get("features") or []

    try:
        frame = pd.DataFrame(
            [[features[name] for name in features_expected]],
            columns=features_expected,
        )
        predicted = float(_model.predict(frame)[0])
    except Exception as exc:  # noqa: BLE001
        logger.warning("ETA prediction failed: %s", exc)
        return None

    target_range = (_state or {}).get("target_range") or {}
    lo = float(target_range.get("min", 1.0))
    hi = float(target_range.get("max", 240.0))
    predicted_rounded = round(max(min(predicted, hi), lo), 1)

    departure = departure_at.replace(tzinfo=None) if departure_at else None
    arrival = departure + timedelta(minutes=predicted_rounded) if departure else None

    return EtaPrediction(
        predicted_duration_minutes=predicted_rounded,
        estimated_arrival_at=arrival,
        departure_at=departure,
        source="ml",
        feature_inputs={name: features[name] for name in features_expected},
    )
