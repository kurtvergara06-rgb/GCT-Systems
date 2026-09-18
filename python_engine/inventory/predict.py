"""Live inventory demand-forecasting service.

Loads the saved Random Forest model plus its persistence metadata and
predicts the weekly spare-part demand (``quantity_issued`` in the part's
unit) for a (bus, part) combination. Inference is side-effect free: it never
queries the database and never writes anything.

The response separates three conceptual layers explicitly:
    1. ML forecast            -> predicted_quantity_issued
    2. Business-rule detection-> risk_status / binary risk flags
    3. Prescriptive hint      -> recommended_action / suggested_order_qty

The business rules (stock vs reorder level vs forecast) are plain, explainable
arithmetic clearly labelled as rules, not ML predictions. The model NEVER
creates purchase orders or makes procurement decisions.

DISCLAIMER: this is a development prototype trained on GENERATED SAMPLE data.
"""

from __future__ import annotations

import json
import logging
from dataclasses import asdict, dataclass, field
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional

import joblib
import numpy as np
import pandas as pd

from .config import model_paths
from .training_data import INVENTORY_FEATURE_COLUMNS

logger = logging.getLogger(__name__)

_paths = model_paths()

DISCLAIMER = (
    "SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA. The current "
    "model is a development/prototype model trained on generated sample data. "
    "It must not be presented as a model trained on actual GCT operational "
    "inventory data."
)


@dataclass
class InventoryReadiness:
    ml_ready: bool = False
    source: str = "not_trained"  # sample | genuine | not_trained
    reason: str = ""
    sample_count: int = 0
    message: str = ""
    model_path: Optional[Path] = None
    data_source: str = "sample"


@dataclass
class InventoryPrediction:
    predicted_quantity_issued: float
    unit: str = ""
    part_name: str = ""
    source: str = "ml"
    forecast_period: str = "next_week"
    data_source: str = "sample"
    is_production_model: bool = False
    model_type: str = "Sample / Development Model"
    feature_inputs: Dict[str, float] = field(default_factory=dict)
    assessment: Dict[str, object] = field(default_factory=dict)
    disclaimer: str = DISCLAIMER


_model = None
_metadata = None
_state = None
_part_by_name: Dict[str, str] = {}
_loaded = False


