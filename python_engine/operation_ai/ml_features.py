"""Feature encoding for the scheduling neural network.

Converts Pydantic schema objects (TripData, DriverData, BusData) into
fixed-size tensors suitable for the SchedulingScorer model.
"""

from typing import Optional

import torch

from .schemas import BusData, DriverData, TripData


# --- Encoding maps ---

SHIFT_MAP = {"Morning": 0, "Afternoon": 1, "Night": 2, "Swing": 3}

DRIVER_STATUS_MAP = {
    "Present": 0,
    "Late": 1,
    "Absent": 2,
    "On Leave": 3,
    "On Duty": 4,
}

BUS_STATUS_MAP = {
    "Active": 0,
    "Inactive": 1,
    "Under Maintenance": 2,
}


def _safe_encode(mapping: dict, value: Optional[str], default: float = -1.0) -> float:
    if value is None:
        return default
    return float(mapping.get(value, default))


def _parse_hour(time_str: Optional[str]) -> float:
    if not time_str:
        return -1.0
    try:
        parts = str(time_str).split(":")
        return float(parts[0]) + float(parts[1]) / 60.0 if len(parts) >= 2 else float(parts[0])
    except (ValueError, IndexError):
        return -1.0


def _remaining_pms_pct(bus: BusData) -> float:
    if bus.mileage is None or bus.next_pms_mileage is None:
        return -1.0
    if bus.next_pms_mileage <= 0:
        return 0.0
    remaining = bus.next_pms_mileage - bus.mileage
    return max(0.0, remaining / bus.next_pms_mileage)


def encode_trip(trip: TripData, driver_shift: Optional[str] = None) -> torch.Tensor:
    """Encode trip features. Pass driver_shift to include shift_match flag."""
    shift_match_val = 0.0
    if driver_shift and trip.shift:
        shift_match_val = 1.0 if trip.shift == driver_shift else -1.0

    return torch.tensor(
        [
            _safe_encode(SHIFT_MAP, trip.shift),
            _parse_hour(trip.departure_time),
            hash(trip.route_code or "") % 1000 / 1000.0,
            _parse_hour(trip.arrival_time) - _parse_hour(trip.departure_time),
            shift_match_val,
        ],
        dtype=torch.float32,
    )


def encode_driver(driver: DriverData) -> torch.Tensor:
    return torch.Tensor(
        [
            _safe_encode(DRIVER_STATUS_MAP, driver.status),
            1.0 if driver.shift else 0.0,
            float(driver.assigned_trips),
            float(driver.assigned_minutes),
            1.0 if driver.has_conflict else 0.0,
        ]
    )


def encode_bus(bus: BusData) -> torch.Tensor:
    return torch.Tensor(
        [
            _safe_encode(BUS_STATUS_MAP, bus.status),
            float(bus.assigned_trips),
            float(bus.assigned_minutes),
            1.0 if bus.has_conflict else 0.0,
            _remaining_pms_pct(bus),
        ]
    )


def encode_all(
    trip: TripData,
    driver: DriverData,
    bus: BusData,
) -> tuple[torch.Tensor, torch.Tensor, torch.Tensor]:
    """Return (trip_tensor, driver_tensor, bus_tensor), each 1-D float32."""
    return encode_trip(trip, driver.shift), encode_driver(driver), encode_bus(bus)


def shift_match(trip: TripData, driver: DriverData) -> bool:
    if not trip.shift or not driver.shift:
        return False
    return trip.shift == driver.shift
