"""FastAPI router for ETA / trip-duration prediction."""

import logging
from datetime import datetime
from typing import Dict, Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from .predict import eta_readiness, predict_trip_duration, route_supported

logger = logging.getLogger(__name__)
router = APIRouter()


class EtaTripRequest(BaseModel):
    route: str = Field(..., min_length=1, description="Route label.")
    departure_at: datetime = Field(..., description="Scheduled departure datetime.")
    shift: Optional[str] = Field(default=None, description="Morning / Afternoon / Night.")
    bus_no: Optional[str] = Field(default=None, description="Assigned bus number, if known.")
    distance_km: Optional[float] = Field(default=None, ge=0)
    route_estimated_time_minutes: Optional[float] = Field(default=None, ge=0)


class EtaTripResponse(BaseModel):
    success: bool
    model_ready: bool
    source: str
    data_source: str
    is_production_model: bool
    sample_count: int
    predicted_duration_minutes: Optional[float]
    estimated_arrival_at: Optional[datetime]
    departure_at: Optional[datetime]
    feature_inputs: Dict[str, float]
    message: str


class EtaStatusResponse(BaseModel):
    success: bool
    model_ready: bool
    source: str
    data_source: str
    dataset_type: str
    is_production_model: bool
    model_type: str
    sample_count: int
    distinct_routes: int
    model_path: str
    reason: str


@router.get("/status", response_model=EtaStatusResponse)
def eta_model_status() -> EtaStatusResponse:
    readiness = eta_readiness()
    return EtaStatusResponse(
        success=True,
        model_ready=readiness.ml_ready,
        source=readiness.source,
        data_source=readiness.data_source,
        dataset_type=readiness.dataset_type,
        is_production_model=readiness.is_production_model,
        model_type=(
            "Production Model"
            if readiness.is_production_model
            else "Development Model"
            if readiness.ml_ready
            else "MODEL NOT READY"
        ),
        sample_count=readiness.sample_count,
        distinct_routes=readiness.distinct_routes,
        model_path=str(readiness.model_path or ""),
        reason=readiness.reason,
    )


@router.post("/predict", response_model=EtaTripResponse)
def eta_trip_prediction(payload: EtaTripRequest) -> EtaTripResponse:
    readiness = eta_readiness()
    if not readiness.ml_ready:
        raise HTTPException(status_code=503, detail=readiness.reason)

    if not route_supported(payload.route):
        raise HTTPException(
            status_code=422,
            detail=(
                "ETA MODEL NOT ELIGIBLE: this route was not present in the model's "
                "training history. Use the historical/statistical fallback until "
                "sufficient genuine route history is collected."
            ),
        )

    prediction = predict_trip_duration(
        route=payload.route,
        departure_at=payload.departure_at,
        shift=payload.shift,
        bus_no=payload.bus_no,
        distance_km=payload.distance_km,
        route_estimated_time_minutes=payload.route_estimated_time_minutes,
    )
    if prediction is None:
        logger.warning("ETA prediction failed for route %s", payload.route)
        raise HTTPException(status_code=500, detail="Unable to produce an ETA prediction for this trip.")

    return EtaTripResponse(
        success=True,
        model_ready=True,
        source=prediction.source,
        data_source=readiness.data_source,
        is_production_model=readiness.is_production_model,
        sample_count=readiness.sample_count,
        predicted_duration_minutes=prediction.predicted_duration_minutes,
        estimated_arrival_at=prediction.estimated_arrival_at,
        departure_at=prediction.departure_at,
        feature_inputs=prediction.feature_inputs,
        message=(
            f"Predicted trip duration is {prediction.predicted_duration_minutes:.0f} minutes "
            f"using {readiness.dataset_type.lower()}."
        ),
    )
