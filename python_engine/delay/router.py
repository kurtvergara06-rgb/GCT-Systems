"""FastAPI router for the delay-prediction service (Model #3).

DEVELOPMENT PROTOTYPE - SAMPLE / DEMONSTRATION DATA. See the DISCLAIMER in
the payloads - this must never be presented as a production model trained on
genuine GCT operational data.
"""

import logging
from datetime import date, datetime
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from .predict import (
    delay_readiness,
    predict_arrival_delay,
    prediction_to_dict,
    reset_cache,
)

logger = logging.getLogger(__name__)

router = APIRouter()

DISCLAIMER = (
    "SAMPLE / DEMONSTRATION DATA - NOT ACTUAL GCT OPERATIONAL DATA. "
    "This Model #3 prediction is a demonstration prototype trained on a "
    "separate sample dataset. It is NOT trained on genuine GCT historical "
    "delay records and must not be presented as a production prediction."
)


class DelayPredictionRequest(BaseModel):
    route: str = Field(..., description="Route label, e.g. 'Talisay - SM Seaside'.")
    scheduled_departure_time: str = Field(
        ...,
        description="Scheduled departure time in 24h 'HH:MM' (e.g. '13:10').",
    )
    bus_no: Optional[str] = Field(default=None, description="Bus plate number, if assigned.")
    driver_id: Optional[str] = Field(default=None, description="Driver id, if assigned.")
    trip_date: Optional[date] = Field(
        default=None, description="Trip date (defaults to today). Used for day-of-week / month / season."
    )
    scheduled_duration_minutes: Optional[float] = Field(
        default=None, description="Optional scheduled duration override (minutes)."
    )
    route_distance_km: Optional[float] = Field(
        default=None, description="Optional route distance override (km)."
    )
    route_prior_delay_mean_min: Optional[float] = Field(
        default=None, description="Optional prior mean arrival delay on this route (minutes)."
    )
    route_prior_delay_rate: Optional[float] = Field(
        default=None, description="Optional prior rate of >5-min late arrivals on this route (0..1)."
    )
    driver_prior_delay_mean_min: Optional[float] = Field(
        default=None, description="Optional prior mean arrival delay for this driver (minutes)."
    )
    driver_trip_seq: Optional[int] = Field(
        default=None, description="Optional position of this trip within the driver's day (1-based)."
    )


@router.get("/status")
def delay_model_status() -> dict:
    readiness = delay_readiness()
    return {
        "success": True,
        "model_ready": readiness.ml_ready,
        "source": readiness.source,
        "dataset_type": "SAMPLE / DEMONSTRATION" if readiness.source == "sample" else "genuine",
        "data_source": readiness.data_source,
        "is_production_model": readiness.source == "genuine",
        "model_type": (
            "Sample / Demonstration Model"
            if readiness.source == "sample"
            else "Production Model"
        ),
        "model_version": readiness.message,
        "training_record_count": readiness.sample_count,
        "sample_count": readiness.sample_count,
        "model_path": str(readiness.model_path or ""),
        "reason": readiness.reason,
        "message": readiness.message,
        "warning": (
            "This model is NOT trained on genuine GCT historical delay records. "
            "It is a SAMPLE / DEMONSTRATION prototype."
        ),
        "disclaimer": DISCLAIMER,
    }


@router.post("/predict")
def delay_prediction(payload: DelayPredictionRequest) -> dict:
    readiness = delay_readiness()

    if not readiness.ml_ready:
        raise HTTPException(
            status_code=503,
            detail=(
                "Delay model is not ready. Run "
                "`python -m delay.prepare_training_data` and "
                "`python -m delay.train_model` first."
            ),
        )

    prediction = predict_arrival_delay(
        route=payload.route,
        scheduled_departure_time=payload.scheduled_departure_time,
        bus_no=payload.bus_no,
        driver_id=payload.driver_id,
        trip_date=(
            datetime.combine(payload.trip_date, datetime.min.time())
            if payload.trip_date else None
        ),
        scheduled_duration_minutes=payload.scheduled_duration_minutes,
        route_distance_km=payload.route_distance_km,
        route_prior_delay_mean_min=payload.route_prior_delay_mean_min,
        route_prior_delay_rate=payload.route_prior_delay_rate,
        driver_prior_delay_mean_min=payload.driver_prior_delay_mean_min,
        driver_trip_seq=payload.driver_trip_seq,
    )

    if prediction is None:
        logger.warning(
            "Delay prediction failed for route=%s time=%s",
            payload.route,
            payload.scheduled_departure_time,
        )
        raise HTTPException(
            status_code=500,
            detail="Unable to produce a delay prediction for this trip.",
        )

    return prediction_to_dict(prediction)


@router.post("/cache/reset")
def reset_prediction_cache() -> dict:
    """Drop lazy-loaded artifacts (used by ops/tests, not by normal traffic)."""
    reset_cache()
    return {"success": True, "message": "Delay model cache reset."}