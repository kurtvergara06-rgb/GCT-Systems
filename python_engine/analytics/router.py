import logging

from fastapi import APIRouter, HTTPException

from .forecasting import (
    FleetTripForecastRequest,
    FleetTripForecastResponse,
    forecast_fleet_trips,
)


logger = logging.getLogger(__name__)

router = APIRouter()


@router.post(
    "/fleet-trip/predict",
    response_model=FleetTripForecastResponse,
)
def predict_fleet_trips(
    payload: FleetTripForecastRequest,
) -> FleetTripForecastResponse:
    try:
        return forecast_fleet_trips(payload)
    except Exception as exc:
        logger.exception("Fleet & Trip prediction failed.")
        raise HTTPException(
            status_code=500,
            detail="Unable to generate Fleet & Trip predictions.",
        ) from exc
