import logging

from fastapi import APIRouter, HTTPException

from .analyzer import analyze_recommendation
from .ml.predict import bus_readiness, driver_readiness
from .recommender import recommend_trip
from .schemas import (
    AiAnalysisResponse,
    AiRecommendationResponse,
    MlTrainingStatusResponse,
    RecommendationData,
)


logger = logging.getLogger(__name__)

router = APIRouter()


@router.get(
    "/training/status",
    response_model=MlTrainingStatusResponse,
)
def ml_training_status() -> MlTrainingStatusResponse:
    """Report ML model readiness for the scheduling recommender.

    The active Random Forest pipeline is trained only from genuine GCT GPS
    trip outcomes (bus model) and genuine driver attendance history (driver
    model). The legacy generated-data neural scorer is not represented here.
    """
    bus = bus_readiness()
    driver = driver_readiness()
    return MlTrainingStatusResponse(
        success=True,
        data_source="genuine",
        dataset_type="GENUINE GCT GPS + ATTENDANCE RECORDS",
        policy_enforced=True,
        bus_model_ready=bus.ml_ready,
        driver_model_ready=driver.ml_ready,
        bus_source=bus.source,
        driver_source=driver.source,
        bus_data_source="genuine",
        driver_data_source="genuine",
        bus_is_production_model=bool(bus.ml_ready and bus.source == "ml"),
        driver_is_production_model=bool(driver.ml_ready and driver.source == "ml"),
        bus_reason=bus.reason,
        driver_reason=driver.reason,
        bus_sample_count=bus.sample_count,
        driver_sample_count=driver.sample_count,
    )


@router.post(
    "/analyze",
    response_model=AiAnalysisResponse,
)
def analyze_operation_schedule(
    payload: RecommendationData,
) -> AiAnalysisResponse:
    try:
        analysis = analyze_recommendation(payload)

        return AiAnalysisResponse(
            success=True,
            analysis=analysis,
        )

    except Exception as exc:
        logger.exception(
            "Operation AI analysis failed."
        )

        raise HTTPException(
            status_code=500,
            detail=(
                "Unable to analyze the "
                "schedule recommendation."
            ),
        ) from exc


@router.post(
    "/recommend",
    response_model=AiRecommendationResponse,
)
def recommend_operation_schedule(
    payload: RecommendationData,
) -> AiRecommendationResponse:
    try:
        return recommend_trip(payload)

    except Exception as exc:
        logger.exception(
            "Operation AI recommendation failed."
        )

        raise HTTPException(
            status_code=500,
            detail=(
                "Unable to generate the "
                "schedule recommendation."
            ),
        ) from exc
