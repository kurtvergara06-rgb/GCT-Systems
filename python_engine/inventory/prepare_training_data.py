"""CLI: Build the SAMPLE / DEVELOPMENT inventory training dataset.

Usage:
    python -m inventory.prepare_training_data

Generates the sample CSV if needed, validates it (structural + consistency
checks), feature-engineers the leakage-safe feature matrix, writes it to
``training_data/inventory/sample_inventory_training_features.csv`` (project
root) and prints the Phase-3 dataset report.

This pipeline operates ENTIRELY on generated sample data. It never reads or
writes the Laravel inventory tables.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from inventory.config import training_data_paths  # noqa: E402
from inventory.training_data import (  # noqa: E402
    INVENTORY_FEATURE_COLUMNS,
    build_dataset,
    check_readiness_thresholds,
    load_sample_csv,
    validate_dataset,
    write_features_csv,
)
from inventory.sample_data import generate_sample_data  # noqa: E402

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_inventory_data")


def main() -> int:
    paths = training_data_paths()

    if not paths["csv"].exists():
        print("Sample CSV not found - generating deterministic SAMPLE data...")
        generate_sample_data.write_sample_csv()

    df = load_sample_csv(paths["csv"])
    valid, errors, report = validate_dataset(df)

    print("\n=== Inventory SAMPLE dataset report (Phase 3) ===")
    print("NOTE: SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA")
    for key in [
        "total_rows", "buses", "parts", "categories", "date_min", "date_max",
        "avg_quantity_issued", "zero_demand_pct", "min_demand", "max_demand",
        "obs_per_part_min", "obs_per_part_max", "obs_per_bus_min", "obs_per_bus_max",
    ]:
        print(f"  {key:<22}: {report.get(key)}")
    print(f"  maintenance_types     : {report.get('maintenance_types')}")

    ok_thresholds, threshold_issues = check_readiness_thresholds(df)
    print(f"\nReadiness thresholds: {'PASS' if ok_thresholds else 'FAIL'}")
    for issue in threshold_issues:
        print(f"  - {issue}")

    if valid and ok_thresholds:
        wide = build_dataset(df)
        write_features_csv(wide, paths["features_csv"])
        print(f"\nFeature matrix written: {paths['features_csv']}")
        print(f"Feature rows (after 8-week warm-up drop): {len(wide)}")
        print(f"Features: {len(INVENTORY_FEATURE_COLUMNS)}")
        print("\nDone.")
        return 0

    print("\nValidation FAILED:")
    for error in errors:
        print(f"  - {error}")
    print("\nSkipping feature matrix build.")
    return 1


if __name__ == "__main__":
    sys.exit(main())