"""Focused quality test for Delay Model #3 SAMPLE data.

This test protects the modeling contract:
- no post-trip leakage enters DLY_FEATURE_COLUMNS
- synthetic incidents that affect the target are represented by pre-trip flags
- chronological holdout performance is materially better than the mean baseline

It does not claim production accuracy; the dataset is explicitly SAMPLE data.
"""

from __future__ import annotations

import math

from delay.model import train_delay_model
from delay.sample_generator import generate_sample_dataset
from delay.training_data import (
    DELAY_INCIDENT_FEATURES,
    DLY_FEATURE_COLUMNS,
    FORBIDDEN_FEATURES,
    build_dataset,
    validate_dataset,
)

MIN_SAMPLE_R2 = 0.45
MAX_SAMPLE_MAE_MINUTES = 2.50


def main() -> int:
    raw = generate_sample_dataset(n=500, seed=42)

    valid, errors, _report = validate_dataset(raw)
    if not valid:
        raise AssertionError(f"Improved SAMPLE dataset failed validation: {errors}")

    missing_incident_columns = [
        name for name in DELAY_INCIDENT_FEATURES if name not in raw.columns
    ]
    if missing_incident_columns:
        raise AssertionError(
            f"Missing pre-trip incident features: {missing_incident_columns}"
        )

    if int(raw["incident_before_departure"].sum()) <= 0:
        raise AssertionError("SAMPLE dataset contains no pre-trip incidents")

    leaked = [name for name in FORBIDDEN_FEATURES if name in DLY_FEATURE_COLUMNS]
    if leaked:
        raise AssertionError(f"Post-trip leakage found in feature list: {leaked}")

    wide = build_dataset(raw)
    # build_dataset also carries trace columns for reporting; one trace field
    # (scheduled_duration_minutes) is intentionally also a model feature. Keep
    # only the first copy before feeding the in-memory frame to scikit-learn.
    # The CSV pipeline naturally normalizes this on reload, but the focused CI
    # test should behave consistently on current scikit-learn versions too.
    wide = wide.loc[:, ~wide.columns.duplicated()].copy()

    result = train_delay_model(wide, source="sample")
    if not result.trained:
        raise AssertionError(f"Delay model did not train: {result.message}")

    r2 = float(result.metrics["r2"])
    mae = float(result.metrics["mae"])
    rmse = float(result.metrics["rmse"])

    if not all(math.isfinite(value) for value in (r2, mae, rmse)):
        raise AssertionError(
            f"Non-finite model metrics: R2={r2}, MAE={mae}, RMSE={rmse}"
        )

    if r2 < MIN_SAMPLE_R2:
        raise AssertionError(
            f"Chronological SAMPLE R2 regressed: {r2:.4f} < {MIN_SAMPLE_R2:.2f}"
        )

    if mae > MAX_SAMPLE_MAE_MINUTES:
        raise AssertionError(
            f"Chronological SAMPLE MAE regressed: {mae:.3f} > "
            f"{MAX_SAMPLE_MAE_MINUTES:.2f} minutes"
        )

    print("Delay Model #3 SAMPLE signal test PASS")
    print(f"  rows: {len(wide)}")
    print(f"  incidents before departure: {int(raw['incident_before_departure'].sum())}")
    print(f"  R2: {r2:.4f}")
    print(f"  MAE: {mae:.3f} min")
    print(f"  RMSE: {rmse:.3f} min")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
