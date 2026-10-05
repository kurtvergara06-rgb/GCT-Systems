"""Candidate benchmarking and training for ETA / trip duration."""

from __future__ import annotations

import json
import logging
import math
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import HistGradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

from ml_runtime_policy import normalize_data_source
from ml_version_guard import get_model_metadata
from .config import data_thresholds, model_paths
from .training_data import ETA_FEATURE_COLUMNS, ETA_TARGET

logger = logging.getLogger(__name__)


@dataclass
class EtaModelResult:
    model: Optional[Any] = None
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
    selected_model_key: str = ""
    selected_model_name: str = ""
    selected_model_family: str = ""
    candidate_models: List[Dict[str, Any]] = field(default_factory=list)


ETA_MODEL_IDENTITIES = {
    "linear_regression": "eta_duration_linear_regression",
    "random_forest": "eta_duration_random_forest",
    "hist_gradient_boosting": "eta_duration_hist_gradient_boosting",
}


def _regression_metrics(actual: pd.Series, predicted: Any) -> Dict[str, float]:
    """Return one comparable metric contract for every ETA candidate."""
    return {
        "mae": float(mean_absolute_error(actual, predicted)),
        "rmse": float(np.sqrt(mean_squared_error(actual, predicted))),
        "r2": float(r2_score(actual, predicted)) if len(actual) >= 2 else float("nan"),
    }


def _candidate_row(
    key: str,
    name: str,
    family: str,
    metrics: Optional[Dict[str, float]] = None,
    status: str = "evaluated",
    reason: str = "",
) -> Dict[str, Any]:
    return {
        "key": key,
        "name": name,
        "family": family,
        "selected": False,
        "status": status,
        "reason": reason,
        "metrics": metrics or {},
    }


