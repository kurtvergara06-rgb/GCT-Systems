"""Live delay-prediction service (Model #3).

DEVELOPMENT PROTOTYPE - SAMPLE / DEMONSTRATION DATA.

Loads the saved Random Forest model plus its persistence metadata and predicts
the expected arrival delay (``arrival_delay_minutes``) for a scheduled trip
from pre-trip inputs only. Inference is side-effect free: it never queries the
database and never writes anything.

The response separates two conceptual layers explicitly:
    1. ML forecast       -> predicted_arrival_delay_minutes
    2. Business-rule band-> risk_status / threshold (plain thresholds, not ML)

DISCLAIMER: this is a development prototype trained on GENERATED SAMPLE data.
It is NOT trained on genuine GCT historical delay records.
"""

from __future__ import annotations

import json
import logging
from dataclasses import asdict, dataclass, field
from datetime import date, datetime, time
from pathlib import Path
from typing import Dict, List, Optional

import joblib
import numpy as np
import pandas as pd

from .config import model_paths
from .model import RISK_THRESHOLDS
from .training_data import DLY_FEATURE_COLUMNS, SEASON_MAP

logger = logging.getLogger(__name__)

_paths = model_paths()

DISCLAIMER = (
    "SAMPLE / DEMONSTRATION DATA - NOT ACTUAL GCT OPERATIONAL DATA. "
    "This Model #3 implementation is a demonstration prototype trained on a "
    "separate sample dataset. It is NOT trained on genuine GCT historical "
    "delay records and must not be presented as a production prediction."
)

# Default fallbacks for pre-trip unknowns (documented, neutral values).
_DEFAULT_PRIOR_DELAY = 0.0
_DEFAULT_PRIOR_RATE = 0.0
_DEFAULT_DRIVER_SEQ = 1


@dataclass
class DelayReadiness:
    ml_ready: bool = False
    source: str = "not_trained"  # sample | not_trained
    reason: str = ""
    sample_count: int = 0
    message: str = ""
    model_path: Optional[Path] = None
    data_source: str = "sample"


@dataclass
class DelayPrediction:
    predicted_arrival_delay_minutes: float
    risk_status: str
    threshold: Dict[str, object]
    data_source: str = "sample"
    is_production_model: bool = False
    model_version: str = ""
    source: str = "ml"
    feature_inputs: Dict[str, float] = field(default_factory=dict)
    disclaimer: str = DISCLAIMER


_model = None
_metadata = None
_state = None
_loaded = False


