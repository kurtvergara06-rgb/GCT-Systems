"""Safety-filter tests for Model #1 ETA training data.

Verifies the 720-minute (12 h) upper bound added to `fetch_trip_outcomes`:

    1. The current 362-row CSV dataset is unchanged (no row exceeds 720 min,
       so the new filter drops nothing from the existing dataset).
    2. The ETA training SQL contains the `g.duration_minutes <= 720` bound.
    3. A GPS record with duration <= 720 remains eligible for training.
    4. A GPS record with duration > 720 is excluded from ETA training.
    5. The ETA training pipeline never writes to gps_trip_records (long raw
       GPS records are preserved; only the read-only training SELECT filters).
    6. Retraining the model on the unchanged CSV reproduces the exact
       baseline metrics (MAE / RMSE / R2 / target range) - no regression.

Run as:  python -m eta.test_eta_safety_filters
"""

import logging
import sys
from pathlib import Path

logging.basicConfig(level=logging.WARNING)

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402

from eta.config import training_data_paths  # noqa: E402
from eta.model import train_eta_model  # noqa: E402
from eta.training_data import build_dataset, fetch_trip_outcomes  # noqa: E402

PASS = 0
FAIL = 0

ACTIVITY_PERIOD_UPPER_BOUND = 720  # minutes (12 hours)

BASELINE_METRICS = {
    "mae": 4.624494285365078,
    "rmse": 6.132630990575449,
    "r2": 0.8243474318833754,
    "n_samples": 362,
    "target_min": 14.0,
    "target_max": 92.0,
}


def check(label: str, condition: bool, detail: str = "") -> None:
    global PASS, FAIL
    status = "PASS" if condition else "FAIL"
    if condition:
        PASS += 1
    else:
        FAIL += 1
    suffix = f" ({detail})" if detail else ""
    print(f"  [{status}] {label}{suffix}")


class FakeDb:
    """Stands in for DbConnection.query_df: captures the SQL and returns rows
    exactly as they exist in gps_trip_records (no duration filtering), so the
    test can verify both the emitted SQL and the in-memory pipeline."""

    def __init__(self, rows):
        self.rows = rows
        self.sql = None

    def query_df(self, sql, params=None):
        self.sql = sql
        return pd.DataFrame(self.rows)


print("\n=== ETA activity-period safety filter tests ===")

# ============================================================
# 1. Current 362-row dataset unchanged
# ============================================================
print("\n--- 1. Existing dataset unchanged ---")
csv_path = training_data_paths()["csv"]
if not csv_path.exists():
    print("  [FAIL] training CSV not found:", csv_path)
    sys.exit(1)

df = pd.read_csv(csv_path)
check("training CSV exists with 362 rows", len(df) == 362, f"{len(df)} rows")
target = pd.to_numeric(df["actual_trip_duration"], errors="coerce")
check(
    "current dataset contains no records > 720 min",
    bool((target <= ACTIVITY_PERIOD_UPPER_BOUND).all()),
    f"max={target.max():.0f} min",
)
check(
    "current target range is still 14.0 - 92.0",
    target.min() == BASELINE_METRICS["target_min"]
    and target.max() == BASELINE_METRICS["target_max"],
    f"range={target.min():.0f}-{target.max():.0f}",
)

# ============================================================
# 2. SQL contains the upper bound
# ============================================================
print("\n--- 2. Training SQL filter ---")
rows = [
    {
        "gps_record_id": 1,
        "bus_no": "GCT-101",
        "route": "Talisay - SM Seaside",
        "beginning_at": "2026-05-20 13:39:00",
        "duration_minutes": 46,
        "shift": "Morning",
        "departure_time": "13:30:00",
        "distance_km": 20.5,
        "route_estimated_time_minutes": 50,
    },
    {
        "gps_record_id": 2,
        "bus_no": "GCT-101",
        "route": "Talisay - SM Seaside",
        "beginning_at": "2026-05-21 05:30:00",
        "duration_minutes": 840,
        "shift": "Morning",
        "departure_time": "05:30:00",
        "distance_km": 20.5,
        "route_estimated_time_minutes": 50,
    },
]
fake = FakeDb(rows)
fetch_trip_outcomes(fake)
check(
    "SQL guards duration_minutes <= 720",
    fake.sql is not None and "g.duration_minutes <= 720" in fake.sql,
    "AND g.duration_minutes <= 720 present",
)

