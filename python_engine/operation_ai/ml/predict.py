"""Live prediction service for the Operation AI ranking.

Loads the saved Random Forest models and produces suitability scores for each
valid driver and bus candidate. This is the PRIMARY ranking mechanism used by
recommender.py and analyzer.py after hard-eligibility filtering.

The bus and driver models are treated independently. The BUS model is trained
on real GPS trip outcomes (rich data) and is typically the reliable ML signal.
The DRIVER model requires enough distinct drivers with attendance history; if
that threshold is not met it falls back to a transparent, data-derived
reliability value rather than pretending an under-trained model is reliable.

Each component reports whether it used ML, the data-derived fallback, or the
legacy rule-based fallback, so the UI can be honest about the source.
"""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional

import joblib
import numpy as np
import pandas as pd

from ..schemas import BusData, DriverData, TripData
from .config import data_thresholds, model_paths
from .features import (
    BUS_FEATURE_NAMES,
    DRIVER_FEATURE_NAMES,
    encode_bus_features,
    encode_driver_features,
    encode_trip_context,
)

logger = logging.getLogger(__name__)

_paths = model_paths()
_thresholds = data_thresholds()

# Module-level lazy caches.
_bus_model = None
_driver_model = None
_bus_loaded = False
_driver_loaded = False


@dataclass
class ComponentReadiness:
    """Readiness of one ML component (bus or driver)."""

    ml_ready: bool = False
    source: str = "rule_fallback"  # ml | data | rule_fallback
    reason: str = ""
    sample_count: int = 0
    model_path: Optional[Path] = None


@dataclass
class PredictionOutcome:
    """Result of evaluating candidates for one trip."""

    bus_readiness: ComponentReadiness = field(default_factory=ComponentReadiness)
    driver_readiness: ComponentReadiness = field(default_factory=ComponentReadiness)
    driver_scores: Dict[int, float] = field(default_factory=dict)
    bus_scores: Dict[int, float] = field(default_factory=dict)
    driver_histories: Dict[int, Dict[str, float]] = field(default_factory=dict)
    bus_histories: Dict[int, Dict[str, float]] = field(default_factory=dict)

    @property
    def any_ml(self) -> bool:
        return self.bus_readiness.ml_ready or self.driver_readiness.ml_ready


def _load_model_secret(path: Path):
    """Lazy-load a joblib model; returns None if unavailable/broken."""
    if not path.exists():
        return None
    try:
        return joblib.load(path)
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to load model %s: %s", path, exc)
        return None


def _load_bus_model():
    global _bus_model, _bus_loaded
    if not _bus_loaded:
        _bus_loaded = True
        _bus_model = _load_model_secret(_paths["bus_model"])
    return _bus_model


def _load_driver_model():
    global _driver_model, _driver_loaded
    if not _driver_loaded:
        _driver_loaded = True
        _driver_model = _load_model_secret(_paths["driver_model"])
    return _driver_model


def _load_feature_layout(path: Path, expected: List[str]) -> Optional[List[str]]:
    if not path.exists():
        return None
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        features = data.get("features")
        if not isinstance(features, list) or features != expected:
            return None
        return features
    except Exception:  # noqa: BLE001
        return None


