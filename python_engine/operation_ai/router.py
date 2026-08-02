from fastapi import APIRouter, HTTPException

from .analyzer import analyze_recommendation
from .schemas import (
    AiAnalysisResponse,
    RecommendationData,
)

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
        raise HTTPException(
            status_code=500,
            detail="Unable to analyze the schedule recommendation.",
        ) from exc