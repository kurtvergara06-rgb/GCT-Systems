from contextlib import asynccontextmanager
from pathlib import Path
import logging
import shutil
import uuid

from fastapi import FastAPI, File, HTTPException, UploadFile

from NLP.anomaly_detector import anomaly_details
from NLP.entity_extractor import (
    extract_entities,
    infer_records_from_table_rows,
)
from NLP.ingestion import stage_record
from NLP.ner_extractor import extract_entities as extract_ner_entities
from NLP.pdf_extractor import (
    extract_pdf_rows,
    extract_pdf_text,
)
from NLP.readiness import assert_required_modules, required_module_status
from NLP.router import router as nlp_router
from NLP.severity_ner_predictor import (
    build_text as render_record_text,
    predict_record as predict_ner_severity,
)
from NLP.severity_predictor import predict_record as predict_severity
from NLP.text_cleaner import clean_text
from analytics.router import router as analytics_router
from operation_ai.router import router as operation_ai_router
from eta.router import router as eta_router
from fuel.router import router as fuel_router
from inventory.router import router as inventory_router
from delay.router import router as delay_router


logger = logging.getLogger(__name__)

MAX_UPLOAD_BYTES = 20 * 1024 * 1024


def annotate_records(records: list[dict]) -> list[dict]:
    """Attach required NLP severity, NER, and anomaly analysis to records.

    These capabilities are required parts of the deployed Python engine. A
    failure on one individual record is still isolated so one malformed row
    cannot take down a complete PDF upload. The classifiers themselves report
    their real source and never claim trained-model readiness when it does not
    exist.
    """
    annotated = []

    for record in records:
        annotated_record = dict(record)

        try:
            annotated_record["severity_prediction"] = predict_severity(record)
        except Exception as error:  # noqa: BLE001
            logger.warning("Severity classification failed for a record: %s", error)
            annotated_record["severity_prediction"] = None

        try:
            annotated_record["severity_prediction_ner"] = predict_ner_severity(record)
        except Exception as error:  # noqa: BLE001
            logger.warning("NER severity classification failed for a record: %s", error)
            annotated_record["severity_prediction_ner"] = None

        try:
            annotated_record["entities"] = extract_ner_entities(
                render_record_text(record)
            )
        except Exception as error:  # noqa: BLE001
            logger.warning("NER entity extraction failed for a record: %s", error)
            annotated_record["entities"] = {}

        try:
            details = anomaly_details(record)
            annotated_record["anomaly"] = details.get("is_anomaly")
            annotated_record["anomaly_score"] = details.get("anomaly_score")
            annotated_record["anomaly_signals"] = details.get("signals", [])
            annotated_record["anomaly_source"] = details.get("source")
        except Exception as error:  # noqa: BLE001
            logger.warning("Anomaly detection failed for a record: %s", error)
            annotated_record["anomaly"] = None
            annotated_record["anomaly_score"] = None
            annotated_record["anomaly_signals"] = []
            annotated_record["anomaly_source"] = None

        annotated.append(annotated_record)

    return annotated


def _stage_and_annotate(records: list[dict], source_format: str) -> list[dict]:
    """Annotate records and stage every result for human review.

    Ingestion is a required capability. A single record that cannot be staged
    is retained in the upload response with an explicit staging error instead
    of silently pretending it entered the review pipeline.
    """
    annotated = annotate_records(records)

    for record in annotated:
        record["_staged_id"] = str(uuid.uuid4())

        try:
            stage_record(record, source_format)
            record["_staging_status"] = "pending"
        except Exception as error:  # noqa: BLE001
            logger.exception("Failed to stage a record for ingestion.")
            record["_staging_status"] = "failed"
            record["_staging_error"] = str(error)

    return annotated


def _warm_operation_ai_models() -> None:
    """Pre-load latency-sensitive models used by live requests."""
    try:
        from operation_ai.ml import predict as ml_predict

        ml_predict.bus_readiness()
        ml_predict.driver_readiness()
    except Exception as exc:  # noqa: BLE001
        logger.warning(
            "Operation AI model warm-up failed (will lazy-load): %s",
            exc,
        )

    try:
        from eta.predict import eta_readiness

        eta_readiness()
    except Exception as exc:  # noqa: BLE001
        logger.warning(
            "ETA model warm-up failed (will lazy-load): %s",
            exc,
        )


