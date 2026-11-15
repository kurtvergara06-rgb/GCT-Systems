"""FastAPI routes for required GCT NLP capabilities and reviewable ingestion."""

from __future__ import annotations

from typing import Any, Literal

from fastapi import APIRouter, HTTPException, Query
from pydantic import BaseModel, Field

from .ingestion import (
    approve_record,
    list_staged,
    promoted_records,
    reject_record,
)
from .readiness import required_module_status

router = APIRouter()


class IngestionReviewRequest(BaseModel):
    """Human review metadata for a staged NLP record."""

    labels: dict[str, Any] = Field(default_factory=dict)
    reviewer: str | None = None


@router.get("/status")
def nlp_status() -> dict[str, Any]:
    """Return truthful readiness for every required NLP capability."""
    return required_module_status()


@router.get("/ingestion/staged")
def staged_records(
    status: Literal["pending", "approved", "rejected"] | None = Query(default=None),
) -> dict[str, Any]:
    """List staged extraction records, optionally filtered by review status."""
    records = list_staged(status)
    return {
        "success": True,
        "status": status,
        "count": len(records),
        "records": records,
    }


@router.get("/ingestion/promoted")
def promoted_ingestion_records() -> dict[str, Any]:
    """List human-approved records available for future training datasets."""
    records = promoted_records()
    return {
        "success": True,
        "count": len(records),
        "records": records,
    }


def _review_error(error: Exception) -> HTTPException:
    if isinstance(error, KeyError):
        return HTTPException(status_code=404, detail=str(error).strip("'"))
    if isinstance(error, ValueError):
        return HTTPException(status_code=422, detail=str(error))
    return HTTPException(status_code=500, detail="Unable to review the staged NLP record.")


@router.post("/ingestion/{staged_id}/approve")
def approve_staged_record(
    staged_id: str,
    request: IngestionReviewRequest,
) -> dict[str, Any]:
    """Approve a staged record and promote it to reviewed training evidence."""
    try:
        record = approve_record(
            staged_id,
            labels=request.labels,
            reviewer=request.reviewer,
        )
    except Exception as error:  # noqa: BLE001
        raise _review_error(error) from error

    return {
        "success": True,
        "message": "Staged NLP record approved and promoted.",
        "record": record,
    }


@router.post("/ingestion/{staged_id}/reject")
def reject_staged_record(
    staged_id: str,
    request: IngestionReviewRequest,
) -> dict[str, Any]:
    """Reject a staged record while retaining its audit/review evidence."""
    try:
        record = reject_record(
            staged_id,
            labels=request.labels,
            reviewer=request.reviewer,
        )
    except Exception as error:  # noqa: BLE001
        raise _review_error(error) from error

    return {
        "success": True,
        "message": "Staged NLP record rejected.",
        "record": record,
    }
