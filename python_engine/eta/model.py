"""Random Forest training and evaluation for ETA / trip duration."""

from __future__ import annotations

import json
import logging
import math
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, Optional

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

from ml_runtime_policy import normalize_data_source
from ml_version_guard import get_model_metadata
from .config import data_thresholds, model_paths
from .training_data import ETA_FEATURE_COLUMNS, ETA_TARGET

logger = logging.getLogger(__name__)


@dataclass
class EtaModelResult:
    model: Optional[RandomForestRegressor] = None
    trained: bool = False
    quality_ready: bool = False
    message: str = ""
    data_source: str = "unknown"
    metrics: Dict[str, float] = field(default_factory=dict)
    feature_importances: Dict[str, float] = field(default_factory=dict)
    n_train: int = 0
    n_test: int = 0
    n_samples: int = 0
    distinct_routes: int = 0
    target_range: Dict[str, float] = field(default_factory=dict)


def _training_source(df: pd.DataFrame) -> str:
    if "data_origin" not in df.columns or df.empty:
        return "unknown"
    origins = {
        normalize_data_source(value)
        for value in df["data_origin"].dropna().astype(str).tolist()
    }
    if origins == {"genuine"}:
        return "genuine"
    if origins and origins.issubset({"synthetic"}):
        return "synthetic"
    return "unknown"


def train_eta_model(df: pd.DataFrame) -> EtaModelResult:
    """Train using a chronological holdout and evaluate against route baseline."""
    thresholds = data_thresholds()
    result = EtaModelResult(
        n_samples=int(len(df)),
        data_source=_training_source(df),
        distinct_routes=(
            int(df["route"].dropna().astype(str).str.strip().nunique())
            if "route" in df.columns
            else 0
        ),
    )

    missing = [c for c in ETA_FEATURE_COLUMNS + [ETA_TARGET] if c not in df.columns]
    if missing:
        result.message = f"Training data missing required columns: {missing}"
        return result

    if len(df) < thresholds["min_records"]:
        result.message = (
            f"MODEL NOT READY: {len(df)} ETA records available; "
            f"at least {thresholds['min_records']} are required."
        )
        return result

    if result.distinct_routes < thresholds["min_routes"]:
        result.message = (
            f"MODEL NOT READY: {result.distinct_routes} distinct ETA routes available; "
            f"at least {thresholds['min_routes']} are required."
        )
        return result

    required = ETA_FEATURE_COLUMNS + [ETA_TARGET]
    working = df.copy()
    working = working.dropna(subset=required).reset_index(drop=True)
    if len(working) < thresholds["min_records"]:
        result.n_samples = len(working)
        result.message = "MODEL NOT READY: insufficient complete ETA records after cleaning."
        return result

    # Evaluate the deployment scenario: older trips train the model and the
    # newest 20% are held out. Random splitting can leak future route patterns
    # into both sets and overstate real-world performance.
    if "beginning_at" not in working.columns:
        result.message = "MODEL NOT READY: beginning_at is required for chronological validation."
        return result

    working["_split_at"] = pd.to_datetime(working["beginning_at"], errors="coerce")
    working = working.dropna(subset=["_split_at"]).sort_values("_split_at").reset_index(drop=True)
    if len(working) < thresholds["min_records"]:
        result.n_samples = len(working)
        result.message = "MODEL NOT READY: insufficient timestamped ETA records for validation."
        return result

    split_index = max(1, min(len(working) - 1, int(math.floor(len(working) * 0.8))))
    train = working.iloc[:split_index]
    test = working.iloc[split_index:]

    X_train = train[ETA_FEATURE_COLUMNS]
    y_train = train[ETA_TARGET].astype(float)
    X_test = test[ETA_FEATURE_COLUMNS]
    y_test = test[ETA_TARGET].astype(float)

    model = RandomForestRegressor(
        n_estimators=200,
        max_depth=None,
        min_samples_leaf=2,
        max_features="sqrt",
        random_state=42,
        n_jobs=-1,
    )
    model.fit(X_train, y_train)

    pred_train = model.predict(X_train)
    pred_test = model.predict(X_test)
    mae = float(mean_absolute_error(y_test, pred_test))
    rmse = float(np.sqrt(mean_squared_error(y_test, pred_test)))
    r2 = float(r2_score(y_test, pred_test)) if len(y_test) >= 2 else float("nan")

    baseline_values = pd.to_numeric(
        X_test["route_estimated_time_minutes"], errors="coerce"
    )
    baseline_mask = baseline_values.notna() & (baseline_values > 0)
    baseline_mae = float("nan")
    if bool(baseline_mask.any()):
        baseline_mae = float(
            mean_absolute_error(y_test[baseline_mask], baseline_values[baseline_mask])
        )

    improvement = float("nan")
    if np.isfinite(baseline_mae) and baseline_mae > 0:
        improvement = float(((baseline_mae - mae) / baseline_mae) * 100.0)

    result.model = model
    result.trained = True
    result.n_train = len(X_train)
    result.n_test = len(X_test)
    result.n_samples = len(working)
    result.metrics = {
        "mae": mae,
        "rmse": rmse,
        "r2": r2,
        "train_mae": float(mean_absolute_error(y_train, pred_train)),
        "test_mae": mae,
        "operator_baseline_mae": baseline_mae,
        "mae_improvement_percent": improvement,
    }
    result.feature_importances = {
        col: float(imp) for col, imp in zip(ETA_FEATURE_COLUMNS, model.feature_importances_)
    }
    y_all = working[ETA_TARGET].astype(float)
    result.target_range = {
        "min": float(y_all.min()),
        "max": float(y_all.max()),
        "mean": float(y_all.mean()),
    }

    metrics_finite = all(np.isfinite(v) for v in (mae, rmse, r2))
    baseline_beaten = np.isfinite(baseline_mae) and mae < baseline_mae
    result.quality_ready = bool(metrics_finite and baseline_beaten)
    result.message = (
        "ETA_ML_QUALITY_READY"
        if result.quality_ready
        else "MODEL NOT READY: ETA model has not demonstrated improvement over the operator route baseline."
    )
    return result