@asynccontextmanager
async def operation_ai_lifespan(app: FastAPI):
    # Required NLP capabilities are a deployment contract. Missing modules or
    # an unusable ingestion store should fail startup rather than be disguised
    # as a complete AI deployment.
    assert_required_modules()
    _warm_operation_ai_models()
    yield


app = FastAPI(
    title="GCT Python Engine",
    description=(
        "Business Analytics, PDF NLP Processing, "
        "and Operation AI Assistance API"
    ),
    version="1.0.0",
    lifespan=operation_ai_lifespan,
)


app.include_router(
    analytics_router,
    prefix="/analytics",
    tags=["Business Analytics"],
)

app.include_router(
    operation_ai_router,
    prefix="/operation/auto-scheduling/ai",
    tags=["Operation AI Assistance"],
)

app.include_router(
    eta_router,
    prefix="/eta",
    tags=["Predictive Analytics"],
)

app.include_router(
    fuel_router,
    prefix="/fuel",
    tags=["Predictive Analytics"],
)

app.include_router(
    inventory_router,
    prefix="/inventory",
    tags=["Predictive Analytics"],
)

app.include_router(
    delay_router,
    prefix="/delay",
    tags=["Predictive Analytics"],
)

# Required NLP capability/readiness and human-review endpoints. The existing
# PDF extraction endpoint below remains at /nlp/extract-pdf.
app.include_router(
    nlp_router,
    prefix="/nlp",
    tags=["NLP Processing"],
)

UPLOAD_FOLDER = Path("uploads")
UPLOAD_FOLDER.mkdir(
    parents=True,
    exist_ok=True,
)


@app.get("/")
def home() -> dict[str, str]:
    return {
        "message": "GCT Python Engine is running.",
    }


@app.get("/health")
def health_check() -> dict:
    nlp = required_module_status()
    return {
        "status": "online" if nlp["ready"] else "degraded",
        "service": "GCT Python Engine",
        "required_nlp_ready": nlp["ready"],
    }