def _load_artifacts() -> None:
    global _model, _metadata, _state, _loaded, _part_by_name
    if _loaded:
        return
    _loaded = True
    _model = None
    _metadata = None
    _state = {}
    _part_by_name = {}
    try:
        if _paths["state"].exists():
            _state = json.loads(_paths["state"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read inventory state: %s", exc)
    try:
        if _paths["features"].exists():
            _metadata = json.loads(_paths["features"].read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to read inventory feature layout: %s", exc)
    try:
        if _paths["model"].exists():
            _model = joblib.load(_paths["model"])
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to load inventory model %s: %s", _paths["model"], exc)
        _model = None

    if _metadata:
        for part_id, meta in (_metadata.get("part_metadata") or {}).items():
            name = str(meta.get("name") or "").strip().lower()
            if name:
                _part_by_name[name] = str(part_id)


def reset_cache() -> None:
    """Clear lazy caches (used by tests to force a reload)."""
    global _model, _metadata, _state, _loaded, _part_by_name
    _model = None
    _metadata = None
    _state = {}
    _loaded = False
    _part_by_name = {}


def inventory_readiness() -> InventoryReadiness:
    _load_artifacts()
    sample_count = int((_state or {}).get("sample_count", 0) or 0)

    if _model is None or _metadata is None:
        return InventoryReadiness(
            ml_ready=False,
            source=_state.get("source", "not_trained"),
            reason="Inventory model is not trained or could not be loaded.",
            sample_count=sample_count,
            message=(_state or {}).get("message", "INVENTORY_ML_NOT_READY"),
            model_path=_paths["model"],
            data_source="sample",
        )
    return InventoryReadiness(
        ml_ready=True,
        source=_metadata.get("source", "sample"),
        reason="Inventory Random Forest model is ready (development prototype).",
        sample_count=sample_count,
        message=(_state or {}).get("message", "INVENTORY_ML_READY (SAMPLE/DEVELOPMENT)"),
        model_path=_paths["model"],
        data_source="sample",
    )


# ---------------------------------------------------------------------------
# Business-rule threshold detection + prescriptive recommendation (NOT ML)
# ---------------------------------------------------------------------------
def evaluate_recommendation(
    predicted_quantity_issued: float,
    current_stock: float,
    reorder_level: float,
    supplier_lead_days: float,
) -> Dict[str, object]:
    """Simplify, explainable rules: restocking/loss/loss/demand checks.

    This is arithmetic, not machine learning. It converts an ML forecast into
    operational tier-risk and a suggestion for downstream prescriptive use.
    It never places orders.
    """
    predicted = max(0.0, float(predicted_quantity_issued))
    stock = max(0.0, float(current_stock))
    reorder = max(0.0, float(reorder_level))

    stockout_risk = bool(stock < predicted)
    low_stock = bool(stock <= reorder)
    elevated_demand = bool(predicted >= max(1.0, reorder) and predicted > 0)

    risk_flags = {
        "stockout_risk": stockout_risk,
        "low_stock": low_stock,
        "elevated_demand": elevated_demand,
    }

    if stockout_risk:
        risk_status = "STOCKOUT_RISK"
    elif low_stock:
        risk_status = "LOW_STOCK"
    elif elevated_demand:
        risk_status = "ELEVATED_DEMAND"
    else:
        risk_status = "NORMAL"

    if stockout_risk or low_stock:
        recommended_action = "Restocking may be required."
        suggested_order_qty = float(
            max(0.0, reorder + predicted - stock)
        )
        lead_note = (
            f"Lead time {int(supplier_lead_days)}d - order should be placed "
            "now to arrive before expected demand."
        )
    elif elevated_demand:
        recommended_action = "Monitor demand next week."
        suggested_order_qty = 0.0
        lead_note = "No immediate restock required."
    else:
        recommended_action = "No action required."
        suggested_order_qty = 0.0
        lead_note = "Stock is adequate for the forecast period."

    return {
        "risk_status": risk_status,
        "risk_flags": risk_flags,
        "recommended_action": recommended_action,
        "suggested_order_qty": round(suggested_order_qty, 2),
        "lead_note": lead_note,
        "rule_layer": (
            "business-rule threshold detection (not an ML prediction)"
        ),
    }


# ---------------------------------------------------------------------------
# Feature encoding aligned with training
# ---------------------------------------------------------------------------
def resolve_part(part_id: str) -> Optional[Dict[str, object]]:
    """Resolve a part by id or by (case-insensitive) name."""
    _load_artifacts()
    meta = (_metadata or {}).get("part_metadata") or {}
    key = str(part_id).strip()
    if key in meta:
        return {**meta[key], "part_id": key}
    lookup = _part_by_name.get(key.lower())
    if lookup is not None:
        return {**meta[lookup], "part_id": lookup}
    return None


def _overall_mean_mileage() -> float:
    buses = ((_metadata or {}).get("bus_metadata") or {}).values()
    vals = [float(b.get("mean_mileage", 0.0)) for b in buses if b.get("mean_mileage")]
    return float(np.mean(vals)) if vals else 0.0


def _next_week_monday(reference: Optional[datetime]) -> datetime:
    if reference is None:
        reference = datetime.now()
    today = reference.date()
    days_ahead = 7 - today.weekday()  # 0 = Monday
    if days_ahead == 7:
        days_ahead = 7  # always move forward to the NEXT week
    nxt = today + timedelta(days=days_ahead)
    return datetime(nxt.year, nxt.month, nxt.day)


def encode_features(
    bus_id: str,
    part_meta: Dict[str, object],
    forecast_date: Optional[datetime] = None,
    current_stock: Optional[float] = None,
    vehicle_mileage: Optional[float] = None,
    breakdown_count_lag1: Optional[int] = None,
    maintenance_type_lag1: Optional[str] = None,
    demand_lag1: Optional[float] = None,
    demand_lag2: Optional[float] = None,
    rolling_demand_4w: Optional[float] = None,
    rolling_demand_8w: Optional[float] = None,
    days_since_last_issue: Optional[float] = None,
) -> Dict[str, float]:
    """Build the exact training feature vector from request inputs + defaults.

    Missing operational context uses the part/bus training-set means (stored
    at training time) or neutral values - never invented numbers. Encodings
    are rebuilt identically to training (persisted sorted maps).
    """
    _load_artifacts()
    encoders = (_metadata or {}).get("encoders") or {}
    buses = encoders.get("bus_encodings") or {}
    parts = encoders.get("part_encodings") or {}
    categories = encoders.get("category_encodings") or {}
    units = encoders.get("unit_encodings") or {}
    maits = encoders.get("maint_lag_encodings") or {}
    bus_meta = ((_metadata or {}).get("bus_metadata") or {}).get(str(bus_id).strip()) or {}

    part_mean_demand = float(part_meta.get("mean_weekly_demand") or 0.0)
    part_mean_on_hand = float(part_meta.get("mean_on_hand") or 0.0)
    part_mean_days = float(part_meta.get("mean_days_since_last_issue") or 366.0)

    bus_encoded = float(buses.get(str(bus_id).strip(), -1.0))
    part_encoded = float(parts.get(str(part_meta.get("part_id")), -1.0))
    category_encoded = float(categories.get(str(part_meta.get("category")), -1.0))
    unit_encoded = float(units.get(str(part_meta.get("unit")), -1.0))

    maint = str(maintenance_type_lag1 or "None").strip()
    maint_encoded = float(maits.get(maint, -1.0))

    on_hand = float(current_stock) if current_stock is not None else part_mean_on_hand
    mileage = float(vehicle_mileage) if vehicle_mileage is not None else (
        float(bus_meta.get("mean_mileage") or 0.0) or _overall_mean_mileage()
    )
    breakdown_lag = float(breakdown_count_lag1 or 0)
    reorder_level = float(part_meta.get("reorder_level") or 0)
    lead_days = float(part_meta.get("supplier_lead_days") or 0)

    d1 = float(demand_lag1) if demand_lag1 is not None else part_mean_demand
    d2 = float(demand_lag2) if demand_lag2 is not None else part_mean_demand
    r4 = float(rolling_demand_4w) if rolling_demand_4w is not None else part_mean_demand
    r8 = float(rolling_demand_8w) if rolling_demand_8w is not None else part_mean_demand
    days = float(days_since_last_issue) if days_since_last_issue is not None else part_mean_days

    fdate = forecast_date or _next_week_monday(None)
    tz_naive = fdate.replace(tzinfo=None) if fdate.tzinfo else fdate
    start = pd.Timestamp(((_metadata or {}).get("periods") or {}).get("train_start"))
    elapsed = (
        (pd.Timestamp(tz_naive) - start).days / 7.0
        if pd.notna(start)
        else 0.0
    )

    iso = pd.Timestamp(tz_naive).isocalendar()
    return {
        "bus_encoded": bus_encoded,
        "part_encoded": part_encoded,
        "category_encoded": category_encoded,
        "unit_encoded": unit_encoded,
        "maintenance_type_lag1_encoded": maint_encoded,
        "on_hand": on_hand,
        "vehicle_mileage": mileage,
        "breakdown_count_lag1": breakdown_lag,
        "reorder_level": reorder_level,
        "supplier_lead_days": lead_days,
        "demand_lag1": d1,
        "demand_lag2": d2,
        "rolling_demand_4w": r4,
        "rolling_demand_8w": r8,
        "days_since_last_issue": days,
        "week_of_year": float(iso.week),
        "month": float(pd.Timestamp(tz_naive).month),
        "quarter": float(pd.Timestamp(tz_naive).quarter),
        "elapsed_weeks": elapsed,
    }


def predict_inventory_demand(
    bus_id: str,
    part_id: str,
    forecast_date: Optional[datetime] = None,
    current_stock: Optional[float] = None,
    vehicle_mileage: Optional[float] = None,
    breakdown_count_lag1: Optional[int] = None,
    maintenance_type_lag1: Optional[str] = None,
    demand_lag1: Optional[float] = None,
    demand_lag2: Optional[float] = None,
    rolling_demand_4w: Optional[float] = None,
    rolling_demand_8w: Optional[float] = None,
    days_since_last_issue: Optional[float] = None,
) -> Optional[InventoryPrediction]:
    """Predict next-week demand for a (bus, part).

    Returns None when the model is not ready or the part is unknown. The
    prediction is clipped to the observed training range and is never negative.
    """
    _load_artifacts()
    if _model is None or _metadata is None:
        return None

    part_meta = resolve_part(part_id)
    if part_meta is None:
        return None

    features = encode_features(
        bus_id=bus_id,
        part_meta=part_meta,
        forecast_date=forecast_date,
        current_stock=current_stock,
        vehicle_mileage=vehicle_mileage,
        breakdown_count_lag1=breakdown_count_lag1,
        maintenance_type_lag1=maintenance_type_lag1,
        demand_lag1=demand_lag1,
        demand_lag2=demand_lag2,
        rolling_demand_4w=rolling_demand_4w,
        rolling_demand_8w=rolling_demand_8w,
        days_since_last_issue=days_since_last_issue,
    )

    features_expected = (_metadata or {}).get("features") or INVENTORY_FEATURE_COLUMNS
    try:
        frame = pd.DataFrame(
            [[features[name] for name in features_expected]],
            columns=features_expected,
        )
        predicted = float(_model.predict(frame)[0])
    except Exception as exc:  # noqa: BLE001
        logger.warning("Inventory prediction failed: %s", exc)
        return None

    state_range = (_state or {}).get("target_range") or {}
    max_seen = float(state_range.get("max", 0.0)) if state_range else 0.0
    predicted = max(0.0, min(predicted, max_seen)) if max_seen > 0 else max(0.0, predicted)
    predicted = round(float(predicted), 3)

    reorder = float(part_meta.get("reorder_level") or 0.0)
    lead = float(part_meta.get("supplier_lead_days") or 0.0)
    stock = float(current_stock) if current_stock is not None else float(
        part_meta.get("mean_on_hand") or 0.0
    )

    assessment = evaluate_recommendation(predicted, stock, reorder, lead)

    return InventoryPrediction(
        predicted_quantity_issued=predicted,
        unit=str(part_meta.get("unit") or ""),
        part_name=str(part_meta.get("name") or ""),
        source="ml",
        forecast_period="next_week",
        data_source="sample",
        is_production_model=False,
        model_type="Sample / Development Model",
        feature_inputs={name: features[name] for name in features_expected},
        assessment=assessment,
    )


def prediction_to_dict(prediction: InventoryPrediction, bus_id: str, part_id: str) -> Dict[str, object]:
    """Flatten a prediction into the API response shape."""
    data = asdict(prediction)
    features = data.pop("feature_inputs")
    del features  # raw feature row kept internal for debugging
    return {
        "success": True,
        "model_ready": True,
        "source": "ml",
        "part_id": part_id,
        "part_name": data["part_name"],
        "unit": data["unit"],
        "bus_id": bus_id,
        "forecast_period": data["forecast_period"],
        "predicted_quantity_issued": data["predicted_quantity_issued"],
        "data_source": data["data_source"],
        "is_production_model": data["is_production_model"],
        "model_type": data["model_type"],
        "disclaimer": data["disclaimer"],
        **data["assessment"],
    }