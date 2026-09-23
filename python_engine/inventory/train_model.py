"""CLI: train Inventory Model #4 from the active prepared dataset."""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from inventory.config import data_source, model_paths, training_data_paths  # noqa: E402
from inventory.model import (  # noqa: E402
    InventoryModelResult,
    save_inventory_model,
    save_state,
    train_inventory_model,
)
from inventory.training_data import (  # noqa: E402
    build_bus_metadata,
    build_encoders,
    build_part_metadata,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("train_inventory_model")


def main() -> int:
    source = data_source()
    paths = model_paths()
    csv_path = training_data_paths()["features_csv"]

    if not csv_path.exists():
        print(f"Inventory feature CSV not found: {csv_path}")
        print("Run `python -m inventory.prepare_training_data` first.")
        result = InventoryModelResult(
            n_samples=0,
            message="No prepared training data found.",
            source=source,
        )
        save_inventory_model(result, paths)
        save_state(result, paths)
        return 1

    df = pd.read_csv(csv_path)
    df["date"] = pd.to_datetime(df["date"], errors="coerce")
    result = train_inventory_model(df, source=source)

    if result.trained:
        encoders = build_encoders(df)
        part_metadata = build_part_metadata(df)
        bus_metadata = build_bus_metadata(df)
        save_inventory_model(
            result,
            paths,
            encoders=encoders,
            part_metadata=part_metadata,
            bus_metadata=bus_metadata,
        )
    else:
        save_inventory_model(result, paths)
    save_state(result, paths)

    source_label = (
        "GENUINE GCT INVENTORY LEDGER"
        if source == "genuine"
        else "SAMPLE / DEVELOPMENT"
    )
    print(f"\n=== Inventory Model #4 training results ({source_label}) ===")
    print(f"Data source:      {source}")
    print(f"Sample count:     {result.n_samples}")
    if not result.trained:
        print(f"NOT TRAINED:      {result.message}")
        return 1

    print(f"Train rows:       {result.n_train}")
    print(f"Test rows:        {result.n_test}")
    print(
        f"Train period:     {result.periods.get('train_start')} .. "
        f"{result.periods.get('train_end')}"
    )
    print(
        f"Test period:      {result.periods.get('test_start')} .. "
        f"{result.periods.get('test_end')}"
    )
    print("Metrics (chronological held-out test):")
    print(f"  MAE  = {result.metrics['mae']:.3f} units/week")
    print(f"  RMSE = {result.metrics['rmse']:.3f} units/week")
    print(f"  R2   = {result.metrics['r2']:.4f}")
    print("Top feature importances:")
    for name, importance in sorted(
        result.feature_importances.items(), key=lambda item: -item[1]
    )[:8]:
        print(f"  {name:<30} {importance:.4f}")
    print(f"\nArtifacts written to: {paths['dir']}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
