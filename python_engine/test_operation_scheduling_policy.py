"""Regression checks for Operation scheduling presence and scoring policy."""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from operation_ai.ml.predict import data_derived_driver_score
from operation_ai.ranking import is_driver_eligible
from operation_ai.schemas import DriverData


assumed_present = DriverData(
    id=1,
    name="Active Driver",
    status="Assumed Present",
    assigned_trips=0,
    assigned_minutes=0,
    has_conflict=False,
)

assert is_driver_eligible(assumed_present) is True
assert data_derived_driver_score(
    assumed_present,
    {"attendance_rate": 0.75, "total_attendance": 12, "distinct_dates": 12},
) == 0.75
assert data_derived_driver_score(assumed_present, {}) == 0.0

conflicting = assumed_present.model_copy(update={"has_conflict": True})
assert is_driver_eligible(conflicting) is False

print("Operation scheduling policy checks passed.")
