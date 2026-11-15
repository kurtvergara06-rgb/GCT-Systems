"""Configuration for the Model #3 Delay Prediction subsystem.

DEVELOPMENT PROTOTYPE - SAMPLE / DEMONSTRATION DATA. The current GCT database
does not contain sufficient genuine historical DDR/schedule meets for
delay-model training, so this subsystem trains on a generated SAMPLE dataset
stored in the central training folder ``training_data/delay/`` (project root).
It NEVER reads from or writes to the Laravel MySQL database.

The configuration mirrors the existing ETA / Fuel / Inventory conventions:

    * data_source()      - 'sample' default; 'genuine' is refused (opt-in env).
    * data_thresholds()  - minimum history before the model is considered usable.
    * rf_convention()    - project-wide Random Forest configuration.
    * model_paths()      - artifacts under delay/models/ (never shares with
                           operation_ai/models/).
    * training_data_paths() - central training_data/delay/ folder (repo root).
    * forecast_test_fraction() - chronological held-out test fraction.
"""

import os
from pathlib import Path
from typing import Dict

DISCLAIMER = (
    "SAMPLE / DEMONSTRATION DATA - NOT ACTUAL GCT OPERATIONAL DATA. "
    "This Model #3 implementation is a demonstration prototype trained on a "
    "separate sample dataset because the current GCT database does not contain "
    "sufficient genuine historical DDR/schedule records for delay-model "
    "training. The sample dataset is not presented as actual GCT operational "
    "data, and the model is NOT trained on genuine GCT historical delay records."
)


def data_source() -> str:
    """Return the training-data source key ('sample' default, 'genuine' opt-in).

    Only 'sample' is supported by the current generation pipeline. The
    'genuine' key is recognized so a FUTURE switch is explicit and auditable;
    if requested before genuine DDR data exists, training refuses to proceed.
    """
    return os.environ.get("DELAY_DATA_SOURCE", "sample").strip().lower()


def data_thresholds() -> Dict[str, int]:
    """Minimum data before the delay model is considered usable."""
    return {
        # Minimum labeled DSR/schedule rows (rows where arrival delay is known).
        "min_records": int(os.environ.get("DELAY_MIN_RECORDS", "50")),
        # Minimum distinct routes present in the training set.
        "min_routes": int(os.environ.get("DELAY_MIN_ROUTES", "3")),
        # Minimum distinct buses.
        "min_buses": int(os.environ.get("DELAY_MIN_BUSES", "5")),
        # Minimum distinct drivers.
        "min_drivers": int(os.environ.get("DELAY_MIN_DRIVERS", "5")),
        # Minimum distinct weeks of historical coverage.
        "min_weeks": int(os.environ.get("DELAY_MIN_WEEKS", "4")),
    }


def rf_convention() -> Dict[str, object]:
    """Project-wide Random Forest convention (reused by ETA / Fuel / Inventory)."""
    return {
        "n_estimators": int(os.environ.get("DELAY_N_ESTIMATORS", "200")),
        "max_depth": None,
        "min_samples_leaf": int(os.environ.get("DELAY_MIN_SAMPLES_LEAF", "2")),
        "max_features": "sqrt",
        "random_state": 42,
        "n_jobs": -1,
    }


def model_paths() -> Dict[str, Path]:
    """Canonical paths for the saved delay model artifacts.

    Files live under delay/models/ so nothing in operation_ai/models/ is
    ever overwritten.
    """
    models_dir = Path(__file__).resolve().parent / "models"
    return {
        "dir": models_dir,
        "model": models_dir / "delay_arrival_rf.pkl",
        "report": models_dir / "delay_arrival_report.txt",
        "features": models_dir / "delay_arrival_features.json",
        "state": models_dir / "delay_arrival_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Canonical paths for the delay training datasets (central folder).

    Follows the ETA / Fuel / Inventory convention: the central ``training_data``
    directory lives at the repository root (python_engine/delay/config.py's
    2nd parent). The sample CSVs are deliberately NOT stored under
    python_engine/.
    """
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "delay"
    return {
        "dir": data_dir,
        "csv": data_dir / "sample_delay_training.csv",
        "features_csv": data_dir / "sample_delay_training_features.csv",
    }


def forecast_test_fraction() -> float:
    """Fraction of the chronologically LATEST dates reserved for the test set."""
    return float(os.environ.get("DELAY_TEST_FRACTION", "0.2"))