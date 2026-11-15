"""Configuration for the Model #3 Delay Prediction subsystem.

Three explicit, isolated data sources:

    * ``sample``  - deterministic generated development dataset under
                    ``training_data/delay``.
    * ``demo``    - client-demo records that are visible in the Laravel
                    frontend and exported with ``php artisan delay:export-demo``.
                    These records are synthetic and never production data.
    * ``genuine`` - production mode trained exclusively on genuine matched
                    DDR/schedule history. No synthetic fallback is allowed.

Production runtime defaults to ``genuine``. Local/development runtime defaults
to ``sample`` unless ``DELAY_DATA_SOURCE=demo`` is selected explicitly.
"""

import os
from pathlib import Path
from typing import Dict

from ml_runtime_policy import is_production_runtime

SAMPLE_DISCLAIMER = (
    "SYNTHETIC / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA. "
    "This Model #3 implementation is a development prototype trained on "
    "generated data and is blocked from serving predictions in production."
)

DEMO_DISCLAIMER = (
    "DEMO / SYNTHETIC CLIENT-PRESENTATION DATA - NOT ACTUAL GCT OPERATIONAL "
    "HISTORY. These records are visible in the normal GCT frontend so the "
    "client can demonstrate the complete workflow, but the model remains a "
    "development/demo model and is blocked from production serving."
)

GENUINE_DISCLAIMER = (
    "GENUINE GCT OPERATIONAL DATA - trained on real matched Daily Driver "
    "Reports against real trip schedules/assignments. This model is eligible "
    "for production only after its genuine data-sufficiency gate passes."
)


def disclaimers() -> Dict[str, str]:
    return {
        "sample": SAMPLE_DISCLAIMER,
        "demo": DEMO_DISCLAIMER,
        "genuine": GENUINE_DISCLAIMER,
    }


def data_source() -> str:
    """Return ``sample``, ``demo`` or ``genuine`` for the active pipeline."""
    default = "genuine" if is_production_runtime() else "sample"
    value = os.environ.get("DELAY_DATA_SOURCE", default).strip().lower()
    if value not in {"sample", "demo", "genuine"}:
        raise ValueError(
            "DELAY_DATA_SOURCE must be one of: sample, demo, genuine "
            f"(received {value!r})."
        )
    return value


def is_genuine() -> bool:
    return data_source() == "genuine"


def is_demo() -> bool:
    return data_source() == "demo"


def demo_trip_prefixes() -> tuple[str, ...]:
    """Trip-code prefixes excluded from genuine training and selected for demo."""
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
    """Artifact paths; demo artifacts are isolated from the canonical model."""
    models_dir = Path(__file__).resolve().parent / "models"
    prefix = "demo_" if data_source() == "demo" else ""
    return {
        "dir": models_dir,
        "model": models_dir / f"{prefix}delay_arrival_rf.pkl",
        "report": models_dir / f"{prefix}delay_arrival_report.txt",
        "features": models_dir / f"{prefix}delay_arrival_features.json",
        "state": models_dir / f"{prefix}delay_arrival_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Canonical paths for the active delay training dataset."""
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "delay"
    prefix = data_source()
    return {
        "dir": data_dir,
        "csv": data_dir / f"{prefix}_delay_training.csv",
        "features_csv": data_dir / f"{prefix}_delay_training_features.csv",
    }


def genuine_csv_path() -> Path:
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "delay"
    return data_dir / "genuine_delay_training.csv"


def demo_csv_path() -> Path:
    data_dir = Path(__file__).resolve().parents[2] / "training_data" / "delay"
    return data_dir / "demo_delay_training.csv"


def forecast_test_fraction() -> float:
    """Fraction of chronologically latest dates reserved for the test set."""
    return float(os.environ.get("DELAY_TEST_FRACTION", "0.2"))