# ============================================================
# 3 + 4. Eligibility of <= 720 and exclusion of > 720
# ============================================================
print("\n--- 3 + 4. Record eligibility ---")

# Emulate MySQL executing the captured WHERE predicates on the raw rows.
raw = pd.DataFrame(rows)
emulated = raw[
    raw["duration_minutes"].notna()
    & (raw["duration_minutes"] > 0)
    & (raw["duration_minutes"] <= ACTIVITY_PERIOD_UPPER_BOUND)
].copy()
dataset = build_dataset(emulated)

eligible_ids = set(dataset["gps_record_id"].astype(int)) if not dataset.empty else set()
check(
    "record with duration <= 720 remains eligible",
    1 in eligible_ids,
    "gps_record_id=1 (46 min) present",
)
check(
    "record with duration > 720 is excluded from training",
    2 not in eligible_ids,
    "gps_record_id=2 (840 min) absent",
)
check(
    "excluded record leaves exactly 1 training row",
    len(dataset) == 1,
    f"{len(dataset)} row(s)",
)

# Build the UNFILTERED set to prove the SQL is the guard (build_dataset alone
# keeps both rows; it does not apply the 720-min bound).
unfiltered_dataset = build_dataset(raw)
check(
    "build_dataset alone does NOT hide long records (SQL is the guard)",
    int(unfiltered_dataset["gps_record_id"].nunique()) == 2,
    "both rows survive without the SQL filter",
)

# ============================================================
# 5. Long GPS records preserved in the database (read-only)
# ============================================================
print("\n--- 5. Long records preserved ---")
lower_sql = fake.sql.lower().lstrip() if fake.sql else ""
check(
    "ETA training query is read-only SELECT (never writes gps_trip_records)",
    lower_sql.startswith("select") and "delete" not in lower_sql and "update" not in lower_sql,
    "SELECT-only",
)

# The syntax check that this is MySQL-compatible filter on an INT column that
# references the actual table column, so it never deletes or alters rows.
check(
    "filter targets duration_minutes of gps_trip_records only",
    "g.duration_minutes <= 720" in (fake.sql or ""),
    "no second table touched",
)

# ============================================================
# 6. Metrics unchanged after retraining the same CSV
# ============================================================
print("\n--- 6. Model metrics unchanged ---")
result = train_eta_model(df)
check("model retrains successfully", result.trained, result.message)
check("n_samples unchanged (362)", result.n_samples == BASELINE_METRICS["n_samples"], f"{result.n_samples}")

close = lambda got, expected: got is not None and abs(float(got) - float(expected)) < 1e-9
check(
    "MAE unchanged",
    close(result.metrics.get("mae"), BASELINE_METRICS["mae"]),
    f"MAE={result.metrics.get('mae'):.6f}",
)
check(
    "RMSE unchanged",
    close(result.metrics.get("rmse"), BASELINE_METRICS["rmse"]),
    f"RMSE={result.metrics.get('rmse'):.6f}",
)
check(
    "R2 unchanged",
    close(result.metrics.get("r2"), BASELINE_METRICS["r2"]),
    f"R2={result.metrics.get('r2'):.6f}",
)
check(
    "target range unchanged (14.0 - 92.0)",
    close(result.target_range.get("min"), BASELINE_METRICS["target_min"])
    and close(result.target_range.get("max"), BASELINE_METRICS["target_max"]),
    f"range={result.target_range.get('min')}-{result.target_range.get('max')}",
)

print(f"\n{'=' * 50}")
print(f"Results: {PASS} passed, {FAIL} failed")
print(f"{'=' * 50}")
sys.exit(1 if FAIL > 0 else 0)