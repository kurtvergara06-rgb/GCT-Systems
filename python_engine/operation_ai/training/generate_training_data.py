"""Training data generator for the scheduling neural network.

Generates synthetic training samples by simulating scheduling scenarios
and scoring them with the existing rule-based system. The rule-based
scores become the training labels (knowledge distillation).

Usage:
    python -m operation_ai.training.generate_training_data

Output:
    python_engine/training_data/scheduling_training.jsonl
"""

import json
import os
import random
import sys
from pathlib import Path

# Ensure parent package is importable when run as a script
_HERE = Path(__file__).resolve().parent
_PARENT = _HERE.parent
if str(_PARENT.parent) not in sys.path:
    sys.path.insert(0, str(_PARENT.parent))

from operation_ai.ranking import bus_score, driver_score, is_bus_eligible, is_driver_eligible
from operation_ai.schemas import BusData, DriverData, TripData

OUTPUT_DIR = Path(__file__).resolve().parent.parent.parent / "training_data"
OUTPUT_PATH = OUTPUT_DIR / "scheduling_training.jsonl"

SHIFTS = ["Morning", "Afternoon", "Night"]
ROUTES = ["Lipa-Batangas", "Batangas-Lipa", "Lipa-Tanauan", "Tanauan-Lipa", "Lipa-SantoTomas", "SantoTomas-Lipa"]
BUS_NOS = [f"GCT-{i:03d}" for i in range(1, 21)]
DRIVER_NAMES = [f"Driver_{i}" for i in range(1, 31)]

DEPARTURE_HOURS = list(range(5, 22))
EST_DURATIONS = [30, 40, 45, 50, 60, 75, 90]


def _random_driver(
    eligible: bool = True,
    force_status: str | None = None,
    force_shift: str | None = None,
    force_conflict: bool | None = None,
) -> DriverData:
    if force_status is not None:
        status = force_status
    elif eligible:
        status = random.choice(["Present", "Present", "Present", "Late"])
    else:
        status = random.choice(["Absent", "On Leave", "On Duty"])

    has_conflict = force_conflict if force_conflict is not None else (random.random() < (0.0 if eligible else 0.5))

    return DriverData(
        id=random.randint(1, 9999),
        name=random.choice(DRIVER_NAMES),
        status=status,
        shift=force_shift if force_shift else random.choice(SHIFTS),
        assigned_trips=random.randint(0, 6),
        assigned_minutes=random.randint(0, 300),
        has_conflict=has_conflict,
        conflict_end_time=None,
    )


def _random_bus(
    eligible: bool = True,
    force_status: str | None = None,
    force_conflict: bool | None = None,
) -> BusData:
    if force_status is not None:
        status = force_status
    elif eligible:
        status = "Active"
    else:
        status = random.choice(["Inactive", "Under Maintenance"])

    has_conflict = force_conflict if force_conflict is not None else (random.random() < (0.0 if eligible else 0.5))

    mileage = random.uniform(5000, 80000)
    next_pms = mileage + random.uniform(500, 5000)

    return BusData(
        id=random.randint(1, 9999),
        bus_no=random.choice(BUS_NOS),
        status=status,
        mileage=round(mileage, 2),
        next_pms_mileage=round(next_pms, 2),
        assigned_trips=random.randint(0, 5),
        assigned_minutes=random.randint(0, 250),
        has_conflict=has_conflict,
        conflict_end_time=None,
    )