def _component_state(prefix: str) -> Dict:
    state = {}
    if _paths["state"].exists():
        try:
            state = json.loads(_paths["state"].read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            state = {}
    return {
        "sample_count": int(state.get(f"{prefix}_sample_count", 0) or 0),
        "ready": bool(state.get(f"{prefix}_model_ready", False)),
    }


def bus_readiness() -> ComponentReadiness:
    model = _load_bus_model()
    features_ok = _load_feature_layout(_paths["bus_features"], BUS_FEATURE_NAMES) is not None
    state = _component_state("bus")

    if model is None or not features_ok:
        return ComponentReadiness(
            ml_ready=False,
            source="rule_fallback",
            reason="Bus ML model is not trained or could not be loaded.",
            sample_count=state["sample_count"],
            model_path=_paths["bus_model"],
        )
    if state["sample_count"] < _thresholds["min_bus_records"]:
        return ComponentReadiness(
            ml_ready=False,
            source="data_fallback",
            reason=(
                f"Only {state['sample_count']} bus trip records available; "
                f"need at least {_thresholds['min_bus_records']}."
            ),
            sample_count=state["sample_count"],
            model_path=_paths["bus_model"],
        )
    return ComponentReadiness(
        ml_ready=True,
        source="ml",
        reason="ML bus model is ready.",
        sample_count=state["sample_count"],
        model_path=_paths["bus_model"],
    )


def driver_readiness() -> ComponentReadiness:
    model = _load_driver_model()
    features_ok = _load_feature_layout(_paths["driver_features"], DRIVER_FEATURE_NAMES) is not None
    state = _component_state("driver")

    if model is None or not features_ok:
        return ComponentReadiness(
            ml_ready=False,
            source="data_fallback",
            reason="Driver ML model is not trained; using data-derived driver reliability.",
            sample_count=state["sample_count"],
            model_path=_paths["driver_model"],
        )
    if state["sample_count"] < _thresholds["min_driver_count"]:
        return ComponentReadiness(
            ml_ready=False,
            source="data_fallback",
            reason=(
                f"Only {state['sample_count']} distinct drivers available; "
                f"need at least {_thresholds['min_driver_count']} for a reliable "
                "driver model. Using data-derived reliability."
            ),
            sample_count=state["sample_count"],
            model_path=_paths["driver_model"],
        )
    return ComponentReadiness(
        ml_ready=True,
        source="ml",
        reason="ML driver model is ready.",
        sample_count=state["sample_count"],
        model_path=_paths["driver_model"],
    )


def predict_driver_suitability(
    driver: DriverData,
    trip: TripData,
    history: Optional[Dict[str, float]] = None,
) -> Optional[float]:
    """Return an ML suitability score in [0,1] for a driver, or None on failure."""
    model = _load_driver_model()
    if model is None:
        return None
    try:
        features = encode_driver_features(driver, trip, history)
        frame = pd.DataFrame(features.reshape(1, -1), columns=DRIVER_FEATURE_NAMES)
        pred = float(model.predict(frame)[0])
        return max(0.0, min(1.0, pred))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Driver ML prediction failed: %s", exc)
        return None


def predict_bus_suitability(
    bus: BusData,
    trip: TripData,
    history: Optional[Dict[str, float]] = None,
) -> Optional[float]:
    """Return an ML suitability score in [0,1] for a bus, or None on failure."""
    model = _load_bus_model()
    if model is None:
        return None
    try:
        features = encode_bus_features(bus, trip, history)
        frame = pd.DataFrame(features.reshape(1, -1), columns=BUS_FEATURE_NAMES)
        pred = float(model.predict(frame)[0])
        return max(0.0, min(1.0, pred))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Bus ML prediction failed: %s", exc)
        return None


def data_derived_driver_score(
    driver: DriverData,
    history: Optional[Dict[str, float]] = None,
) -> float:
    """Compute a transparent, data-derived driver reliability score in [0,1].

    Used when the driver ML model is not ready (insufficient training data).
    The value comes from the driver's actual attendance history:
      reliability = attendance_rate (share of Present/Late records),
    lightly combined with current status. This is grounded in real attendance
    data, not arbitrary penalties.
    """
    hist = history or {}
    attendance_rate = float(hist.get("attendance_rate", 0.0))

    # Current-day status modifier, grounded in the real attendance enum.
    status_bonus = 1.0 if driver.status in ("Present", "Late") else 0.0

    score = 0.7 * attendance_rate + 0.3 * status_bonus
    return max(0.0, min(1.0, score))


def evaluate_candidates(
    trip: TripData,
    eligible_drivers: List[DriverData],
    eligible_buses: List[BusData],
    driver_histories: Optional[Dict[int, Dict[str, float]]] = None,
    bus_histories: Optional[Dict[int, Dict[str, float]]] = None,
) -> PredictionOutcome:
    """Score every eligible driver + bus for a trip.

    Buses use the ML model when ready, else fall back.
    Drivers use the ML model when ready, else data-derived reliability.
    """
    bus_ready = bus_readiness()
    driver_ready = driver_readiness()

    outcome = PredictionOutcome(
        bus_readiness=bus_ready,
        driver_readiness=driver_ready,
    )
    driver_histories = driver_histories or {}
    bus_histories = bus_histories or {}

    # --- Buses ---
    if bus_ready.ml_ready:
        for bus in eligible_buses:
            score = predict_bus_suitability(bus, trip, bus_histories.get(bus.id))
            if score is not None:
                outcome.bus_scores[bus.id] = score
        if not outcome.bus_scores:
            outcome.bus_readiness = ComponentReadiness(
                ml_ready=False,
                source="rule_fallback",
                reason="Bus ML model produced no valid predictions; using fallback.",
                sample_count=bus_ready.sample_count,
                model_path=_paths["bus_model"],
            )
    outcome.bus_histories = {
        b.id: bus_histories.get(b.id, {}) for b in eligible_buses
    }

    # --- Drivers ---
    if driver_ready.ml_ready:
        for driver in eligible_drivers:
            score = predict_driver_suitability(driver, trip, driver_histories.get(driver.id))
            if score is not None:
                outcome.driver_scores[driver.id] = score
        if not outcome.driver_scores:
            outcome.driver_readiness = ComponentReadiness(
                ml_ready=False,
                source="data_fallback",
                reason="Driver ML model produced no valid predictions; using data-derived reliability.",
                sample_count=driver_ready.sample_count,
                model_path=_paths["driver_model"],
            )
    else:
        # Data-derived reliability fallback for drivers.
        for driver in eligible_drivers:
            outcome.driver_scores[driver.id] = data_derived_driver_score(
                driver, driver_histories.get(driver.id)
            )
    outcome.driver_histories = {
        d.id: driver_histories.get(d.id, {}) for d in eligible_drivers
    }

    _log_predictions(trip, outcome)
    return outcome


def _log_predictions(trip: TripData, outcome: PredictionOutcome) -> None:
    context = encode_trip_context(trip)
    logger.debug(
        "Suitability for trip %s (route=%s, dep_hour=%.2f): "
        "bus_source=%s driver_source=%s bus_scores=%s driver_scores=%s",
        trip.trip_code,
        context.get("route_code"),
        context.get("departure_hour"),
        outcome.bus_readiness.source,
        outcome.driver_readiness.source,
        {str(k): round(v, 4) for k, v in sorted(outcome.bus_scores.items())},
        {str(k): round(v, 4) for k, v in sorted(outcome.driver_scores.items())},
    )
