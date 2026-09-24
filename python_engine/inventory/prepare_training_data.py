"""CLI: prepare Inventory Model #4 training data.

Modes:
- sample: deterministic generated development CSV
- demo: frontend-visible synthetic warehouse movements (source='demo')
- genuine: application-written warehouse movements (source='app')
"""

import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from inventory.config import data_source, training_data_paths  # noqa: E402
from inventory.demo_data import fetch_demo_stock_movements  # noqa: E402
from inventory.training_data import (  # noqa: E402
    INVENTORY_FEATURE_COLUMNS,
    build_dataset,
    check_readiness_thresholds,
    load_training_data,
    validate_dataset,
    write_features_csv,
)
from inventory.sample_data import generate_sample_data  # noqa: E402

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_inventory_data")


def main() -> int:
    source = data_source()
    paths = training_data_paths()

    if source == "sample" and not paths["csv"].exists():
        print("Sample CSV not found - generating deterministic DEVELOPMENT data...")
        generate_sample_data.write_sample_csv()

    if source == "demo":
        df = fetch_demo_stock_movements()
        loaded_source = "demo"
    else:
        df, loaded_source = load_training_data(
            paths["csv"] if source == "sample" else None
        )

    if loaded_source in {"genuine", "demo"}:
        paths["dir"].mkdir(parents=True, exist_ok=True)
        df.to_csv(paths["csv"], index=False)

    valid, errors, report = validate_dataset(df)

    if loaded_source == "genuine":
        label = "GENUINE GCT INVENTORY LEDGER"
    elif loaded_source == "demo":
        label = "FRONTEND DEMO / SYNTHETIC WAREHOUSE DATA"
    else:
        label = "SAMPLE / DEVELOPMENT"

    print(f"\n=== Inventory Model #4 dataset report ({label}) ===")
    for key in [
        "total_rows", "buses", "parts", "categories", "date_min", "date_max",
        "avg_quantity_issued", "zero_demand_pct", "min_demand", "max_demand",
        "stock_out_events", "obs_per_part_min", "obs_per_part_max",
    ]:
        if key in report:
            print(f"  {key:<22}: {report.get(key)}")

    ok_thresholds, threshold_issues = check_readiness_thresholds(df)
    print(f"\nReadiness thresholds: {'PASS' if ok_thresholds else 'FAIL'}")
    for issue in threshold_issues:
        print(f"  - {issue}")

    if valid and ok_thresholds:
        wide = build_dataset(df)
        if wide.empty:
            print("\nFeature engineering produced no trainable rows.")
            return 1
        write_features_csv(wide, paths["features_csv"])
        print(f"\nRaw panel:      {paths['csv']}")
        print(f"Feature matrix: {paths['features_csv']}")
        print(f"Feature rows:   {len(wide)}")
        print(f"Features:       {len(INVENTORY_FEATURE_COLUMNS)}")
        print(f"Source:         {loaded_source}")
        if loaded_source == "demo":
            print("DEMO / SYNTHETIC ONLY - not genuine GCT operational history.")
        return 0

    print("\nDataset is not ready for model training:")
    for error in errors:
        print(f"  - {error}")
    if loaded_source == "genuine":
        print("No synthetic fallback was used.")
    elif loaded_source == "demo":
        print("Run ClientDemoDataSeeder first and verify source='demo' stock movements exist.")
    return 1


if __name__ == "__main__":
    sys.exit(main())
