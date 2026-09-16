"""Configuration for the Fuel Consumption ML subsystem.

Mirrors the ETA config module and reuses the database credential resolution
from operation_ai.ml.config so the training pipeline can query the Laravel
MySQL database read-only, exactly like the other ML subsystems.
"""

import os
from pathlib import Path
from typing import Dict

from operation_ai.ml.config import db_config  # noqa: F401  (reused as-is)


def data_thresholds() -> Dict[str, int]:
    """Minimum historical data before the fuel model is considered usable."""
    return {
        # Minimum labeled fuel reports (rows where real fuel consumed is known).
        "min_records": int(os.environ.get("FUEL_MIN_RECORDS", "50")),
        # Minimum distinct routes present in the training set.
        "min_routes": int(os.environ.get("FUEL_MIN_ROUTES", "3")),
    }


def model_paths() -> Dict[str, Path]:
    """Canonical paths for the saved fuel model artifacts.

    These are separate from the Operation AI scheduling models so nothing in
    operation_ai/models/ is ever overwritten.
    """
    models_dir = Path(__file__).resolve().parent / "models"
    return {
        "dir": models_dir,
        "model": models_dir / "fuel_liters_rf.pkl",
        "report": models_dir / "fuel_liters_report.txt",
        "features": models_dir / "fuel_liters_features.json",
        "state": models_dir / "fuel_liters_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Canonical paths for the generated fuel training dataset."""
    data_dir = Path(__file__).resolve().parents[2] / "training_data"
    return {
        "dir": data_dir,
        "csv": data_dir / "fuel_consumption_training.csv",
    }