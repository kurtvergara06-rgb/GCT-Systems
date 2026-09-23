"""FastAPI router for Inventory Model #4.

Production accepts only a genuine inventory artifact. Genuine forecasting is
fleet-level by spare part; bus_id remains optional context for existing clients
and is not fabricated into the training history.
"""

import logging
from datetime import datetime
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from ml_runtime_policy import evaluate_model_source, synthetic_models_allowed
from .predict import (
    inventory_readiness,
    predict_inventory_demand,
    prediction_to_dict,
    reset_cache,
)
from .training_data import GENUINE_SCOPE_ID

logger = logging.getLogger(__name__)
router = APIRouter()


class InventoryPredictionRequest(BaseModel):
    part_id: str = Field(..., description="Inventory part id or part name.")
    bus_id: Optional[str] = Field(
        default=None,
        description=(
            "Optional bus context for compatibility. Genuine Model #4 forecasts "
            "fleet-level demand by part and does not invent per-bus history."
        ),
    )
    forecast_date: Optional[datetime] = Field(
        default=None, description="Forecast week (defaults to next Monday)."
    )
    current_stock: Optional[float] = Field(
        default=None, description="Current on-hand stock."
    )
    vehicle_mileage: Optional[float] = None
    breakdown_count_lag1: Optional[int] = None
    maintenance_type_lag1: Optional[str] = None
    demand_lag1: Optional[float] = None
    demand_lag2: Optional[float] = None
    rolling_demand_4w: Optional[float] = None
    rolling_demand_8w: Optional[float] = None
    days_since_last_issue: Optional[float] = None


@router.get("/status")
def inventory_model_status() -> dict:
    readiness = inventory_readiness()
    source = (
        readiness.source
        if readiness.source in {"sample", "genuine"}
        else readiness.data_source
    )
    policy = evaluate_model_source("Inventory Model #4", source)
    effective_ready = bool(readiness.ml_ready and policy.allowed)
    genuine = policy.data_source == "genuine"

    return {
        "success": True,
        "model_ready": effective_ready,
        "source": readiness.source,
        "dataset_type": (
            "GENUINE GCT INVENTORY LEDGER"
            if genuine
            else "SYNTHETIC / DEVELOPMENT"
        ),
        "data_source": policy.data_source,
        "is_production_model": bool(genuine and effective_ready),
        "model_type": (
            "Production Model"
            if genuine and effective_ready
            else "Synthetic / Development Model"
            if policy.allowed
            else "MODEL NOT READY"
        ),
        "forecast_scope": "fleet_part" if genuine else "bus_part",
        "sample_count": readiness.sample_count,
        "model_path": str(readiness.model_path or ""),
        "runtime_mode": policy.runtime_mode,
        "synthetic_allowed": synthetic_models_allowed(),
        "policy_enforced": True,
        "reason": readiness.reason if policy.allowed else policy.reason,
        "message": readiness.message if policy.allowed else policy.model_ready_message,
        "disclaimer": (
            "GENUINE GCT OPERATIONAL INVENTORY DATA"
            if genuine
            else "SYNTHETIC / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA"
        ),
    }


@router.post("/predict")
def inventory_demand_prediction(payload: InventoryPredictionRequest) -> dict:
    readiness = inventory_readiness()
    source = (
        readiness.source
        if readiness.source in {"sample", "genuine"}
        else readiness.data_source
    )
    policy = evaluate_model_source("Inventory Model #4", source)

    if not policy.allowed:
        raise HTTPException(status_code=503, detail=policy.reason)
    if not readiness.ml_ready:
        raise HTTPException(
            status_code=503,
            detail=(
                "MODEL NOT READY: Inventory Model #4 does not have sufficient "
                "genuine GCT stock-movement history for this runtime."
            ),
        )

    request_bus = payload.bus_id or GENUINE_SCOPE_ID
    prediction = predict_inventory_demand(
        bus_id=request_bus,
        part_id=payload.part_id,
        forecast_date=payload.forecast_date,
        current_stock=payload.current_stock,
        vehicle_mileage=payload.vehicle_mileage,
        breakdown_count_lag1=payload.breakdown_count_lag1,
        maintenance_type_lag1=payload.maintenance_type_lag1,
        demand_lag1=payload.demand_lag1,
        demand_lag2=payload.demand_lag2,
        rolling_demand_4w=payload.rolling_demand_4w,
        rolling_demand_8w=payload.rolling_demand_8w,
        days_since_last_issue=payload.days_since_last_issue,
    )

    if prediction is None:
        logger.warning("Inventory prediction unavailable for part=%s", payload.part_id)
        raise HTTPException(
            status_code=404,
            detail="Unknown part or model unavailable for this request.",
        )

    return prediction_to_dict(prediction, request_bus, payload.part_id)


@router.post("/cache/reset")
def reset_prediction_cache() -> dict:
    reset_cache()
    return {"success": True, "message": "Inventory model cache reset."}
