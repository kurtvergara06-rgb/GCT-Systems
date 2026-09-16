"""Configuration for the ETA / Trip Duration ML subsystem.

Reuses the database credential resolution from operation_ai.ml.config so the
training pipeline can query the Laravel MySQL database read-only, exactly like
the existing scheduling models do.
"""

import os
from pathlib import Path
from typing import Dict

from operation_ai.ml.config import db_config  # noqa: F401  (reused as-is)


def data_thresholds() -> Dict[str, int]:
    """Minimum historical data before the ETA model is considered usable."""
    return {
        # Minimum labeled completed trips (rows where real duration is known).
        "min_records": int(os.environ.get("ETA_MIN_RECORDS", "50")),
        # Minimum distinct routes present in the training set.
        "min_routes": int(os.environ.get("ETA_MIN_ROUTES", "3")),
    }


def model_paths() -> Dict[str, Path]:
    """Canonical paths for the saved ETA model artifacts.

    These are separate from the Operation AI scheduling models so nothing in
    operation_ai/models/ is ever overwritten.
    """
    models_dir = Path(__file__).resolve().parent / "models"
    return {
        "dir": models_dir,
        "model": models_dir / "eta_duration_rf.pkl",
        "report": models_dir / "eta_duration_report.txt",
        "features": models_dir / "eta_duration_features.json",
        "state": models_dir / "eta_duration_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Canonical paths for the generated ETA training dataset."""
    data_dir = Path(__file__).resolve().parents[2] / "training_data"
    return {
        "dir": data_dir,
        "csv": data_dir / "eta_trip_duration_training.csv",
    }