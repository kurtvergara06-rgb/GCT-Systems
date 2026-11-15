import logging

from fastapi import APIRouter, HTTPException

from .analyzer import analyze_recommendation
from .recommender import recommend_trip
from .schemas import (
    AiAnalysisResponse,
    AiRecommendationResponse,
    RecommendationData,
)


logger = logging.getLogger(__name__)

router = APIRouter()


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