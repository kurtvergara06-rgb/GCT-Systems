"""Random Forest training + evaluation for the delay-prediction model.

DEVELOPMENT PROTOTYPE - SAMPLE / DEMONSTRATION DATA.

Trains a single RandomForestRegressor that predicts a scheduled trip's
``arrival_delay_minutes`` (the target) from the leakage-safe feature matrix
produced by ``training_data.build_dataset``.

Validation is STRICTLY chronological: earlier dates train, the most recent
dates are held out. No random shuffling is applied (time-series discipline).

The hyper-parameters intentionally reuse the project-wide RF convention:

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

from .config import disclaimers, forecast_test_fraction, model_paths, rf_convention
from .training_data import (
    DLY_FEATURE_COLUMNS,
    DLY_TARGET,
    chronological_split,
)

logger = logging.getLogger(__name__)

# Business-rule risk bands (separate non-ML layer on top of the prediction).
RISK_THRESHOLDS: List[Dict[str, object]] = [
    {"max_minutes": 5, "label": "On Time"},
    {"max_minutes": 10, "label": "Minor Delay"},
    {"max_minutes": 20, "label": "Moderate Delay"},
    {"label": "High Delay"},  # 21+ minutes
]


@dataclass
class DelayModelResult:
    model: Optional[RandomForestRegressor] = None
    trained: bool = False
    message: str = ""
    source: str = "sample"
    metrics: Dict[str, float] = field(default_factory=dict)
    feature_importances: Dict[str, float] = field(default_factory=dict)
    n_train: int = 0
    n_test: int = 0
    n_samples: int = 0
    target_range: Dict[str, float] = field(default_factory=dict)
    periods: Dict[str, str] = field(default_factory=dict)


def train_delay_model(df: pd.DataFrame, source: str = "sample") -> DelayModelResult:
    """Train on the wide feature dataset with a chronological train/test split."""
    result = DelayModelResult(n_samples=int(len(df)), source=source)

    if df.empty or len(df) < 20:
        result.message = (
            f"Only {len(df)} observations available. Insufficient for reliable "
            "training; model not produced."
        )
        logger.warning(result.message)
        return result

    needed = DLY_FEATURE_COLUMNS + [DLY_TARGET, "trip_date"]
    missing = [c for c in needed if c not in df.columns]
    if missing:
        result.message = f"Training data missing required columns: {missing}"
        logger.warning(result.message)
        return result

    train, test, periods = chronological_split(df, forecast_test_fraction())
    result.periods = periods

    cols = DLY_FEATURE_COLUMNS + [DLY_TARGET]
    train = train[cols].dropna().reset_index(drop=True)
    test = test[cols].dropna().reset_index(drop=True)

    if len(train) < 20 or len(test) < 1:
        result.message = (
            f"Insufficient split sizes (train={len(train)}, test={len(test)}). "
            "Model not produced."
        )
        logger.warning(result.message)
        return result

    result.n_train = int(len(train))
    result.n_test = int(len(test))

    X_train = train[DLY_FEATURE_COLUMNS].reset_index(drop=True)
    y_train = train[DLY_TARGET].astype(float).reset_index(drop=True)
    X_test = test[DLY_FEATURE_COLUMNS].reset_index(drop=True)
    y_test = test[DLY_TARGET].astype(float).reset_index(drop=True)

    params = rf_convention()
    model = RandomForestRegressor(
        n_estimators=int(params["n_estimators"]),
        max_depth=params["max_depth"],
        min_samples_leaf=int(params["min_samples_leaf"]),
        max_features=params["max_features"],
        random_state=int(params["random_state"]),
        n_jobs=int(params["n_jobs"]),
    )
    model.fit(X_train, y_train)

    pred_test = model.predict(X_test)
    pred_train = model.predict(X_train)

    result.model = model
    result.trained = True

    result.metrics = {
        "mae": float(mean_absolute_error(y_test, pred_test)),
        "rmse": float(float(np.sqrt(mean_squared_error(y_test, pred_test)))),
        "r2": float(r2_score(y_test, pred_test)),
        "train_mae": float(mean_absolute_error(y_train, pred_train)),
    }
    result.feature_importances = {
        col: float(imp) for col, imp in zip(X_train.columns, model.feature_importances_)
    }
    result.target_range = {
        "min": float(y_train.min()),
        "max": float(y_train.max()),
        "mean": float(y_train.mean()),
    }

    logger.info(
        "Delay model trained (source=%s): n_train=%d, n_test=%d, "
        "R2=%.3f, MAE=%.3f",
        source,
        result.n_train,
        result.n_test,
        result.metrics["r2"],
        result.metrics["mae"],
    )
    return result


def save_delay_model(
    result: DelayModelResult,
    paths: Optional[Dict[str, Path]] = None,
    encoders: Optional[Dict[str, Dict[str, int]]] = None,
    route_metadata: Optional[Dict[str, Dict[str, float]]] = None,
    driver_metadata: Optional[Dict[str, Dict[str, float]]] = None,
    bus_metadata: Optional[Dict[str, Dict[str, float]]] = None,
) -> None:
    """Persist the trained model + feature layout + metadata + report.

    Writes only its own artifacts under delay/models/; models in
    operation_ai/models/ are never touched.
    """
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)

    if result.trained and result.model is not None:
        joblib.dump(result.model, paths["model"])

    disclaimer = disclaimers().get(result.source, disclaimers()["sample"])
    features_payload = {
        "features": DLY_FEATURE_COLUMNS,
        "target": DLY_TARGET,
        "source": result.source,
        "encoders": encoders or {},
        "route_metadata": route_metadata or {},
        "driver_metadata": driver_metadata or {},
        "bus_metadata": bus_metadata or {},
        "target_range": result.target_range,
        "periods": result.periods,
        "risk_thresholds": RISK_THRESHOLDS,
        "disclaimer": disclaimer,
    }
    with open(paths["features"], "w", encoding="utf-8") as f:
        json.dump(features_payload, f, indent=2)

    source_label = "GENUINE GCT OPERATIONAL DATA" if result.source == "genuine" \
        else "SAMPLE / DEMONSTRATION DATA"
    lines = [
        "Delay-prediction model report (Model #3)",
        "=" * 68,
        f"DATA SOURCE: {source_label}",
        f"Data source:          {result.source}",
        f"Prediction target:    {DLY_TARGET} (minutes)",
        f"Feature inputs:       {', '.join(DLY_FEATURE_COLUMNS)}",
        f"Samples available:    {result.n_samples}",
        f"Training rows:        {result.n_train}",
        f"Test rows:            {result.n_test}",
        f"Training period:      {result.periods.get('train_start', '')} .. {result.periods.get('train_end', '')}",
        f"Test period:          {result.periods.get('test_start', '')} .. {result.periods.get('test_end', '')}",
    ]
    if result.trained:
        lines += [
            "",
            "Metrics (chronological held-out test set):",
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
            lines.append(f"  {name:<36} {imp:.4f}")
        lines += [
            "",
            "Risk threshold layer (business rule, not ML):",
            "  0-5  minutes  = On Time",
            "  6-10  minutes = Minor Delay",
            "  11-20 minutes = Moderate Delay",
            "  21+   minutes = High Delay",
            "",
            "Configuration (project-wide RF convention):",
            "  n_estimators=200, max_depth=None, min_samples_leaf=2,",
            "  max_features='sqrt', random_state=42, n_jobs=-1",
            "",
            "Leakage controls:",
            "  - target (arrival_delay_minutes) is never an input feature",
            "  - departure_delay_minutes is never an input feature",
            "  - actual departure/arrival times and actual duration are excluded",
            "  - route/driver 'historical' features use ONLY strictly prior trips",
            "  - train/test split is chronological on the date axis (no shuffle)",
        ]
    else:
        lines += ["", result.message]

    paths["report"].write_text("\n".join(lines) + "\n", encoding="utf-8")
    logger.info("Wrote delay model report to %s", paths["report"])


def save_state(result: DelayModelResult, paths: Optional[Dict[str, Path]] = None) -> None:
    """Write a JSON state file describing delay model readiness."""
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    state = {
        "model_ready": result.trained,
        "message": (
            "DELAY_ML_READY (GENUINE DATA)" if (result.trained and result.source == "genuine")
            else "DELAY_ML_READY (SAMPLE/DEVELOPMENT)" if result.trained
            else "DELAY_ML_NOT_READY"
        ),
        "source": result.source,
        "disclaimer": disclaimers().get(result.source, disclaimers()["sample"]),
        "sample_count": result.n_samples,
        "n_train": result.n_train,
        "n_test": result.n_test,
        "metrics": result.metrics,
        "target_range": result.target_range,
        "periods": result.periods,
        "risk_thresholds": RISK_THRESHOLDS,
    }
    paths["state"].write_text(json.dumps(state, indent=2), encoding="utf-8")
    logger.info("Wrote delay state to %s", paths["state"])