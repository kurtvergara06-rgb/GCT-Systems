"""Feature extraction for the Operation AI machine-learning ranking.

Converts live scheduling candidate payloads (DriverData / BusData) into the
same fixed-length numeric feature vectors that were used at training time.

The feature sets are aligned 1:1 with the training pipeline in
operation_ai.ml.training_data, so predictions at runtime match the model's
training distribution exactly:
  - BUS    features == BUS_FEATURE_COLUMNS (per-trip bus model)
  - DRIVER features == DRIVER_FEATURE_COLUMNS (per-driver reliability model)
"""

from typing import Dict, List, Optional

import numpy as np

from ..schemas import BusData, DriverData, TripData

# ---------------------------------------------------------------------------
# Static encoding maps (kept identical to training-time maps)
# ---------------------------------------------------------------------------

_SHIFT_MAP = {"Morning": 0, "Afternoon": 1, "Night": 2, "Swing": 3}

# Training normalizes driver status strings to lowercase; these are the
# present-attendance encodings used by the driver model.
_PRESENT_STATUS = {"present", "late", "on duty"}
_DRIVER_STATUS_MAP = {
    "Present": 1.0,
    "Late": 0.5,
    "Absent": 0.0,
    "On Leave": 0.0,
    "On Duty": 0.0,
}

_BUS_STATUS_MAP = {
    "Active": 1.0,
    "Inactive": 0.0,
    "Under Maintenance": 0.0,
}


def _safe(value, default=0.0):
    if value is None:
        return default
    return value


def _hour(time_str: Optional[str]) -> float:
    if not time_str:
        return -1.0
    try:
        parts = str(time_str).split(":")
        return float(parts[0]) + float(parts[1]) / 60.0 if len(parts) >= 2 else float(parts[0])
    except (ValueError, IndexError):
        return -1.0


def _clamp(value, lo=0.0, hi=1.0) -> float:
    try:
        v = float(value)
    except (TypeError, ValueError):
        return lo
    return max(lo, min(hi, v))


# ---------------------------------------------------------------------------
# Driver features (aligned with DRIVER_FEATURE_COLUMNS)
# ---------------------------------------------------------------------------

DRIVER_FEATURE_NAMES: List[str] = [
    "shift_encoded",
    "attendance_rate",
    "late_rate",
    "total_attendance",
    "distinct_dates",
]


def _build_driver_history() -> Dict[str, float]:
    return {
        "attendance_rate": 0.0,
        "late_rate": 0.0,
        "total_attendance": 0.0,
        "distinct_dates": 0.0,
        "has_history": 0.0,
    }


def encode_driver_features(
    driver: DriverData,
    trip: TripData,
    history: Optional[Dict[str, float]] = None,
) -> np.ndarray:
    """Encode a driver candidate into the per-driver reliability vector.

    `history` comes from Laravel's per-driver attendance profile. When absent
    (no observed attendance history) all history-derived terms default to 0,
    which the model then treats as an unobserved driver.
    """
    hist = history or _build_driver_history()
    has_history = 1.0 if (history and _safe(hist.get("has_history"))) else 0.0

    shift = str(driver.shift or "").lower()
    shift_encoded = float(_SHIFT_MAP.get(shift, -1.0))
    if has_history == 0.0 or not shift:
        # No observed attendance history, or no shift on the candidate:
        # an unknown modal shift is the safest encoding for an unobserved driver.
        shift_encoded = -1.0

    values = [
        shift_encoded,
        _clamp(_safe(hist.get("attendance_rate"))),
        _clamp(_safe(hist.get("late_rate"))),
        _safe(hist.get("total_attendance")),
        _safe(hist.get("distinct_dates")),
    ]
    return np.asarray(values, dtype=np.float32)


# ---------------------------------------------------------------------------
# Bus features (aligned with BUS_FEATURE_COLUMNS - per-trip bus model)
# ---------------------------------------------------------------------------

BUS_FEATURE_NAMES: List[str] = [
    "status_encoded",
    "capacity",
    "remaining_pms_pct",
    "fuel_efficiency_avg",
    "active_job_orders",
    "total_job_orders",
    "trip_mileage_km",
    "trip_engine_hours",
    "trip_idling_minutes",
    "trip_total_minutes",
    "trip_hour",
]


def _build_bus_history() -> Dict[str, float]:
    return {
        "capacity": 0.0,
        "fuel_efficiency_avg": 0.0,
        "active_job_orders": 0.0,
        "total_job_orders": 0.0,
        "avg_trip_mileage_km": 0.0,
        "avg_trip_engine_hours": 0.0,
        "avg_trip_idling_minutes": 0.0,
        "avg_trip_total_minutes": 0.0,
        "has_history": 0.0,
    }


def encode_bus_features(
    bus: BusData,
    trip: TripData,
    history: Optional[Dict[str, float]] = None,
) -> np.ndarray:
    """Encode a bus candidate into the per-trip performance vector.

    The bus model was trained on real GPS trips. At dispatch time there is no
    new GPS record for the bus yet, so the trip_* mechanical features are set
    from the bus's observed historical averages (from `history`). The single
    live value injected from the new trip is `trip_hour` (departure hour).
    """
    hist = history or _build_bus_history()

    remaining_pms_pct = 0.0
    if (
        bus.mileage is not None
        and bus.next_pms_mileage is not None
        and bus.next_pms_mileage > 0
    ):
        remaining = bus.next_pms_mileage - bus.mileage
        remaining_pms_pct = _clamp(remaining / bus.next_pms_mileage)

    capacity = _safe(hist.get("capacity"))
    if not hist.get("has_history"):
        capacity = 0.0

    values = [
        _BUS_STATUS_MAP.get(bus.status, 0.0),
        capacity,
        remaining_pms_pct,
        _safe(hist.get("fuel_efficiency_avg")),
        _safe(hist.get("active_job_orders")),
        _safe(hist.get("total_job_orders")),
        _safe(hist.get("avg_trip_mileage_km")),
        _safe(hist.get("avg_trip_engine_hours")),
        _safe(hist.get("avg_trip_idling_minutes")),
        _safe(hist.get("avg_trip_total_minutes")),
        _hour(trip.departure_time),
    ]
    return np.asarray(values, dtype=np.float32)


# ---------------------------------------------------------------------------
# Trip context (used for logging only / future combined models)
# ---------------------------------------------------------------------------

def encode_trip_context(trip: TripData) -> Dict[str, float]:
    """Encode trip-level context for logging and provenance."""
    return {
        "shift_encoded": float(_SHIFT_MAP.get((trip.shift or "").lower(), -1.0)),
        "departure_hour": _hour(trip.departure_time),
        "arrival_hour": _hour(trip.arrival_time),
        "route_code": trip.route_code or "",
    }
