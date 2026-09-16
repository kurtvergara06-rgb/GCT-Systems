"""Configuration for the Model #4 inventory demand-forecasting subsystem.

Mirrors the ETA / Fuel config convention. The development pipeline operates
on GENERATED SAMPLE data stored in the central training folder
``training_data/inventory/`` (project root); it never reads or writes the
Laravel inventory tables. When genuine ``stock_movements`` rows
(``source = 'app'``) become available, set ``INVENTORY_DATA_SOURCE=genuine``
to switch the extractor — see ``training_data.fetch_genuine_stock_movements``.
"""

import os
from pathlib import Path
from typing import Dict

DISCLAIMER = (
    "SAMPLE / DEVELOPMENT DATA — NOT ACTUAL GCT OPERATIONAL DATA. "
    "The current model is a development/prototype model trained on generated "
    "sample data. It must not be presented as a model trained on actual GCT "
    "operational inventory data."
)


def data_source() -> str:
    """Return the training-data source key ('sample' default, 'genuine' opt-in)."""
    return os.environ.get("INVENTORY_DATA_SOURCE", "sample").strip().lower()


def data_thresholds() -> Dict[str, int]:
    """Minimum data before the inventory model is considered usable."""
    return {
        "min_rows": int(os.environ.get("INVENTORY_MIN_ROWS", "1000")),
        "min_buses": int(os.environ.get("INVENTORY_MIN_BUSES", "3")),
        "min_parts": int(os.environ.get("INVENTORY_MIN_PARTS", "10")),
        "min_weeks": int(os.environ.get("INVENTORY_MIN_WEEKS", "13")),
    }


def rf_convention() -> Dict[str, object]:
    """Project-wide Random Forest convention (reused by ETA / Fuel / Operation AI)."""
    return {
        "n_estimators": 200,
        "max_depth": None,
        "min_samples_leaf": 2,
        "max_features": "sqrt",
        "random_state": 42,
        "n_jobs": -1,
    }


def model_paths() -> Dict[str, Path]:
    """Canonical paths for the saved inventory model artifacts."""
    models_dir = Path(__file__).resolve().parent / "models"
    return {
        "dir": models_dir,
        "model": models_dir / "inventory_demand_rf.pkl",
        "report": models_dir / "inventory_demand_report.txt",
        "features": models_dir / "inventory_demand_features.json",
        "state": models_dir / "inventory_demand_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Canonical paths for the inventory training datasets (central folder).

    Follows the ETA / Fuel convention: the central ``training_data`` directory
    lives at the repository root (``python_engine/config.py``'s 2nd parent).
    """
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "inventory"
    return {
        "dir": data_dir,
        "csv": data_dir / "sample_inventory_training.csv",
        "features_csv": data_dir / "sample_inventory_training_features.csv",
    }


def forecast_test_fraction() -> float:
    """Fraction of the chronologically LATEST weeks reserved for the test set."""
    return float(os.environ.get("INVENTORY_TEST_FRACTION", "0.2"))


def forecast_horizon() -> str:
    """Forecast horizon label ('next_week' for the weekly development model)."""
    return "next_week"