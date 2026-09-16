"""FastAPI router for the fuel consumption prediction service."""

import logging
from datetime import datetime
from typing import Dict, Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from .predict import fuel_readiness, predict_fuel_consumption

logger = logging.getLogger(__name__)

router = APIRouter()


class FuelTripRequest(BaseModel):
    route: str = Field(..., description="Route label, e.g. 'SM City Cebu - Mactan Airport'.")
    trip_started_at: datetime = Field(..., description="When the trip started (departure).")
    bus_no: Optional[str] = Field(default=None, description="Bus plate number, if assigned.")
    distance_km: Optional[float] = Field(default=None, description="Measured trip distance (km).")
    trip_duration_minutes: Optional[float] = Field(
        default=None, description="Trip clock duration in minutes."
    )
    in_motion_minutes: Optional[float] = Field(
        default=None, description="Trip time actually in motion (minutes)."
    )
    idling_minutes: Optional[float] = Field(
        default=None, description="Trip time idling (minutes)."
    )
    engine_on_hours: Optional[float] = Field(
        default=None, description="Engine-hours recorded for the trip."
    )


class FuelTripResponse(BaseModel):
    success: bool
    model_ready: bool
    source: str
    sample_count: int
    predicted_fuel_liters: Optional[float]
    feature_inputs: Dict[str, float]
    message: str


class FuelStatusResponse(BaseModel):
    success: bool
    model_ready: bool
    source: str
    sample_count: int
    model_path: str
    reason: str


@router.get("/status", response_model=FuelStatusResponse)
def fuel_model_status() -> FuelStatusResponse:
    readiness = fuel_readiness()
    return FuelStatusResponse(
        success=True,
        model_ready=readiness.ml_ready,
        source=readiness.source,
        sample_count=readiness.sample_count,
        model_path=str(readiness.model_path or ""),
        reason=readiness.reason,
    )


@router.post("/predict", response_model=FuelTripResponse)
def fuel_trip_prediction(payload: FuelTripRequest) -> FuelTripResponse:
    readiness = fuel_readiness()

    if not readiness.ml_ready:
        raise HTTPException(
            status_code=503,
            detail=readiness.reason,
        )

    prediction = predict_fuel_consumption(
        route=payload.route,
        trip_started_at=payload.trip_started_at,
        bus_no=payload.bus_no,
        distance_km=payload.distance_km,
        trip_duration_minutes=payload.trip_duration_minutes,
        in_motion_minutes=payload.in_motion_minutes,
        idling_minutes=payload.idling_minutes,
        engine_on_hours=payload.engine_on_hours,
    )

    if prediction is None:
        logger.warning("Fuel prediction failed for route %s", payload.route)
        raise HTTPException(
            status_code=500,
            detail="Unable to produce a fuel consumption prediction for this trip.",
        )

    return FuelTripResponse(
        success=True,
        model_ready=True,
        source=prediction.source,
        sample_count=readiness.sample_count,
        predicted_fuel_liters=prediction.predicted_fuel_liters,
        feature_inputs=prediction.feature_inputs,
        message=(
            f"Predicted Fuel Consumption: {prediction.predicted_fuel_liters:.2f} liters "
            "based on historical GPS trip records."
        ),
    )