def _random_trip() -> TripData:
    shift = random.choice(SHIFTS)
    dep_hour = random.choice(DEPARTURE_HOURS)
    duration = random.choice(EST_DURATIONS)
    arr_hour = min(23, dep_hour + duration // 60)

    return TripData(
        id=random.randint(1, 9999),
        trip_code=f"TRIP-{random.randint(1000, 9999)}",
        trip_date="2026-09-04",
        shift=shift,
        route_code=random.choice(ROUTES),
        route_name=random.choice(ROUTES),
        departure_time=f"{dep_hour:02d}:{random.choice([0, 15, 30, 45]):02d}",
        arrival_time=f"{arr_hour:02d}:{random.choice([0, 15, 30, 45]):02d}",
    )


def _score_pair(trip: TripData, driver: DriverData, bus: BusData) -> dict:
    """Score a (trip, driver, bus) pair using the rule-based system."""
    eligible_driver = is_driver_eligible(driver, trip.shift)
    eligible_bus = is_bus_eligible(bus)

    d_score = driver_score(driver, trip.shift)
    b_score = bus_score(bus)

    combined = 0
    if eligible_driver and eligible_bus:
        combined = (d_score + b_score) / 2
    elif not eligible_driver or not eligible_bus:
        combined = 0

    return {
        "driver_score": d_score,
        "bus_score": b_score,
        "combined_score": round(combined, 1),
        "eligible": eligible_driver and eligible_bus,
    }


def _build_driver(driver_eligible: bool, trip_shift: str) -> DriverData:
    """Build a driver. When ineligible, vary the failure mode so the model
    learns to distinguish: bad status, shift mismatch, or conflict."""
    if driver_eligible:
        # Sometimes produce an ideal zero-workload driver
        if random.random() < 0.15:
            return _random_driver(
                eligible=True,
                force_shift=trip_shift,
                force_conflict=False,
            ).model_copy(update={"assigned_trips": 0, "assigned_minutes": 0})
        return _random_driver(
            eligible=True,
            force_shift=trip_shift,
            force_conflict=False,
        )

    # Ineligible: distinct failure modes
    mode = random.random()
    if mode < 0.35:
        # Bad status but otherwise fine
        return _random_driver(force_status=random.choice(["Absent", "On Leave", "On Duty"]),
                              force_shift=trip_shift,
                              force_conflict=False)
    elif mode < 0.7:
        # Present but wrong shift
        wrong_shift = random.choice([s for s in SHIFTS if s != trip_shift])
        return _random_driver(force_status="Present", force_shift=wrong_shift, force_conflict=False)
    else:
        # Present but has a conflicting assignment
        return _random_driver(force_status="Present", force_shift=trip_shift, force_conflict=True)


def _build_bus(bus_eligible: bool) -> BusData:
    """Build a bus. When ineligible, vary the failure mode."""
    if bus_eligible:
        if random.random() < 0.15:
            return _random_bus(eligible=True, force_conflict=False).model_copy(
                update={"assigned_trips": 0, "assigned_minutes": 0}
            )
        return _random_bus(eligible=True, force_conflict=False)

    mode = random.random()
    if mode < 0.6:
        return _random_bus(force_status=random.choice(["Inactive", "Under Maintenance"]), force_conflict=False)
    else:
        return _random_bus(force_status="Active", force_conflict=True)


def generate_sample() -> dict:
    """Generate one training sample: trip + candidate driver + candidate bus + label.

    Ensures ~50/50 balance between eligible and ineligible pairs.
    """
    trip = _random_trip()

    make_eligible = random.random() < 0.5

    if make_eligible:
        driver_eligible = True
        bus_eligible = True
    else:
        roll = random.random()
        if roll < 0.4:
            driver_eligible = False
            bus_eligible = True
        elif roll < 0.8:
            driver_eligible = True
            bus_eligible = False
        else:
            driver_eligible = False
            bus_eligible = False

    driver = _build_driver(driver_eligible, trip.shift)
    bus = _build_bus(bus_eligible)

    scores = _score_pair(trip, driver, bus)

    return {
        "trip": {
            "shift": trip.shift,
            "departure_time": trip.departure_time,
            "route_code": trip.route_code,
            "arrival_time": trip.arrival_time,
        },
        "driver": {
            "status": driver.status,
            "shift": driver.shift,
            "assigned_trips": driver.assigned_trips,
            "assigned_minutes": driver.assigned_minutes,
            "has_conflict": driver.has_conflict,
        },
        "bus": {
            "status": bus.status,
            "assigned_trips": bus.assigned_trips,
            "assigned_minutes": bus.assigned_minutes,
            "has_conflict": bus.has_conflict,
            "mileage": bus.mileage,
            "next_pms_mileage": bus.next_pms_mileage,
        },
        "label": scores["combined_score"],
        "eligible": scores["eligible"],
    }


def generate(n_samples: int = 5000, output_path: str | None = None):
    """Generate n_samples training records and write to JSONL."""
    path = Path(output_path) if output_path else OUTPUT_PATH
    path.parent.mkdir(parents=True, exist_ok=True)

    samples = [generate_sample() for _ in range(n_samples)]

    eligible = sum(1 for s in samples if s["eligible"])
    print(f"Generated {n_samples} samples ({eligible} eligible, {n_samples - eligible} ineligible)")

    with open(path, "w", encoding="utf-8") as f:
        for sample in samples:
            f.write(json.dumps(sample) + "\n")

    print(f"Saved to {path}")
    return path


if __name__ == "__main__":
    n = int(sys.argv[1]) if len(sys.argv) > 1 else 5000
    generate(n)
