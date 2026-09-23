"""Random Forest training and persistence for Inventory Model #4."""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, Optional

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

from .config import forecast_test_fraction, model_paths, rf_convention
from .training_data import INVENTORY_FEATURE_COLUMNS, INVENTORY_TARGET, chronological_split

logger = logging.getLogger(__name__)
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
    result = InventoryModelResult(n_samples=int(len(df)), source=source)
    if df.empty or len(df) < 20:
        result.message = (
            f"Only {len(df)} observations available. Insufficient for reliable training."
        )
        return result

    needed = INVENTORY_FEATURE_COLUMNS + [INVENTORY_TARGET, "date"]
    missing = [column for column in needed if column not in df.columns]
    if missing:
        result.message = f"Training data missing required columns: {missing}"
        return result

    train, test, periods = chronological_split(df, forecast_test_fraction())
    result.periods = periods
    cols = INVENTORY_FEATURE_COLUMNS + [INVENTORY_TARGET] + _IDENTITY_COLS
    train = train[cols].dropna().reset_index(drop=True)
    test = test[cols].dropna().reset_index(drop=True)

    if len(train) < 20 or len(test) < 1:
        result.message = (
            f"Insufficient split sizes (train={len(train)}, test={len(test)})."
        )
        return result

    result.n_train = int(len(train))
    result.n_test = int(len(test))
    X_train = train[INVENTORY_FEATURE_COLUMNS]
    y_train = train[INVENTORY_TARGET].astype(float)
    X_test = test[INVENTORY_FEATURE_COLUMNS]
    y_test = test[INVENTORY_TARGET].astype(float)

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
    result.message = "INVENTORY_ML_READY"
    result.metrics = {
        "mae": float(mean_absolute_error(y_test, pred_test)),
        "rmse": float(np.sqrt(mean_squared_error(y_test, pred_test))),
        "r2": float(r2_score(y_test, pred_test)),
        "train_mae": float(mean_absolute_error(y_train, pred_train)),
    }
    result.feature_importances = {
        column: float(importance)
        for column, importance in zip(X_train.columns, model.feature_importances_)
    }
    result.target_range = {
        "min": float(y_train.min()),
        "max": float(y_train.max()),
        "mean": float(y_train.mean()),
    }

    evaluated = test.copy()
    evaluated["predicted"] = pred_test
    evaluated["actual"] = y_test.to_numpy()
    evaluated["abs_error"] = (evaluated["predicted"] - evaluated["actual"]).abs()
    for part_id, group in evaluated.groupby("part_id", observed=True):
        if len(group) >= 5:
            result.per_part_metrics[str(part_id)] = {
                "n": int(len(group)),
                "mae": float(group["abs_error"].mean()),
                "predicted_mean": float(group["predicted"].mean()),
                "actual_mean": float(group["actual"].mean()),
            }

    logger.info(
        "Inventory model trained source=%s n_train=%d n_test=%d r2=%.3f mae=%.3f",
        source,
        result.n_train,
        result.n_test,
        result.metrics["r2"],
        result.metrics["mae"],
    )
    return result


def _source_labels(source: str) -> tuple[str, str]:
    if source == "genuine":
        return (
            "GENUINE GCT INVENTORY LEDGER",
            "GENUINE GCT OPERATIONAL DATA - source='app' stock movements only",
        )
    return (
        "SAMPLE / DEVELOPMENT",
        "SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA",
    )


def save_inventory_model(
    result: InventoryModelResult,
    paths: Optional[Dict[str, Path]] = None,
    encoders: Optional[Dict[str, Dict[str, int]]] = None,
    part_metadata: Optional[Dict[str, Dict[str, object]]] = None,
    bus_metadata: Optional[Dict[str, Dict[str, float]]] = None,
) -> None:
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    source_label, disclaimer = _source_labels(result.source)

    if result.trained and result.model is not None:
        joblib.dump(result.model, paths["model"])

    features_payload = {
        "features": INVENTORY_FEATURE_COLUMNS,
        "target": INVENTORY_TARGET,
        "forecast_horizon": "next_week",
        "source": result.source,
        "dataset_type": source_label,
        "encoders": encoders or {},
        "part_metadata": part_metadata or {},
        "bus_metadata": bus_metadata or {},
        "target_range": result.target_range,
        "periods": result.periods,
        "disclaimer": disclaimer,
    }
    paths["features"].write_text(
        json.dumps(features_payload, indent=2), encoding="utf-8"
    )

    lines = [
        "Inventory demand-forecasting model report (Model #4)",
        "=" * 56,
        f"Data source:          {result.source}",
        f"Dataset type:         {source_label}",
        f"Disclaimer:           {disclaimer}",
        f"Forecast target:      {INVENTORY_TARGET} (weekly spare-part demand)",
        "Forecast horizon:     next week",
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
            "Feature importances:",
        ]
        for name, importance in sorted(
            result.feature_importances.items(), key=lambda item: -item[1]
        ):
            lines.append(f"  {name:<34} {importance:.4f}")
        lines += [
            "",
            "Leakage controls:",
            "  - quantity_issued is target only, never an input",
            "  - current-week demand is excluded from lag/rolling features",
            "  - chronological train/test split; no random shuffling",
            "  - genuine mode uses only source='app' warehouse ledger rows",
            "  - genuine demand is fleet-level by part; no bus id is fabricated",
        ]
    else:
        lines += ["", result.message or "MODEL NOT READY"]

    paths["report"].write_text("\n".join(lines) + "\n", encoding="utf-8")


def save_state(result: InventoryModelResult, paths: Optional[Dict[str, Path]] = None) -> None:
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    source_label, disclaimer = _source_labels(result.source)
    ready_message = (
        "INVENTORY_ML_READY (GENUINE GCT DATA)"
        if result.trained and result.source == "genuine"
        else "INVENTORY_ML_READY (SAMPLE/DEVELOPMENT)"
        if result.trained
        else "INVENTORY_ML_NOT_READY"
    )
    state = {
        "model_ready": result.trained,
        "message": ready_message,
        "source": result.source,
        "dataset_type": source_label,
        "disclaimer": disclaimer,
        "sample_count": result.n_samples,
        "n_train": result.n_train,
        "n_test": result.n_test,
        "metrics": result.metrics,
        "target_range": result.target_range,
        "periods": result.periods,
    }
    paths["state"].write_text(json.dumps(state, indent=2), encoding="utf-8")