def save_eta_model(
    result: EtaModelResult,
    paths: Optional[Dict[str, Path]] = None,
    route_metadata: Optional[Dict[str, Dict[str, float]]] = None,
    route_encodings: Optional[Dict[str, int]] = None,
    bus_encodings: Optional[Dict[str, int]] = None,
) -> None:
    """Persist model and schema; remove stale model if this run did not train."""
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)

    if result.trained and result.model is not None:
        joblib.dump(result.model, paths["model"])
    elif paths["model"].exists():
        paths["model"].unlink()

    features_payload = {
        "features": ETA_FEATURE_COLUMNS,
        "target": ETA_TARGET,
        "route_metadata": route_metadata or {},
        "route_encodings": route_encodings or {},
        "bus_encodings": bus_encodings or {},
    }
    paths["features"].write_text(json.dumps(features_payload, indent=2), encoding="utf-8")

    lines = [
        "ETA / Trip Duration model report",
        "=" * 40,
        f"Training source:    {result.data_source}",
        "Split strategy:     chronological 80/20 holdout",
        f"Samples available:  {result.n_samples}",
        f"Distinct routes:    {result.distinct_routes}",
        f"Training rows:      {result.n_train}",
        f"Test rows:          {result.n_test}",
    ]
    if result.trained:
        lines += [
            "",
            "Metrics (newest held-out trips):",
            f"  MAE : {result.metrics['mae']:.2f} minutes",
            f"  RMSE: {result.metrics['rmse']:.2f} minutes",
            f"  R2  : {result.metrics['r2']:.4f}",
            f"  Operator baseline MAE: {result.metrics['operator_baseline_mae']:.2f} minutes",
            f"  MAE improvement: {result.metrics['mae_improvement_percent']:.2f}%",
            f"Quality ready:     {'yes' if result.quality_ready else 'no'}",
        ]
    else:
        lines += ["", result.message]
    paths["report"].write_text("\n".join(lines) + "\n", encoding="utf-8")


def save_state(result: EtaModelResult, paths: Optional[Dict[str, Path]] = None) -> None:
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    metadata = get_model_metadata(
        model_name="eta_duration_rf",
        training_source=result.data_source,
        model_version="1.1.0",
        feature_schema_version="1.1",
    )
    state = {
        **metadata,
        "model_ready": result.trained,
        "quality_ready": result.quality_ready,
        "sample_count": result.n_samples,
        "distinct_routes": result.distinct_routes,
        "split_strategy": "chronological_80_20",
        "metrics": result.metrics,
        "target_range": result.target_range,
        "message": (
            "ETA_ML_READY"
            if result.trained and result.quality_ready and result.data_source == "genuine"
            else "ETA_ML_READY_DEVELOPMENT_ONLY"
            if result.trained and result.data_source != "genuine"
            else "ETA_ML_NOT_READY"
        ),
    }
    paths["state"].write_text(json.dumps(state, indent=2, allow_nan=False), encoding="utf-8")
