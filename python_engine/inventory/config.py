"""Configuration for Inventory Model #4.

Sources:
- ``sample``: generated development CSV.
- ``demo``: frontend-visible synthetic warehouse movements (source='demo').
- ``genuine``: production GCT ledger rows (source='app').

Production defaults to genuine and never falls back to synthetic data.
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

DEMO_DISCLAIMER = (
    "DEMO / SYNTHETIC FRONTEND DATA — NOT ACTUAL GCT OPERATIONAL HISTORY. "
    "These records exist in the normal Warehouse/Purchase frontend for client "
    "presentation but remain development-only ML data."
)


def data_source() -> str:
    default = "genuine" if is_production_runtime() else "sample"
    value = os.environ.get("INVENTORY_DATA_SOURCE", default).strip().lower()
    if value not in {"sample", "demo", "genuine"}:
        raise ValueError(
            "INVENTORY_DATA_SOURCE must be one of: sample, demo, genuine "
            f"(received {value!r})."
        )
    return value


def is_genuine() -> bool:
    return data_source() == "genuine"


def is_demo() -> bool:
    return data_source() == "demo"


def data_thresholds() -> Dict[str, int]:
    """Minimum history required before Model #4 is considered usable."""
    if data_source() in {"genuine", "demo"}:
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
    return {
        "n_estimators": 200,
        "max_depth": None,
        "min_samples_leaf": 2,
        "max_features": "sqrt",
        "random_state": 42,
        "n_jobs": -1,
    }


def model_paths() -> Dict[str, Path]:
    """Demo artifacts are isolated from the canonical inventory artifact."""
    models_dir = Path(__file__).resolve().parent / "models"
    prefix = "demo_" if data_source() == "demo" else ""
    return {
        "dir": models_dir,
        "model": models_dir / f"{prefix}inventory_demand_rf.pkl",
        "report": models_dir / f"{prefix}inventory_demand_report.txt",
        "features": models_dir / f"{prefix}inventory_demand_features.json",
        "state": models_dir / f"{prefix}inventory_demand_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "inventory"
    prefix = data_source()
    return {
        "dir": data_dir,
        "csv": data_dir / f"{prefix}_inventory_training.csv",
        "features_csv": data_dir / f"{prefix}_inventory_training_features.csv",
    }


def forecast_test_fraction() -> float:
    return float(os.environ.get("INVENTORY_TEST_FRACTION", "0.2"))


def forecast_horizon() -> str:
    return "next_week"
