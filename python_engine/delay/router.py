"""FastAPI router for the delay-prediction service (Model #3).

Supports two explicit training sources:
    * ``sample``  (development)  - SAMPLE / DEMONSTRATION model.
    * ``genuine`` (production)   - genuine GCT operational model, only available
                  after the genuine data-export + readiness gate passed.

The payloads always disclose the actual data source. Production additionally
enforces the shared genuine-data runtime policy: a sample/synthetic model is
reported as ``MODEL NOT READY`` and cannot serve predictions.
"""

import logging
from datetime import datetime
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from ml_runtime_policy import evaluate_model_source, synthetic_models_allowed
from .config import disclaimers
from .predict import (
    delay_readiness,
    predict_arrival_delay,
    prediction_to_dict,
    reset_cache,
)

logger = logging.getLogger(__name__)

router = APIRouter()


class DelayPredictionRequest(BaseModel):
    route: str = Field(..., description="Route label, e.g. 'Talisay - SM Seaside' "
                                        "or a route code for genuine mode.")
    scheduled_departure_time: str = Field(
        ...,
        description="Scheduled departure time in 24h 'HH:MM' (e.g. '13:10').",
    )
    bus_no: Optional[str] = Field(default=None, description="Bus plate number, if assigned.")
    driver_id: Optional[str] = Field(default=None, description="Driver id, if assigned.")
    trip_date: Optional[str] = Field(
        default=None, description="Trip date 'YYYY-MM-DD' (defaults to today). "
        "Used for day-of-week / month / season."
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
    incident_before_departure: bool = Field(
        default=False, description="An incident is already reported on this trip/bus "
        "before the scheduled departure."
    )
    incident_breakdown_flag: bool = Field(
        default=False, description="A breakdown incident is reported before departure."
    )
    incident_traffic_flag: bool = Field(
        default=False, description="A traffic/accident incident is reported before departure."
    )
    incident_replacement_flag: bool = Field(
        default=False, description="A replacement bus has already been dispatched before departure."
    )


def _parse_trip_date(value: Optional[str]) -> Optional[datetime]:
    if not value:
        return None
    try:
        return datetime.strptime(str(value).strip(), "%Y-%m-%d")
    except ValueError:
        raise HTTPException(
            status_code=422,
            detail="trip_date must be 'YYYY-MM-DD'.",
        ) from None


@router.get("/status")
def delay_model_status() -> dict:
    readiness = delay_readiness()
    source = readiness.source if readiness.source in {"sample", "genuine"} else readiness.data_source
    policy = evaluate_model_source("Delay Model #3", source)
    effective_ready = bool(readiness.ml_ready and policy.allowed)
    genuine = policy.data_source == "genuine"

    return {
        "success": True,
        "model_ready": effective_ready,
        "ready": effective_ready,
        "source": readiness.source,
        "dataset_type": "GENUINE GCT RECORDS" if genuine else "SAMPLE / DEMONSTRATION (SYNTHETIC)",
        "model_source": policy.data_source,
        "data_source": readiness.data_source,
        "is_production_model": bool(genuine and effective_ready),
        "model_type": (
            "Production Model" if genuine and effective_ready
            else "Synthetic / Development Model" if policy.allowed
            else "MODEL NOT READY"
        ),
        "model_version": readiness.message if policy.allowed else policy.model_ready_message,
        "training_record_count": readiness.sample_count,
        "sample_count": readiness.sample_count,
        "model_path": str(readiness.model_path or ""),
        "runtime_mode": policy.runtime_mode,
        "synthetic_allowed": synthetic_models_allowed(),
        "policy_enforced": True,
        "reason": readiness.reason if policy.allowed else policy.reason,
        "message": readiness.message if policy.allowed else policy.model_ready_message,
        "warning": (
            "" if genuine else
            "This model is NOT trained on genuine GCT historical delay records. Generated/synthetic Delay data is development-only and is blocked in production."
        ),
        "disclaimer": disclaimers().get(
            readiness.source if readiness.source in {"sample", "genuine"} else "sample",
            disclaimers()["sample"],
        ),
    }


@router.post("/predict")
def delay_prediction(payload: DelayPredictionRequest) -> dict:
    readiness = delay_readiness()
    source = readiness.source if readiness.source in {"sample", "genuine"} else readiness.data_source
    policy = evaluate_model_source("Delay Model #3", source)

    if not policy.allowed:
        raise HTTPException(status_code=503, detail=policy.reason)

    if not readiness.ml_ready:
        raise HTTPException(
            status_code=503,
            detail=(
                "MODEL NOT READY: Delay model does not have a usable genuine "
                "training artifact for this runtime."
            ),
        )

    prediction = predict_arrival_delay(
        route=payload.route,
        scheduled_departure_time=payload.scheduled_departure_time,
        bus_no=payload.bus_no,
        driver_id=payload.driver_id,
        trip_date=_parse_trip_date(payload.trip_date),
        scheduled_duration_minutes=payload.scheduled_duration_minutes,
        route_distance_km=payload.route_distance_km,
        route_prior_delay_mean_min=payload.route_prior_delay_mean_min,
        route_prior_delay_rate=payload.route_prior_delay_rate,
        driver_prior_delay_mean_min=payload.driver_prior_delay_mean_min,
        driver_trip_seq=payload.driver_trip_seq,
        incident_before_departure=payload.incident_before_departure,
        incident_breakdown_flag=payload.incident_breakdown_flag,
        incident_traffic_flag=payload.incident_traffic_flag,
        incident_replacement_flag=payload.incident_replacement_flag,
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
