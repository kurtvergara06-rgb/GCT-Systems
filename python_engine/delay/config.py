"""Configuration for the Model #3 Delay Prediction subsystem.

Two explicit, isolated data sources:

    * ``sample``  (DEFAULT) - DEVELOPMENT / DEMONSTRATION mode. Trains on a
                  generated SAMPLE dataset under ``training_data/delay/``.
                  Never reads the Laravel MySQL database.
    * ``genuine`` - PRODUCTION-QUALITY mode. Trains exclusively on the genuine
                  DDR <-> schedule matched history exported by the Laravel
                  command ``php artisan delay:export-genuine`` (writes
                  ``training_data/delay/genuine_delay_training.csv``). If the
                  genuine export is missing or fails the readiness gate, the
                  pipeline FAILS clearly and NEVER silently falls back to the
                  sample dataset.

The configuration mirrors the existing ETA / Fuel / Inventory conventions:

    * data_source()        - 'sample' default; 'genuine' opt-in (explicit).
    * data_thresholds()    - minimum history before the model is considered usable.
    * rf_convention()      - project-wide Random Forest configuration.
    * model_paths()        - artifacts under delay/models/ (never shares with
                             operation_ai/models/).
    * training_data_paths()- data-source aware central training_data/delay/
                             folder (repo root).
    * forecast_test_fraction() - chronological held-out test fraction.
"""

import os
from pathlib import Path
from typing import Dict

SAMPLE_DISCLAIMER = (
    "SAMPLE / DEMONSTRATION DATA - NOT ACTUAL GCT OPERATIONAL DATA. "
    "This Model #3 implementation is a demonstration prototype trained on a "
    "separate sample dataset because the current GCT database does not contain "
    "sufficient genuine historical DDR/schedule records for delay-model "
    "training. The sample dataset is not presented as actual GCT operational "
    "data, and the model is NOT trained on genuine GCT historical delay records."
)

GENUINE_DISCLAIMER = (
    "GENUINE GCT OPERATIONAL DATA - trained on real matched Daily Driver "
    "Reports against real trip schedules/assignments. This is a production-"
    "quality model whose training dataset passed the Model #3 data-sufficiency "
    "gate (DELAY_MODEL_READINESS.md)."
)


def disclaimers() -> Dict[str, str]:
    return {
        "sample": SAMPLE_DISCLAIMER,
        "genuine": GENUINE_DISCLAIMER,
    }


def data_source() -> str:
    """Return the training-data source key ('sample' default, 'genuine' opt-in).

    ``sample`` trains on the deterministic generated sample CSV (development).
    ``genuine`` reads the Laravel-exported genuine matched DDR dataset and
    refuses to fall back to sample data under any condition.
    """
    return os.environ.get("DELAY_DATA_SOURCE", "sample").strip().lower()


def is_genuine() -> bool:
    return data_source() == "genuine"


def demo_trip_prefixes() -> tuple[str, ...]:
    """Trip-code prefixes treated as DEMO schedules and excluded from genuine
    training (the seeded demo schedules use codes like ``TRIP-001``)."""
    raw = os.environ.get(
        "DELAY_DEMO_TRIP_PREFIXES",
        "TRIP-",
    )
    return tuple(p.strip() for p in raw.split(",") if p.strip())


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
    2nd parent). Paths are data-source aware so genuine and sample artifacts
    never overwrite each other, and neither is stored under python_engine/.
    """
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
    """Fraction of the chronologically LATEST dates reserved for the test set."""
    return float(os.environ.get("DELAY_TEST_FRACTION", "0.2"))