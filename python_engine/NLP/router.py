"""FastAPI routes for required GCT NLP capabilities and reviewable ingestion."""

from __future__ import annotations

import hmac
import os
from typing import Any, Literal

from fastapi import APIRouter, Header, HTTPException, Query
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


class TextAnalysisRequest(BaseModel):
    """Text-only NLP request: no PDF parsing or large language model required."""

    text: str = Field(min_length=1, max_length=20000)


@router.post("/analyze-text")
def analyze_text(payload: TextAnalysisRequest) -> dict[str, Any]:
    """Extract operational entities and rule-based severity without spaCy."""
    from .ner_extractor import extract_entities
    from .severity_predictor import predict_record

    entities = extract_entities(payload.text)
    severity = predict_record({"description": payload.text})
    return {
        "success": True,
        "entities": entities,
        "severity": severity,
        "source": "operational_rules",
        "trained_model": False,
    }


class GpsEnrichmentItem(BaseModel):
    id: int = Field(gt=0)
    description: str = Field(default="", max_length=1200)
    bus_no: str = Field(default="", max_length=80)
    grouping: str = Field(default="", max_length=120)
    duration_minutes: float | None = None
    total_minutes: float | None = None
    in_motion_minutes: float | None = None
    idling_minutes: float | None = None
    mileage_km: float | None = None
    engine_hours: float | None = None


class GpsEnrichmentBatch(BaseModel):
    records: list[GpsEnrichmentItem] = Field(min_length=1, max_length=25)


@router.get("/enrich-gps-status")
def enrich_gps_status(x_gct_nlp_token: str | None = Header(default=None)) -> dict[str, Any]:
    """Authenticated readiness check for the actual lightweight enrichment path."""
    token = os.getenv("GCT_NLP_ENRICH_TOKEN", "")
    if not token or not x_gct_nlp_token or not hmac.compare_digest(token, x_gct_nlp_token):
        raise HTTPException(status_code=403, detail="NLP enrichment token required.")
    from .anomaly_detector import readiness as anomaly_readiness
    from .ner_extractor import readiness as ner_readiness
    from .severity_ner_predictor import readiness as severity_readiness

    ready = all(check()["ready"] for check in (
        anomaly_readiness, ner_readiness, severity_readiness
    ))
    return {
        "ready": ready,
        "source": "operational_rules",
        "trained_model": False,
        "max_batch_size": 25,
    }


@router.post("/enrich-gps-batch")
def enrich_gps_batch(
    payload: GpsEnrichmentBatch,
    x_gct_nlp_token: str | None = Header(default=None),
) -> dict[str, Any]:
    """Bounded, advisory rule NLP. No model loading, PDF processing or ingestion writes."""
    token = os.getenv("GCT_NLP_ENRICH_TOKEN", "")
    if not token or not x_gct_nlp_token or not hmac.compare_digest(token, x_gct_nlp_token):
        raise HTTPException(status_code=403, detail="NLP enrichment token required.")

    from .anomaly_detector import anomaly_details
    from .ner_extractor import extract_entities
    from .severity_ner_predictor import predict_record as classify_severity

    results = []
    for item in payload.records:
        record = item.model_dump()
        text = " ".join(part for part in (
            record.get("description"), "bus " + record["bus_no"] if record["bus_no"] else "",
            "route " + record["grouping"] if record["grouping"] else "",
        ) if part)
        try:
            entities = extract_entities(text, use_spacy=False)
            severity = classify_severity(record, use_spacy=False)
            anomaly = anomaly_details(record)
            results.append({
                "id": item.id, "success": True, "entities": entities,
                "severity": severity, "anomaly": anomaly,
                "source": "operational_rules", "trained_model": False,
            })
        except Exception:
            # A bad input must not abort another record's enrichment.
            results.append({"id": item.id, "success": False, "source": "operational_rules", "trained_model": False})
    return {"success": True, "records": results, "source": "operational_rules", "trained_model": False}


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
