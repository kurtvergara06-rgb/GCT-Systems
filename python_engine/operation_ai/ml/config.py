"""Configuration for the Operation AI ML subsystem.

Reads database credentials and runtime settings from the python_engine/.env
file (or process environment), so the ML training pipeline can query the
Laravel MySQL database directly without depending on Laravel itself.
"""

import os
from pathlib import Path
from typing import Dict


def _load_env_file(path: Path) -> Dict[str, str]:
    """Parse a simple KEY=VALUE .env file into a dict."""
    env: Dict[str, str] = {}
    if not path.exists():
        return env
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip()
        # Strip surrounding quotes
        if len(value) >= 2 and value[0] == value[-1] == '"':
            value = value[1:-1]
        env[key] = value
    return env


def _resolve_env() -> Dict[str, str]:
    """Merge python_engine/.env (lowest priority) with process env (highest)."""
    env_file = Path(__file__).resolve().parents[2] / ".env"
    merged = _load_env_file(env_file)
    for key, value in os.environ.items():
        if key in {"DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"}:
            merged[key] = value
    return merged


_ENV = _resolve_env()


def db_config() -> Dict[str, str]:
    """Return MySQL connection settings for the Laravel database."""
    return {
        "host": _ENV.get("DB_HOST", "127.0.0.1"),
        "port": int(_ENV.get("DB_PORT", "3306")),
        "database": _ENV.get("DB_DATABASE", "gct_system"),
        "user": _ENV.get("DB_USERNAME", "root"),
        "password": _ENV.get("DB_PASSWORD", "root123"),
    }


def data_thresholds() -> Dict[str, int]:
    """Minimum historical data needed before ML ranking is considered usable."""
    return {
        # Minimum distinct buses (or trips) with real GPS performance data.
        "min_bus_records": int(_ENV.get("OPERATION_AI_MIN_BUS_RECORDS", "30")),
        # Minimum distinct drivers with real attendance history.
        "min_driver_records": int(_ENV.get("OPERATION_AI_MIN_DRIVER_RECORDS", "5")),
        # Minimum distinct buses present in the training set.
        "min_bus_count": int(_ENV.get("OPERATION_AI_MIN_BUS_COUNT", "5")),
        # Minimum distinct drivers present in the training set. A RandomForest
        # cannot be trusted with fewer than this many per-driver rows.
        "min_driver_count": int(_ENV.get("OPERATION_AI_MIN_DRIVER_COUNT", "10")),
    }


def model_paths() -> Dict[str, Path]:
    """Return the canonical paths for the saved ML artifacts."""
    models_dir = Path(__file__).resolve().parents[1] / "models"
    return {
        "dir": models_dir,
        "bus_model": models_dir / "scheduling_bus_rf.pkl",
        "driver_model": models_dir / "scheduling_driver_rf.pkl",
        "bus_report": models_dir / "scheduling_bus_rf_report.txt",
        "driver_report": models_dir / "scheduling_driver_rf_report.txt",
        "bus_features": models_dir / "scheduling_bus_features.json",
        "driver_features": models_dir / "scheduling_driver_features.json",
        "state": models_dir / "scheduling_ml_state.json",
    }


def training_data_paths() -> Dict[str, Path]:
    """Return canonical paths for generated training datasets."""
    data_dir = Path(__file__).resolve().parents[3] / "training_data"
    return {
        "dir": data_dir,
        "bus_csv": data_dir / "scheduling_bus_training.csv",
        "driver_csv": data_dir / "scheduling_driver_training.csv",
    }