def _json_safe_candidates(candidates: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """Replace non-finite metric values before strict JSON serialization."""
    safe_candidates: List[Dict[str, Any]] = []
    for candidate in candidates:
        safe_candidate = {**candidate}
        metrics = candidate.get("metrics") or {}
        safe_candidate["metrics"] = {
            key: float(value) if value is not None and np.isfinite(value) else None
            for key, value in metrics.items()
        }
        safe_candidates.append(safe_candidate)
    return safe_candidates


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
    """Benchmark ETA candidates on one chronological holdout and save the winner."""
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

    baseline_values = pd.to_numeric(
        X_test["route_estimated_time_minutes"], errors="coerce"
    )
    baseline_mask = baseline_values.notna() & (baseline_values > 0)
    baseline_mae = float("nan")
    if bool(baseline_mask.any()):
        baseline_metrics = _regression_metrics(
            y_test[baseline_mask], baseline_values[baseline_mask]
        )
        baseline_mae = baseline_metrics["mae"]
        result.candidate_models.append(
            _candidate_row(
                "operator_baseline",
                "Operator Route Baseline",
                "Published route estimate",
                baseline_metrics,
            )
        )
    else:
        result.candidate_models.append(
            _candidate_row(
                "operator_baseline",
                "Operator Route Baseline",
                "Published route estimate",
                status="not_evaluated",
                reason="No positive route estimate exists in the held-out rows.",
            )
        )

    candidate_estimators = [
        (
            "linear_regression",
            "Linear Regression",
            "Parametric linear (OLS)",
            LinearRegression(),
        ),
        (
            "random_forest",
            "Random Forest",
            "Nonlinear ensemble (200 trees)",
            RandomForestRegressor(
                n_estimators=200,
                max_depth=None,
                min_samples_leaf=2,
                max_features="sqrt",
                random_state=42,
                n_jobs=-1,
            ),
        ),
        (
            "hist_gradient_boosting",
            "Histogram Gradient Boosting",
            "Boosted decision trees",
            HistGradientBoostingRegressor(
                max_iter=200,
                learning_rate=0.05,
                random_state=42,
            ),
        ),
    ]

    evaluated: List[Dict[str, Any]] = []
    for key, name, family, estimator in candidate_estimators:
        try:
            estimator.fit(X_train, y_train)
            pred_test = estimator.predict(X_test)
            metrics = _regression_metrics(y_test, pred_test)
            pred_train = estimator.predict(X_train)
            metrics["train_mae"] = float(mean_absolute_error(y_train, pred_train))
            metrics["test_mae"] = metrics["mae"]
            if bool(baseline_mask.any()):
                metrics["baseline_comparable_mae"] = float(
                    mean_absolute_error(y_test[baseline_mask], pred_test[baseline_mask])
                )

            row = _candidate_row(key, name, family, metrics)
            row["estimator"] = estimator
            evaluated.append(row)
        except Exception as exc:  # noqa: BLE001
            logger.exception("ETA candidate %s failed during evaluation", key)
            result.candidate_models.append(
                _candidate_row(key, name, family, status="failed", reason=str(exc))
            )

    if not evaluated:
        result.message = "MODEL NOT READY: all ETA candidate algorithms failed evaluation."
        return result

    selected = min(evaluated, key=lambda row: row["metrics"]["mae"])
    selected["selected"] = True
    model = selected.pop("estimator")
    for row in evaluated:
        row.pop("estimator", None)
        result.candidate_models.append(row)

    selected_metrics = selected["metrics"]
    mae = float(selected_metrics["mae"])
    rmse = float(selected_metrics["rmse"])
    r2 = float(selected_metrics["r2"])
    comparable_mae = float(selected_metrics.get("baseline_comparable_mae", float("nan")))

    improvement = float("nan")
    if np.isfinite(baseline_mae) and baseline_mae > 0 and np.isfinite(comparable_mae):
        improvement = float(((baseline_mae - comparable_mae) / baseline_mae) * 100.0)

    result.model = model
    result.trained = True
    result.n_train = len(X_train)
    result.n_test = len(X_test)
    result.n_samples = len(working)
    result.metrics = {
        "mae": mae,
        "rmse": rmse,
        "r2": r2,
        "train_mae": float(selected_metrics["train_mae"]),
        "test_mae": mae,
        "baseline_comparable_mae": comparable_mae,
        "operator_baseline_mae": baseline_mae,
        "mae_improvement_percent": improvement,
    }
    if hasattr(model, "feature_importances_"):
        result.feature_importances = {
            col: float(imp)
            for col, imp in zip(ETA_FEATURE_COLUMNS, model.feature_importances_)
        }
    result.selected_model_key = str(selected["key"])
    result.selected_model_name = str(selected["name"])
    result.selected_model_family = str(selected["family"])
    y_all = working[ETA_TARGET].astype(float)
    result.target_range = {
        "min": float(y_all.min()),
        "max": float(y_all.max()),
        "mean": float(y_all.mean()),
    }

    metrics_finite = all(np.isfinite(v) for v in (mae, rmse, r2))
    baseline_beaten = (
        np.isfinite(baseline_mae)
        and np.isfinite(comparable_mae)
        and comparable_mae < baseline_mae
    )
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
        f"Selected candidate: {result.selected_model_name or 'none'}",
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
            "",
            "Candidate comparison (same chronological holdout):",
        ]
        for candidate in result.candidate_models:
            metrics = candidate.get("metrics") or {}
            if candidate.get("status") != "evaluated":
                lines.append(
                    f"  {candidate['name']}: {candidate.get('status')} - {candidate.get('reason', '')}"
                )
                continue
            lines.append(
                "  "
                + candidate["name"]
                + (" [SELECTED]" if candidate.get("selected") else "")
                + f": MAE={metrics.get('mae', float('nan')):.2f}, "
                + f"RMSE={metrics.get('rmse', float('nan')):.2f}, "
                + f"R2={metrics.get('r2', float('nan')):.4f}"
            )
    else:
        lines += ["", result.message]
    paths["report"].write_text("\n".join(lines) + "\n", encoding="utf-8")


def save_state(result: EtaModelResult, paths: Optional[Dict[str, Path]] = None) -> None:
    paths = paths or model_paths()
    paths["dir"].mkdir(parents=True, exist_ok=True)
    metadata = get_model_metadata(
        model_name=ETA_MODEL_IDENTITIES.get(
            result.selected_model_key,
            "eta_duration_model_not_selected",
        ),
        training_source=result.data_source,
        model_version="1.2.0",
        feature_schema_version="1.1",
    )
    safe_metrics = {
        key: (float(value) if np.isfinite(value) else None)
        for key, value in result.metrics.items()
    }
    state = {
        **metadata,
        "model_ready": result.trained,
        "quality_ready": result.quality_ready,
        "sample_count": result.n_samples,
        "distinct_routes": result.distinct_routes,
        "split_strategy": "chronological_80_20",
        "selected_model": {
            "key": result.selected_model_key,
            "name": result.selected_model_name,
            "family": result.selected_model_family,
        },
        "candidate_models": _json_safe_candidates(result.candidate_models),
        "metrics": safe_metrics,
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
