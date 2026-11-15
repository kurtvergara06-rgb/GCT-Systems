"""CLI: Train the Random Forest ranking models.

Usage:
    python -m operation_ai.ml.train_model

Reads the CSV datasets produced by prepare_training_data, trains independent
RandomForestRegressor models for buses and drivers, evaluates them on a
held-out test split, and saves:
    operation_ai/models/scheduling_bus_rf.pkl
    operation_ai/models/scheduling_driver_rf.pkl
    operation_ai/models/scheduling_bus_rf_report.txt
    operation_ai/models/scheduling_driver_rf_report.txt
    operation_ai/models/scheduling_ml_state.json

If there is insufficient real data the models are NOT produced and the state
file reports ML_NOT_READY; the runtime system then falls back to rules.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from operation_ai.ml.config import model_paths, training_data_paths  # noqa: E402
from operation_ai.ml.model import (  # noqa: E402
    save_model_result,
    save_state,
    train_bus_model,
    train_driver_model,
)
from operation_ai.ml.training_data import (  # noqa: E402
    BUS_FEATURE_COLUMNS,
    BUS_LABEL,
    DRIVER_FEATURE_COLUMNS,
    DRIVER_LABEL,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("train_model")


def main() -> int:
    paths = model_paths()
    data_paths = training_data_paths()

    bus_csv = data_paths["bus_csv"]
    driver_csv = data_paths["driver_csv"]

    bus_result = None
    driver_result = None

    if bus_csv.exists():
        bus_df = pd.read_csv(bus_csv)
        # Validate feature/label columns before training.
        missing = [c for c in BUS_FEATURE_COLUMNS + [BUS_LABEL] if c not in bus_df.columns]
        if missing:
            logger.error("Bus training data missing columns: %s", missing)
        else:
            bus_result = train_bus_model(bus_df)
    else:
        logger.warning("Bus training CSV not found: %s", bus_csv)
        print("  (no bus training data)")
        from operation_ai.ml.model import ModelResult
        bus_result = ModelResult(kind="bus", trained=False, message="No bus training data found.", n_samples=0)

    if driver_csv.exists():
        driver_df = pd.read_csv(driver_csv)
        missing = [c for c in DRIVER_FEATURE_COLUMNS + [DRIVER_LABEL] if c not in driver_df.columns]
        if missing:
            logger.error("Driver training data missing columns: %s", missing)
        else:
            driver_result = train_driver_model(driver_df)
    else:
        logger.warning("Driver training CSV not found: %s", driver_csv)
        print("  (no driver training data)")
        from operation_ai.ml.model import ModelResult
        driver_result = ModelResult(kind="driver", trained=False, message="No driver training data found.", n_samples=0)

    save_model_result(bus_result, paths)
    save_model_result(driver_result, paths)
    save_state(bus_result, driver_result, paths)

    print("\n=== Training results ===")
    _print_result(bus_result, "BUS")
    _print_result(driver_result, "DRIVER")
    print(f"\nReports written to {paths['dir']}")
    print("State file: " + str(paths["state"]))

    ready = bus_result.trained and driver_result.trained
    print(f"\nML ranking ready: {ready}")
    return 0 if ready else 1


def _print_result(result, title: str) -> None:
    print(f"\n--- {title} MODEL ---")
    if not result.trained:
        print(f"  NOT TRAINED: {result.message}")
        return
    print(f"  Samples:    {result.n_samples}")
    print(f"  Train rows: {result.n_train}")
    print(f"  Test rows:  {result.n_test}")
    print("  Metrics (held-out test):")
    print(f"    MAE  = {result.metrics['mae']:.4f}")
    print(f"    RMSE = {result.metrics['rmse']:.4f}")
    print(f"    R2   = {result.metrics['r2']:.4f}")
    print("  Top feature importances:")
    for name, imp in sorted(result.feature_importances.items(), key=lambda kv: -kv[1])[:5]:
        print(f"    {name:<28} {imp:.4f}")


if __name__ == "__main__":
    sys.exit(main())
