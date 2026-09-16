"""FastAPI router for the inventory demand-forecasting service (Model #4).

Development prototype trained on GENERATED SAMPLE data. See the DISCLAIMER
in the payloads - this must never be presented as a production model.
"""

import logging
from datetime import datetime
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from .predict import (
    inventory_readiness,
    predict_inventory_demand,
    prediction_to_dict,
    reset_cache,
)

logger = logging.getLogger(__name__)

router = APIRouter()


class InventoryPredictionRequest(BaseModel):
    bus_id: str = Field(..., description="Bus identifier, e.g. 'GCT-101'.")
    part_id: str = Field(..., description="Inventory part id or part name.")
    forecast_date: Optional[datetime] = Field(default=None, description="Forecast week (defaults to next Monday).")
    current_stock: Optional[float] = Field(default=None, description="Current on-hand stock (falls back to the part training mean).")
    vehicle_mileage: Optional[float] = Field(default=None, description="Bus mileage override (falls back to the bus training mean).")
    breakdown_count_lag1: Optional[int] = Field(default=None, description="Breakdowns last week (defaults to 0).")
    maintenance_type_lag1: Optional[str] = Field(default=None, description="Maintenance type last week (Preventive / Corrective / None).")
    demand_lag1: Optional[float] = Field(default=None, description="Issued quantity last week (falls back to part training mean).")
    demand_lag2: Optional[float] = Field(default=None, description="Issued quantity two weeks ago.")
    rolling_demand_4w: Optional[float] = Field(default=None, description="Total issued quantity over the last 4 weeks.")
    rolling_demand_8w: Optional[float] = Field(default=None, description="Total issued quantity over the last 8 weeks.")
    days_since_last_issue: Optional[float] = Field(default=None, description="Days since the last issuance (falls back to part training mean).")


@router.get("/status")
def inventory_model_status() -> dict:
    readiness = inventory_readiness()
    return {
        "success": True,
        "model_ready": readiness.ml_ready,
        "source": readiness.source,
        "dataset_type": "SAMPLE / DEVELOPMENT" if readiness.source == "sample" else "genuine",
        "sample_count": readiness.sample_count,
        "model_path": str(readiness.model_path or ""),
        "reason": readiness.reason,
        "message": readiness.message,
        "disclaimer": "SAMPLE / DEVELOPMENT DATA - NOT ACTUAL GCT OPERATIONAL DATA",
    }


@router.post("/predict")
def inventory_demand_prediction(payload: InventoryPredictionRequest) -> dict:
    readiness = inventory_readiness()

    if not readiness.ml_ready:
        raise HTTPException(
            status_code=503,
            detail=(
                "Inventory model is not ready. "
                "Run `python -m inventory.train_model` first."
            ),
        )

    prediction = predict_inventory_demand(
        bus_id=payload.bus_id,
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
        logger.warning(
            "Inventory prediction failed for bus=%s part=%s",
            payload.bus_id,
            payload.part_id,
        )
        raise HTTPException(
            status_code=404,
            detail="Unknown part or model unavailable for this request.",
        )

    return prediction_to_dict(prediction, payload.bus_id, payload.part_id)


@router.post("/cache/reset")
def reset_prediction_cache() -> dict:
    """Drop lazy-loaded artifacts (used by ops/tests, not by normal traffic)."""
    reset_cache()
    return {"success": True, "message": "Inventory model cache reset."}