def _load_artifacts() -> None:
    global _model, _metadata, _state, _loaded
    if _loaded:
        return
    _loaded = True
    _model = None
    _metadata = None
    _state = {}
    try:
        if _paths["state"].exists():
            _state = json.loads(_paths["state"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read delay state: %s", exc)
    try:
        if _paths["features"].exists():
            _metadata = json.loads(_paths["features"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read delay feature layout: %s", exc)
    try:
        if _paths["model"].exists():
            _model = joblib.load(_paths["model"])
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to load delay model %s: %s", _paths["model"], exc)
        _model = None


def reset_cache() -> None:
    """Clear lazy caches (used by tests to force a reload)."""
    global _model, _metadata, _state, _loaded
    _model = None
    _metadata = None
    _state = {}
    _loaded = False


def delay_readiness() -> DelayReadiness:
    _load_artifacts()
    sample_count = int((_state or {}).get("sample_count", 0) or 0)

    if _model is None or _metadata is None:
        return DelayReadiness(
            ml_ready=False,
            source=(_state or {}).get("source", "not_trained"),
            reason="Delay model is not trained or could not be loaded.",
            sample_count=sample_count,
            message=(_state or {}).get("message", "DELAY_ML_NOT_READY"),
            model_path=_paths["model"],
            data_source="sample",
        )
    return DelayReadiness(
        ml_ready=True,
        source=(_metadata or {}).get("source", "sample"),
        reason="Delay Random Forest model is ready (SAMPLE/DEVELOPMENT prototype).",
        sample_count=sample_count,
        message=(_state or {}).get("message", "DELAY_ML_READY (SAMPLE/DEVELOPMENT)"),
        model_path=_paths["model"],
        data_source="sample",
    )


# ---------------------------------------------------------------------------
# Business-rule (non-ML) risk band layer
# ---------------------------------------------------------------------------
def classify_arrival_delay(delay_minutes: float) -> Dict[str, object]:
    """Map the numeric prediction to an On Time / Minor / Moderate / High band.

    Pure threshold arithmetic - NOT machine learning:
        0-5  minutes  = On Time
        6-10  minutes = Minor Delay
        11-20 minutes = Moderate Delay
        21+   minutes = High Delay
    """
    value = max(0.0, float(delay_minutes))
    bands = (_metadata or {}).get("risk_thresholds") or RISK_THRESHOLDS
    label = "High Delay"
    matched = None
    for band in bands:
        maximum = band.get("max_minutes")
        if maximum is None:
            continue
        if value <= float(maximum):
            label = str(band.get("label") or "On Time")
            matched = band
            break
    return {
        "label": label,
        "delay_minutes": round(value, 1),
        "threshold": [int(b.get("max_minutes")) for b in bands if b.get("max_minutes") is not None],
        "band": [b.get("label") for b in bands],
    }


# ---------------------------------------------------------------------------
# Feature encoding aligned with training
# ---------------------------------------------------------------------------
def _route_metadata() -> Dict[str, Dict[str, object]]:
    return (_metadata or {}).get("route_metadata") or {}


def _normalize_route(route: str) -> str:
    return " ".join(str(route).strip().lower().split())


def encode_features(
    route: str,
    bus_no: Optional[str],
    driver_id: Optional[str],
    trip_date: Optional[datetime],
    scheduled_departure_time: str,
    scheduled_duration_minutes: Optional[float] = None,
    route_distance_km: Optional[float] = None,
    route_prior_delay_mean_min: Optional[float] = None,
    route_prior_delay_rate: Optional[float] = None,
    driver_prior_delay_mean_min: Optional[float] = None,
    driver_trip_seq: Optional[int] = None,
) -> Dict[str, float]:
    """Build the exact training feature vector from request inputs + defaults.

    Missing operational context uses route metadata captured at training time
    or neutral documented defaults - never invented values. Categorical
    encodings are rebuilt from the persisted sorted maps (identical to
    training), and unknown categories map to -1.
    """
    _load_artifacts()
    encoders = (_metadata or {}).get("encoders") or {}
    routes = encoders.get("route_encodings") or {}
    buses = encoders.get("bus_encodings") or {}
    drivers = encoders.get("driver_encodings") or {}

    route_lookup = {
        _normalize_route(name): meta for name, meta in _route_metadata().items()
    }
    route_meta = route_lookup.get(_normalize_route(route))

    route_encoded = float(routes.get(str(route).strip(), -1.0))
    bus_encoded = float(buses.get(str(bus_no).strip(), -1.0)) if bus_no else -1.0
    driver_encoded = float(drivers.get(str(driver_id).strip(), -1.0)) if driver_id else -1.0

    sched_duration = scheduled_duration_minutes
    if sched_duration is None and route_meta:
        sched_duration = route_meta.get("duration_minutes")
    sched_duration = float(sched_duration) if sched_duration is not None else -1.0

    distance = route_distance_km
    if distance is None and route_meta:
        distance = route_meta.get("distance_km")
    distance = float(distance) if distance is not None else -1.0

    r_prior = route_prior_delay_mean_min
    if r_prior is None and route_meta:
        r_prior = route_meta.get("prior_delay_mean")
    r_prior = float(r_prior) if r_prior is not None else _DEFAULT_PRIOR_DELAY

    r_rate = route_prior_delay_rate
    if r_rate is None and route_meta:
        r_rate = route_meta.get("prior_delay_rate")
    r_rate = float(r_rate) if r_rate is not None else _DEFAULT_PRIOR_RATE

    d_prior = driver_prior_delay_mean_min
    if d_prior is None:
        d_meta = ((_metadata or {}).get("driver_metadata") or {}).get(
            str(driver_id).strip()
        )
        if d_meta:
            d_prior = d_meta.get("prior_delay_mean")
    d_prior = float(d_prior) if d_prior is not None else _DEFAULT_PRIOR_DELAY

    seq = driver_trip_seq if driver_trip_seq is not None else _DEFAULT_DRIVER_SEQ

    if trip_date is None:
        tz_naive = datetime.now()
    else:
        tz_naive = pd.Timestamp(trip_date).tz_localize(None).to_pydatetime()

    day_of_week = float(tz_naive.weekday())
    is_weekend = 1.0 if tz_naive.weekday() >= 5 else 0.0
    month = float(tz_naive.month)
    season_encoded = float(SEASON_MAP.get(_season_by_month(tz_naive.month), -1.0))

    hour, minute = _parse_departure_time(scheduled_departure_time)

    return {
        "route_encoded": route_encoded,
        "bus_encoded": bus_encoded,
        "driver_encoded": driver_encoded,
        "scheduled_departure_hour": hour,
        "scheduled_departure_minute": minute,
        "day_of_week": day_of_week,
        "is_weekend": is_weekend,
        "month": month,
        "season_encoded": season_encoded,
        "scheduled_duration_minutes": sched_duration,
        "route_distance_km": distance,
        "route_prior_delay_mean_min": r_prior,
        "route_prior_delay_rate": r_rate,
        "driver_prior_delay_mean_min": d_prior,
        "driver_trip_seq": float(seq),
    }


def _season_by_month(month: int) -> str:
    if month in {12, 1, 2, 3, 4, 5}:
        return "dry"
    if month in {6, 7, 8, 9}:
        return "wet"
    return "transition"


def _parse_departure_time(value: str) -> "tuple[float, float]":
    """Parse an HH:MM (24h) string; invalid values degrade to -1 features."""
    cleaned = str(value).strip()
    try:
        parts = cleaned.split(":")
        hour = int(parts[0])
        minute = int(parts[1]) if len(parts) > 1 else 0
        if not (0 <= hour <= 23) or not (0 <= minute <= 59):
            return -1.0, -1.0
        return float(hour), float(minute)
    except (ValueError, IndexError):
        return -1.0, -1.0


# ---------------------------------------------------------------------------
# Prediction
# ---------------------------------------------------------------------------
def predict_arrival_delay(
    route: str,
    scheduled_departure_time: str,
    bus_no: Optional[str] = None,
    driver_id: Optional[str] = None,
    trip_date: Optional[datetime] = None,
    scheduled_duration_minutes: Optional[float] = None,
    route_distance_km: Optional[float] = None,
    route_prior_delay_mean_min: Optional[float] = None,
    route_prior_delay_rate: Optional[float] = None,
    driver_prior_delay_mean_min: Optional[float] = None,
    driver_trip_seq: Optional[int] = None,
) -> Optional[DelayPrediction]:
    """Predict expected arrival delay (minutes) for a scheduled trip.

    Returns None when the model is not ready or the feature vector cannot be
    built. Predictions are clipped to [0, max_observed] and rounded to 1dp.
    """
    _load_artifacts()
    if _model is None or _metadata is None:
        return None

    features = encode_features(
        route=route,
        bus_no=bus_no,
        driver_id=driver_id,
        trip_date=trip_date,
        scheduled_departure_time=scheduled_departure_time,
        scheduled_duration_minutes=scheduled_duration_minutes,
        route_distance_km=route_distance_km,
        route_prior_delay_mean_min=route_prior_delay_mean_min,
        route_prior_delay_rate=route_prior_delay_rate,
        driver_prior_delay_mean_min=driver_prior_delay_mean_min,
        driver_trip_seq=driver_trip_seq,
    )

    features_expected = (_metadata or {}).get("features") or DLY_FEATURE_COLUMNS
    try:
        frame = pd.DataFrame(
            [[features[name] for name in features_expected]],
            columns=features_expected,
        )
        predicted = float(_model.predict(frame)[0])
    except Exception as exc:  # noqa: BLE001
        logger.warning("Delay prediction failed: %s", exc)
        return None

    state_range = (_state or {}).get("target_range") or {}
    max_seen = float(state_range.get("max", 60.0)) if state_range else 60.0
    predicted = max(0.0, min(predicted, max_seen))
    predicted = round(float(predicted), 1)

    band = classify_arrival_delay(predicted)

    return DelayPrediction(
        predicted_arrival_delay_minutes=predicted,
        risk_status=band["label"],
        threshold={
            "label": band["label"],
            "delay_minutes": band["delay_minutes"],
            "bands_minutes": band["threshold"],
            "bands_labels": band["band"],
        },
        data_source="sample",
        is_production_model=False,
        model_version=(_state or {}).get("message", "DELAY_ML_READY (SAMPLE/DEVELOPMENT)"),
        source="ml",
        feature_inputs={name: features[name] for name in features_expected},
        disclaimer=DISCLAIMER,
    )


def prediction_to_dict(prediction: DelayPrediction) -> Dict[str, object]:
    """Flatten a prediction into the API response shape."""
    data = asdict(prediction)
    features = data.pop("feature_inputs")
    del features  # raw feature row kept internal for debugging
    return {
        "success": True,
        "predicted_arrival_delay_minutes": data["predicted_arrival_delay_minutes"],
        "risk_status": data["risk_status"],
        "threshold": data["threshold"],
        "data_source": data["data_source"],
        "is_production_model": data["is_production_model"],
        "model_version": data["model_version"],
        "source": data["source"],
        "disclaimer": data["disclaimer"],
    }