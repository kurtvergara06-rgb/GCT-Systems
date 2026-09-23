"""Configuration for the Model #3 Delay Prediction subsystem.

Two explicit, isolated data sources:

    * ``sample``  - DEVELOPMENT / DEMONSTRATION mode. Trains on a generated
                  sample dataset under ``training_data/delay/`` and never reads
                  the Laravel MySQL database.
    * ``genuine`` - PRODUCTION mode. Trains exclusively on the genuine DDR <->
                  schedule matched history exported by Laravel. If genuine data
                  is missing or fails readiness, training/prediction must fail
                  clearly and never silently fall back to sample data.

Production runtime defaults to ``genuine``. Local/development runtime defaults
to ``sample`` so generated data remains available for demonstrations and tests.
"""

import os
from pathlib import Path
from typing import Dict

from ml_runtime_policy import is_production_runtime

SAMPLE_DISCLAIMER = (
    "SYNTHETIC / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA. "
    "This Model #3 implementation is a development/demo prototype trained on "
    "generated data. It must never be presented as genuine GCT history and is "
    "blocked from serving predictions in production."
)

GENUINE_DISCLAIMER = (
    "GENUINE GCT OPERATIONAL DATA - trained on real matched Daily Driver "
    "Reports against real trip schedules/assignments. This model is eligible "
    "for production only after its genuine data-sufficiency gate passes."
)


def disclaimers() -> Dict[str, str]:
    return {
        "sample": SAMPLE_DISCLAIMER,
        "genuine": GENUINE_DISCLAIMER,
    }


def data_source() -> str:
    """Return the requested training-data source.

    Development defaults to ``sample``. Production defaults to ``genuine``.
    Even if ``sample`` is explicitly selected in production, the shared runtime
    policy blocks it from serving predictions.
    """
    default = "genuine" if is_production_runtime() else "sample"
    return os.environ.get("DELAY_DATA_SOURCE", default).strip().lower()


def is_genuine() -> bool:
    return data_source() == "genuine"


def demo_trip_prefixes() -> tuple[str, ...]:
    """Trip-code prefixes treated as DEMO schedules and excluded from genuine training."""
    raw = os.environ.get("DELAY_DEMO_TRIP_PREFIXES", "TRIP-")
    return tuple(p.strip() for p in raw.split(",") if p.strip())


def data_thresholds() -> Dict[str, int]:
    """Minimum data before the delay model is considered usable."""
    return {
        "min_records": int(os.environ.get("DELAY_MIN_RECORDS", "50")),
        "min_routes": int(os.environ.get("DELAY_MIN_ROUTES", "3")),
        "min_buses": int(os.environ.get("DELAY_MIN_BUSES", "5")),
        "min_drivers": int(os.environ.get("DELAY_MIN_DRIVERS", "5")),
        "min_weeks": int(os.environ.get("DELAY_MIN_WEEKS", "4")),
    }


def rf_convention() -> Dict[str, object]:
    """Project-wide Random Forest convention."""
    return {
        "n_estimators": int(os.environ.get("DELAY_N_ESTIMATORS", "200")),
        "max_depth": None,
        "min_samples_leaf": int(os.environ.get("DELAY_MIN_SAMPLES_LEAF", "2")),
        "max_features": "sqrt",
        "random_state": 42,
        "n_jobs": -1,
    }


def model_paths() -> Dict[str, Path]:
    """Canonical paths for the saved delay model artifacts."""
    models_dir = Path(__file__).resolve().parent / "models"
    return {
        "dir": models_dir,
        "model": models_dir / "delay_arrival_rf.pkl",
        "report": models_dir / "delay_arrival_report.txt",
        "features": models_dir / "delay_arrival_features.json",
        "state": models_dir / "delay_arrival_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Canonical paths for the delay training datasets."""
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "delay"
    prefix = "genuine" if is_genuine() else "sample"
    return {
        "dir": data_dir,
        "csv": data_dir / f"{prefix}_delay_training.csv",
        "features_csv": data_dir / f"{prefix}_delay_training_features.csv",
    }


def genuine_csv_path() -> Path:
    """Path of the Laravel-exported genuine matched DDR dataset."""
    return training_data_paths()["dir"] / "genuine_delay_training.csv"


def forecast_test_fraction() -> float:
    """Fraction of the chronologically latest dates reserved for the test set."""
    return float(os.environ.get("DELAY_TEST_FRACTION", "0.2"))