@app.post("/nlp/extract-pdf")
def extract_pdf_data(
    pdf_file: UploadFile = File(...),
) -> dict:
    if not pdf_file.filename:
        raise HTTPException(
            status_code=400,
            detail="No PDF file was uploaded.",
        )

    if not pdf_file.filename.lower().endswith(".pdf"):
        raise HTTPException(
            status_code=400,
            detail="Only PDF files are allowed.",
        )

    safe_filename = Path(
        pdf_file.filename
    ).name

    available = pdf_file.size

    if (
        available is not None
        and available > MAX_UPLOAD_BYTES
    ):
        raise HTTPException(
            status_code=413,
            detail=(
                "PDF is too large. Maximum size is "
                f"{MAX_UPLOAD_BYTES // (1024 * 1024)} MB."
            ),
        )

    unique_name = (
        f"{uuid.uuid4()}_{safe_filename}"
    )

    saved_pdf_path = (
        UPLOAD_FOLDER / unique_name
    )

    try:
        with saved_pdf_path.open("wb") as buffer:
            shutil.copyfileobj(
                pdf_file.file,
                buffer,
            )

        raw_text = extract_pdf_text(
            str(saved_pdf_path),
        )

        table_result = extract_pdf_rows(
            str(saved_pdf_path),
        )

        if isinstance(table_result, tuple):
            table_rows, detected_table_type = (
                table_result
            )
        else:
            table_rows = table_result or []
            detected_table_type = "unknown"

        table_rows = table_rows or []

        if not raw_text and not table_rows:
            raise HTTPException(
                status_code=422,
                detail=(
                    "No readable text was found in this PDF. "
                    "It may be a scanned image PDF."
                ),
            )

        cleaned_text = clean_text(
            raw_text,
        )

        extracted_data = extract_entities(
            cleaned_text,
        )

        records = []
        skipped_headers = 0
        skipped_no_bus_no = 0
        extraction_mode = None
        table_type = detected_table_type
        debug_info = {}

        if table_rows:
            inference_result = (
                infer_records_from_table_rows(
                    table_rows,
                )
            )

            records = inference_result.get(
                "records",
                [],
            )

            skipped_headers = (
                inference_result.get(
                    "skipped_headers",
                    0,
                )
            )

            skipped_no_bus_no = (
                inference_result.get(
                    "skipped_no_bus_no",
                    0,
                )
            )

            debug_info = (
                inference_result.get(
                    "debug_info",
                    {},
                )
            )

            if records:
                extraction_mode = "table"

        if not records and not table_rows:
            has_meaningful_value = any(
                [
                    extracted_data.get(
                        "bus_no"
                    ),
                    extracted_data.get(
                        "grouping"
                    ),
                    extracted_data.get(
                        "beginning"
                    ),
                    extracted_data.get(
                        "ending"
                    ),
                    extracted_data.get(
                        "initial_location"
                    ),
                    extracted_data.get(
                        "final_location"
                    ),
                    extracted_data.get(
                        "location"
                    ),
                    extracted_data.get(
                        "mileage_km"
                    ),
                    extracted_data.get(
                        "engine_hours"
                    ),
                ]
            )

            if (
                has_meaningful_value
                and extracted_data.get(
                    "bus_no"
                )
            ):
                fallback_record = {
                    "record_no": None,
                    "bus_no": (
                        extracted_data.get(
                            "bus_no"
                        )
                    ),
                    "grouping": (
                        extracted_data.get(
                            "grouping"
                        )
                    ),
                    "trip_type": (
                        extracted_data.get(
                            "trip_type"
                        )
                    ),
                    "beginning": (
                        extracted_data.get(
                            "beginning"
                        )
                    ),
                    "initial_location": (
                        extracted_data.get(
                            "initial_location"
                        )
                    ),
                    "ending": (
                        extracted_data.get(
                            "ending"
                        )
                    ),
                    "final_location": (
                        extracted_data.get(
                            "final_location"
                        )
                    ),
                    "duration_minutes": (
                        extracted_data.get(
                            "duration_minutes"
                        )
                    ),
                    "total_minutes": (
                        extracted_data.get(
                            "total_minutes"
                        )
                    ),
                    "in_motion_minutes": (
                        extracted_data.get(
                            "in_motion_minutes"
                        )
                    ),
                    "idling_minutes": (
                        extracted_data.get(
                            "idling_minutes"
                        )
                    ),
                    "mileage_km": (
                        extracted_data.get(
                            "mileage_km"
                        )
                    ),
                    "engine_hours": (
                        extracted_data.get(
                            "engine_hours"
                        )
                    ),
                    "location": (
                        extracted_data.get(
                            "location"
                        )
                    ),
                    "coordinates": (
                        extracted_data.get(
                            "coordinates"
                        )
                    ),
                    "description": (
                        extracted_data.get(
                            "description"
                        )
                    ),
                    "source_format": (
                        "PDF Text Report"
                    ),
                    "raw_data": {
                        "raw_text": raw_text,
                    },
                }

                records = [
                    fallback_record,
                ]

                extraction_mode = (
                    "text_fallback"
                )

                table_type = (
                    "text_fallback"
                )

        return {
            "success": True,
            "file_name": pdf_file.filename,
            "raw_text": raw_text,
            "cleaned_text": cleaned_text,
            "source_format": "GPS Report",
            "records": _stage_and_annotate(records, "GPS Report"),
            "extracted_data": extracted_data,
            "_debug": {
                "table_rows_found": len(
                    table_rows
                ),
                "records_created": len(
                    records
                ),
                "skipped_headers": (
                    skipped_headers
                ),
                "skipped_no_bus_no": (
                    skipped_no_bus_no
                ),
                "extraction_mode": (
                    extraction_mode
                ),
                "table_type": table_type,
                "detected_header": (
                    debug_info.get(
                        "detected_header"
                    )
                ),
                "sample_rows": (
                    debug_info.get(
                        "sample_rows"
                    )
                ),
                "num_standard_rows": (
                    debug_info.get(
                        "num_standard_rows"
                    )
                ),
                "num_key_value_rows": (
                    debug_info.get(
                        "num_key_value_rows"
                    )
                ),
            },
        }

    except HTTPException:
        raise

    except Exception as error:
        logger.exception(
            "PDF NLP processing failed.",
        )

        raise HTTPException(
            status_code=500,
            detail=(
                "PDF NLP processing failed. "
                "Please try again later."
            ),
        ) from error

    finally:
        if saved_pdf_path.exists():
            saved_pdf_path.unlink()
