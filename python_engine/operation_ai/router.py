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

    Technical model readiness and saved row counts are reported separately
    from provenance. The retained artifacts have not passed a source-record
    audit; loading a model does not establish production suitability.
    """
    bus = bus_readiness()
    driver = driver_readiness()
    return MlTrainingStatusResponse(
        success=True,
        data_source="unverified",
        dataset_type="Reported scheduling training data; provenance unverified",
        policy_enforced=False,
        bus_model_ready=bus.ml_ready,
        driver_model_ready=driver.ml_ready,
        bus_source=bus.source,
        driver_source=driver.source,
        bus_data_source="unverified",
        driver_data_source="unverified",
        bus_is_production_model=False,
        driver_is_production_model=False,
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
