"""CLI: Build the SAMPLE / DEVELOPMENT delay training datasets.

Usage:
    python -m delay.prepare_training_data

Generates the SAMPLE CSV if needed (deterministic), validates it (structural
+ consistency checks), feature-engineers the leakage-safe feature matrix and
writes it to ``training_data/delay/sample_delay_training_features.csv``
(project root), then prints the dataset report.

SAMPLE / DEMONSTRATION DATA ONLY - this pipeline generates its own data and
NEVER reads or writes the Laravel MySQL database.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from delay.config import DISCLAIMER, training_data_paths  # noqa: E402
from delay.training_data import (  # noqa: E402
    DLY_FEATURE_COLUMNS,
    DLY_TARGET,
    build_dataset,
    check_readiness_thresholds,
    generate_sample_csv,
    load_sample_csv,
    validate_dataset,
    write_features_csv,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_delay_data")


def main() -> int:
    paths = training_data_paths()

    if not paths["csv"].exists():
        print("Sample CSV not found - generating deterministic SAMPLE data...")
        generate_sample_csv()

    df = load_sample_csv(paths["csv"])
    valid, errors, report = validate_dataset(df)

    print("\n=== Delay SAMPLE dataset report ===")
    print(f"DISCLAIMER: {DISCLAIMER}")
    for key in [
        "total_rows", "routes", "buses", "drivers", "date_min", "date_max",
        "distinct_dates", "span_days", "distinct_weeks",
        "arrival_delay_min", "arrival_delay_max", "arrival_delay_mean",
        "on_time_pct", "delay_distro",
    ]:
        print(f"  {key:<22}: {report.get(key)}")

    ok_thresholds, threshold_issues = check_readiness_thresholds(df)
    print(f"\nReadiness thresholds: {'PASS' if ok_thresholds else 'FAIL'}")
    for issue in threshold_issues:
        print(f"  - {issue}")

    if not valid:
        print("\nValidation FAILED:")
        for error in errors:
            print(f"  - {error}")
        print("\nSkipping feature matrix build.")
        return 1

    wide = build_dataset(df)
    write_features_csv(wide, paths["features_csv"])
    print(f"\nFeature matrix written: {paths['features_csv']}")
    print(f"Feature rows:          {len(wide)}")
    print(f"Features ({len(DLY_FEATURE_COLUMNS)}): {', '.join(DLY_FEATURE_COLUMNS)}")
    print(f"Target:                {DLY_TARGET} (never an input feature)")
    print("\nDone.")
    return 0


if __name__ == "__main__":
    sys.exit(main())