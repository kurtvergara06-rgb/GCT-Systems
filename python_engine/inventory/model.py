"""Random Forest training + evaluation for inventory demand forecasting.

Trains a single RandomForestRegressor that predicts the weekly spare-part
demand ``quantity_issued`` (the target) per (bus, part) from the leakage-safe
feature matrix produced by ``training_data.build_dataset``.

Validation is STRICTLY chronological: earlier weeks train, the most recent
weeks are held out. No random shuffling is applied (time-series discipline).

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

from .config import forecast_test_fraction, model_paths, rf_convention
from .training_data import (
    INVENTORY_FEATURE_COLUMNS,
    INVENTORY_TARGET,
    chronological_split,
)

logger = logging.getLogger(__name__)

# Identity columns kept in the split arrays for per-part reporting only
# (they are never model inputs; they stay out of INVENTORY_FEATURE_COLUMNS).
_IDENTITY_COLS = ["bus_id", "part_id"]


@dataclass
class InventoryModelResult:
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
    per_part_metrics: Dict[str, Dict[str, float]] = field(default_factory=dict)


def train_inventory_model(df: pd.DataFrame, source: str = "sample") -> InventoryModelResult:
    """Train on the wide feature dataset with a chronological train/test split."""
    result = InventoryModelResult(n_samples=int(len(df)), source=source)

    if df.empty or len(df) < 20:
        result.message = (
            f"Only {len(df)} observations available. Insufficient for reliable "
            "training; model not produced."
        )
        logger.warning(result.message)
        return result

    needed = INVENTORY_FEATURE_COLUMNS + [INVENTORY_TARGET, "date"]
    missing = [c for c in needed if c not in df.columns]
    if missing:
        result.message = f"Training data missing required columns: {missing}"
        logger.warning(result.message)
        return result

    train, test, periods = chronological_split(df, forecast_test_fraction())
    result.periods = periods

    cols = INVENTORY_FEATURE_COLUMNS + [INVENTORY_TARGET] + _IDENTITY_COLS
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

    X_train = train[INVENTORY_FEATURE_COLUMNS].reset_index(drop=True)
    y_train = train[INVENTORY_TARGET].astype(float).reset_index(drop=True)
    X_test = test[INVENTORY_FEATURE_COLUMNS].reset_index(drop=True)
    y_test = test[INVENTORY_TARGET].astype(float).reset_index(drop=True)

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

    # Per-part held-out metrics (only for parts with enough test rows).
    test_with_pred = test.copy()
    test_with_pred["predicted"] = pred_test
    test_with_pred["actual"] = y_test.to_numpy()
    test_with_pred["abs_error"] = (test_with_pred["predicted"] - test_with_pred["actual"]).abs()
    per_part: Dict[str, Dict[str, float]] = {}
    for part_id, group in test_with_pred.groupby("part_id", observed=True):
        if len(group) >= 5:
            per_part[str(part_id)] = {
                "n": int(len(group)),
                "mae": float(group["abs_error"].mean()),
                "predicted_mean": float(group["predicted"].mean()),
                "actual_mean": float(group["actual"].mean()),
            }
    result.per_part_metrics = per_part

    logger.info(
        "Inventory model trained (source=%s): n_train=%d, n_test=%d, "
        "R2=%.3f, MAE=%.3f",
        source,
        result.n_train,
        result.n_test,
        result.metrics["r2"],
        result.metrics["mae"],
    )
    return result


def save_inventory_model(
    result: InventoryModelResult,
    paths: Optional[Dict[str, Path]] = None,
    encoders: Optional[Dict[str, Dict[str, int]]] = None,
    part_metadata: Optional[Dict[str, Dict[str, object]]] = None,
    bus_metadata: Optional[Dict[str, Dict[str, float]]] = None,
) -> None:
    """Persist the trained model + feature layout + metadata + report."""
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)

    if result.trained and result.model is not None:
        joblib.dump(result.model, paths["model"])

    features_payload = {
        "features": INVENTORY_FEATURE_COLUMNS,
        "target": INVENTORY_TARGET,
        "forecast_horizon": "next_week",
        "source": result.source,
        "encoders": encoders or {},
        "part_metadata": part_metadata or {},
        "bus_metadata": bus_metadata or {},
        "target_range": result.target_range,
        "periods": result.periods,
        "disclaimer": (
            "SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA"
        ),
    }
    with open(paths["features"], "w", encoding="utf-8") as f:
        json.dump(features_payload, f, indent=2)

    lines = [
        "Inventory demand-forecasting model report (Model #4 - development prototype)",
        "=" * 68,
        f"DISCLAIMER: SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA",
        f"Data source:          {result.source}",
        f"Forecast target:      {INVENTORY_TARGET} (weekly spare-part demand)",
        f"Forecast horizon:     next week",
        f"Feature inputs:       {', '.join(INVENTORY_FEATURE_COLUMNS)}",
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
            f"  MAE : {result.metrics['mae']:.3f} units/week",
            f"  RMSE: {result.metrics['rmse']:.3f} units/week",
            f"  R2  : {result.metrics['r2']:.4f}",
            "",
            "Label range (training):",
            f"  min : {result.target_range['min']:.2f}",
            f"  max : {result.target_range['max']:.2f}",
            f"  mean: {result.target_range['mean']:.2f}",
            "",
            "Feature importances:",
        ]
        for name, imp in sorted(
            result.feature_importances.items(), key=lambda kv: -kv[1]
        ):
            lines.append(f"  {name:<34} {imp:.4f}")
        lines += [
            "",
            "Per-part held-out MAE (parts with >= 5 test rows):",
        ]
        for part_id, meta in sorted(
            result.per_part_metrics.items(), key=lambda kv: -kv[1]["mae"]
        ):
            lines.append(
                f"  {part_id:<10} n={meta['n']:>3}  MAE={meta['mae']:.3f}  "
                f"pred_mean={meta['predicted_mean']:.2f}  "
                f"actual_mean={meta['actual_mean']:.2f}"
            )
        lines += [
            "",
            "Configuration (project-wide RF convention):",
            "  n_estimators=200, max_depth=None, min_samples_leaf=2,",
            "  max_features='sqrt', random_state=42, n_jobs=-1",
            "",
            "Leakage controls:",
            "  - target (quantity_issued) is never an input feature",
            "  - on_hand_after is rejected (computed with the target)",
            "  - current maintenance_type / breakdown_count are lagged by one week",
            "  - train/test split is chronological (no random shuffle)",
            "  - days_since_last_issue is recomputed from strictly prior demand",
        ]
    else:
        lines += ["", result.message]

    paths["report"].write_text("\n".join(lines) + "\n", encoding="utf-8")
    logger.info("Wrote inventory model report to %s", paths["report"])


def save_state(result: InventoryModelResult, paths: Optional[Dict[str, Path]] = None) -> None:
    """Write a JSON state file describing inventory model readiness."""
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    state = {
        "model_ready": result.trained,
        "message": (
            "INVENTORY_ML_READY (SAMPLE/DEVELOPMENT)" if result.trained else "INVENTORY_ML_NOT_READY"
        ),
        "source": result.source,
        "disclaimer": "SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA",
        "sample_count": result.n_samples,
        "n_train": result.n_train,
        "n_test": result.n_test,
        "metrics": result.metrics,
        "target_range": result.target_range,
        "periods": result.periods,
    }
    paths["state"].write_text(json.dumps(state, indent=2), encoding="utf-8")
    logger.info("Wrote inventory state to %s", paths["state"])