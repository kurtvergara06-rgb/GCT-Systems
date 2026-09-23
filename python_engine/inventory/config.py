"""Configuration for Inventory Model #4.

Development may still use generated sample data for UI/model-development work.
Production defaults to genuine GCT inventory ledger rows (``source='app'``)
and never falls back to sample artifacts.
"""

import os
from pathlib import Path
from typing import Dict

from ml_runtime_policy import is_production_runtime

DISCLAIMER = (
    "SYNTHETIC / DEVELOPMENT DATA — NOT ACTUAL GCT OPERATIONAL DATA. "
    "Generated inventory data is allowed only for development/demo use and is "
    "blocked from serving predictions in production."
)


def data_source() -> str:
    """Return the requested inventory training-data source."""
    default = "genuine" if is_production_runtime() else "sample"
    return os.environ.get("INVENTORY_DATA_SOURCE", default).strip().lower()


def is_genuine() -> bool:
    """Return True if data source is configured for genuine data."""
    return data_source() == "genuine"


def data_thresholds() -> Dict[str, int]:
    """Minimum history required before Model #4 is considered usable.

    Genuine training is fleet-level spare-part demand rather than per-bus
    demand because the authoritative warehouse ledger records the issued part,
    quantity and reference but does not reliably identify a bus on every row.
    The genuine gate therefore measures parts, weeks and real Stock Out events.
    """
    if data_source() == "genuine":
        return {
            "min_rows": int(os.environ.get("INVENTORY_MIN_ROWS", "260")),
            "min_buses": 1,
            "min_parts": int(os.environ.get("INVENTORY_MIN_PARTS", "20")),
            "min_weeks": int(os.environ.get("INVENTORY_MIN_WEEKS", "13")),
            "min_stock_out_events": int(
                os.environ.get("INVENTORY_MIN_STOCK_OUT_EVENTS", "500")
            ),
        }

    return {
        "min_rows": int(os.environ.get("INVENTORY_MIN_ROWS", "1000")),
        "min_buses": int(os.environ.get("INVENTORY_MIN_BUSES", "3")),
        "min_parts": int(os.environ.get("INVENTORY_MIN_PARTS", "10")),
        "min_weeks": int(os.environ.get("INVENTORY_MIN_WEEKS", "13")),
        "min_stock_out_events": 0,
    }


def rf_convention() -> Dict[str, object]:
    """Project-wide Random Forest convention."""
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
    """Canonical paths for inventory training datasets."""
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "inventory"
    prefix = "genuine" if data_source() == "genuine" else "sample"
    return {
        "dir": data_dir,
        "csv": data_dir / f"{prefix}_inventory_training.csv",
        "features_csv": data_dir / f"{prefix}_inventory_training_features.csv",
    }


def forecast_test_fraction() -> float:
    """Fraction of chronologically latest weeks reserved for testing."""
    return float(os.environ.get("INVENTORY_TEST_FRACTION", "0.2"))


def forecast_horizon() -> str:
    return "next_week"
