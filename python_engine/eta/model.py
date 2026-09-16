"""Random Forest training + evaluation for the ETA / trip-duration model.

Trains a single RandomForestRegressor that predicts a trip's ACTUAL duration
in minutes from pre-trip features only. Evaluation emits MAE, RMSE, R^2 and
per-feature importances so reviewers can confirm the model learned real
signal and never saw the label as an input.

The hyper-parameters intentionally reuse the existing Operation AI scheduling
Random Forest configuration (see operation_ai/ml/model.py) so the project
keeps a single, defensible RF convention:

    n_estimators=200, max_depth=None, min_samples_leaf=2,
    max_features="sqrt", random_state=42, n_jobs=-1
"""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split

from .config import model_paths
from .training_data import ETA_FEATURE_COLUMNS, ETA_TARGET

logger = logging.getLogger(__name__)


@dataclass
class EtaModelResult:
    model: Optional[RandomForestRegressor] = None
    trained: bool = False
    message: str = ""
    metrics: Dict[str, float] = field(default_factory=dict)
    feature_importances: Dict[str, float] = field(default_factory=dict)
    n_train: int = 0
    n_test: int = 0
    n_samples: int = 0
    target_range: Dict[str, float] = field(default_factory=dict)
    route_metadata: Dict[str, Dict[str, float]] = field(default_factory=dict)


def train_eta_model(df: pd.DataFrame) -> EtaModelResult:
    result = EtaModelResult(n_samples=int(len(df)))

    if df.empty or len(df) < 5:
        result.message = (
            f"Only {len(df)} samples available. Insufficient for reliable "
            "training; model not produced."
        )
        logger.warning(result.message)
        return result

    missing = [c for c in ETA_FEATURE_COLUMNS + [ETA_TARGET] if c not in df.columns]
    if missing:
        result.message = f"Training data missing required columns: {missing}"
        logger.warning(result.message)
        return result

    data = df[ETA_FEATURE_COLUMNS + [ETA_TARGET]].copy().dropna()
    if len(data) < 5:
        result.message = (
            f"Only {len(data)} complete samples after cleaning. "
            "Insufficient for reliable training; model not produced."
        )
        logger.warning(result.message)
        return result

    X = data[ETA_FEATURE_COLUMNS].reset_index(drop=True)
    y = data[ETA_TARGET].astype(float).reset_index(drop=True)

    # Prevent data leakage: split BEFORE fitting so the label is never seen.
    try:
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
    except ValueError as exc:
        result.message = f"Failed to split data: {exc}"
        logger.warning(result.message)
        return result

    model = RandomForestRegressor(
        n_estimators=200,
        max_depth=None,
        min_samples_leaf=2,
        max_features="sqrt",
        random_state=42,
        n_jobs=-1,
    )

    model.fit(X_train, y_train)
    pred_test = model.predict(X_test)
    pred_train = model.predict(X_train)

    result.model = model
    result.trained = True
    result.n_train = len(X_train)
    result.n_test = len(X_test)
    result.n_samples = len(data)

    result.metrics = {
        "mae": float(mean_absolute_error(y_test, pred_test)),
        "rmse": float(np.sqrt(mean_squared_error(y_test, pred_test))),
        "r2": float(r2_score(y_test, pred_test)),
        "train_mae": float(mean_absolute_error(y_train, pred_train)),
        "test_mae": float(mean_absolute_error(y_test, pred_test)),
    }
    result.feature_importances = {
        col: float(imp) for col, imp in zip(X.columns, model.feature_importances_)
    }
    result.target_range = {
        "min": float(y.min()),
        "max": float(y.max()),
        "mean": float(y.mean()),
    }

    logger.info(
        "ETA model trained: n_train=%d, n_test=%d, R2=%.3f, MAE=%.3f min",
        result.n_train,
        result.n_test,
        result.metrics["r2"],
        result.metrics["mae"],
    )
    return result


def save_eta_model(
    result: EtaModelResult,
    paths: Optional[Dict[str, Path]] = None,
    route_metadata: Optional[Dict[str, Dict[str, float]]] = None,
    bus_encodings: Optional[Dict[str, int]] = None,
) -> None:
    """Persist the trained model + feature layout + metadata + a report.

    Writes only its own artifacts under eta/models/; existing models in
    operation_ai/models/ are never touched.
    """
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)

    if result.trained and result.model is not None:
        joblib.dump(result.model, paths["model"])

    features_payload = {
        "features": ETA_FEATURE_COLUMNS,
        "target": ETA_TARGET,
        "route_metadata": route_metadata or {},
        "bus_encodings": bus_encodings or {},
    }
    with open(paths["features"], "w", encoding="utf-8") as f:
        json.dump(features_payload, f, indent=2)

    lines = [
        "ETA / Trip Duration model report",
        "=" * 40,
        f"Feature target:     {ETA_TARGET} (minutes)",
        f"Feature inputs:     {', '.join(ETA_FEATURE_COLUMNS)}",
        f"Samples available:  {result.n_samples}",
        f"Training rows:      {result.n_train}",
        f"Test rows:          {result.n_test}",
    ]
    if result.trained:
        lines += [
            "",
            "Metrics (held-out test set):",
            f"  MAE : {result.metrics['mae']:.2f} minutes",
            f"  RMSE: {result.metrics['rmse']:.2f} minutes",
            f"  R2  : {result.metrics['r2']:.4f}",
            "",
            "Label range (training):",
            f"  min : {result.target_range['min']:.1f}",
            f"  max : {result.target_range['max']:.1f}",
            f"  mean: {result.target_range['mean']:.1f}",
            "",
            "Feature importances:",
        ]
        for name, imp in sorted(
            result.feature_importances.items(), key=lambda kv: -kv[1]
        ):
            lines.append(f"  {name:<34} {imp:.4f}")
        lines += [
            "",
            "Configuration (reused project-wide RF convention):",
            "  n_estimators=200, max_depth=None, min_samples_leaf=2,",
            "  max_features='sqrt', random_state=42, n_jobs=-1",
            "",
            "Note: the label is the REAL GPS trip duration (minutes). It is",
            "NEVER included as an input feature (no target leakage).",
        ]
    else:
        lines += ["", result.message]

    paths["report"].write_text("\n".join(lines) + "\n", encoding="utf-8")
    logger.info("Wrote ETA model report to %s", paths["report"])


def save_state(result: EtaModelResult, paths: Optional[Dict[str, Path]] = None) -> None:
    """Write a JSON state file describing ETA model readiness."""
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    state = {
        "model_ready": result.trained,
        "sample_count": result.n_samples,
        "metrics": result.metrics,
        "target_range": result.target_range,
        "message": "ETA_ML_READY" if result.trained else "ETA_ML_NOT_READY",
    }
    paths["state"].write_text(json.dumps(state, indent=2), encoding="utf-8")
    logger.info("Wrote ETA state to %s", paths["state"])