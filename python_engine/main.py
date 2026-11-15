from pathlib import Path
import importlib
import logging
import shutil
import uuid

from fastapi import FastAPI, File, HTTPException, UploadFile

from NLP.entity_extractor import (
    extract_entities,
    infer_records_from_table_rows,
)
from NLP.pdf_extractor import (
    extract_pdf_rows,
    extract_pdf_text,
)
from NLP.text_cleaner import clean_text
from analytics.router import router as analytics_router
from operation_ai.router import router as operation_ai_router
from eta.router import router as eta_router
from fuel.router import router as fuel_router
from inventory.router import router as inventory_router
from delay.router import router as delay_router


def _optional_import(module_name: str, attribute: str):
    """Best-effort import of an optional NLP annotation module.

    Returns the requested callable, or None when the module is not available
    in this checkout. The severity / NER / anomaly / ingestion modules are not
    core PDF record extraction: when absent, annotations simply degrade to
    None / {} (see annotate_records) and the engine still starts.
    """
    try:
        module = importlib.import_module(module_name)
        return getattr(module, attribute)
    except Exception as error:  # noqa: BLE001
        logger.warning(
            "Optional NLP module %s.%s is unavailable: %s",
            module_name,
            attribute,
            error,
        )
        return None


def annotate_records(records: list[dict]) -> list[dict]:
    """Attach an NLP severity prediction and an anomaly flag to each record.

    The severity prediction is a remark-driven classifier; the anomaly flag is
    a robust unsupervised outlier score on the operational features. Either
    one degrades gracefully (None) if its model is unavailable.
    """
    severity_model = _optional_import("NLP.severity_predictor", "predict_record")
    severity_ner_model = _optional_import("NLP.severity_ner_predictor", "predict_record")
    render_record_text = _optional_import("NLP.severity_ner_predictor", "build_text")
    extract_ner_entities = _optional_import("NLP.ner_extractor", "extract_entities")
    detect_anomaly = _optional_import("NLP.anomaly_detector", "anomaly_details")

    annotated = []

    for record in records:
        annotated_record = dict(record)

        if severity_model is None:
            annotated_record["severity_prediction"] = None
        else:
            try:
                annotated_record["severity_prediction"] = severity_model(record)
            except FileNotFoundError:
                annotated_record["severity_prediction"] = None
            except Exception as error:
                logger.warning("Severity prediction failed for a record: %s", error)
                annotated_record["severity_prediction"] = None

        # Custom NER-driven severity classifier (typed event + operational
        # features), with entity extraction exposed for transparency.
        if severity_ner_model is None or render_record_text is None:
            annotated_record["severity_prediction_ner"] = None
        else:
            try:
                annotated_record["severity_prediction_ner"] = severity_ner_model(record)
            except FileNotFoundError:
                annotated_record["severity_prediction_ner"] = None
            except Exception as error:
                logger.warning("NER severity prediction failed for a record: %s", error)
                annotated_record["severity_prediction_ner"] = None

        if extract_ner_entities is None or render_record_text is None:
            annotated_record["entities"] = {}
        else:
            try:
                annotated_record["entities"] = extract_ner_entities(render_record_text(record))
            except Exception as error:
                logger.warning("NER entity extraction failed for a record: %s", error)
                annotated_record["entities"] = {}

        if detect_anomaly is None:
            annotated_record["anomaly"] = None
            annotated_record["anomaly_score"] = None
        else:
            try:
                details = detect_anomaly(record)
                annotated_record["anomaly"] = details.get("is_anomaly")
                annotated_record["anomaly_score"] = details.get("anomaly_score")
            except Exception as error:
                logger.warning("Anomaly detection failed for a record: %s", error)
                annotated_record["anomaly"] = None
                annotated_record["anomaly_score"] = None

        annotated.append(annotated_record)

    return annotated


def _stage_and_annotate(records: list[dict], source_format: str) -> list[dict]:
    """Annotate records with model predictions and persist them to staging.

    Each record gets a unique _staged_id and is written to the ingestion
    staging log so a reviewer can approve/label them for later retraining.
    Staging is best-effort: a failure there must not fail the whole upload.
    """
    stage_record = _optional_import("NLP.ingestion", "stage_record")

    annotated = annotate_records(records)

    for record in annotated:
        record["_staged_id"] = str(uuid.uuid4())

        if stage_record is None:
            continue

        try:
            stage_record(record, source_format)
        except Exception as error:
            logger.warning("Failed to stage a record for ingestion: %s", error)

    return annotated


from contextlib import asynccontextmanager


def _warm_operation_ai_models() -> None:
    """Pre-load the Operation AI ML models so the first live inference does not
    pay the lazy joblib.load cost inside a request (which can exceed Laravel's
    short service timeout). This is best-effort; failures only mean the first
    request warms them lazily as before."""
    try:
        from operation_ai.ml import predict as ml_predict

        ml_predict.bus_readiness()
        ml_predict.driver_readiness()
    except Exception as exc:  # noqa: BLE001
        logging.getLogger(__name__).warning(
            "Operation AI model warm-up failed (will lazy-load): %s", exc
        )

    try:
        from eta.predict import eta_readiness

        eta_readiness()
    except Exception as exc:  # noqa: BLE001
        logging.getLogger(__name__).warning(
            "ETA model warm-up failed (will lazy-load): %s", exc
        )


@asynccontextmanager
async def operation_ai_lifespan(app: FastAPI):
    _warm_operation_ai_models()
    yield


logger = logging.getLogger(__name__)

MAX_UPLOAD_BYTES = 20 * 1024 * 1024


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

# Register the Operation AI router during application startup.
app.include_router(
    operation_ai_router,
    prefix="/operation/auto-scheduling/ai",
    tags=["Operation AI Assistance"],
)

# Register the ETA / trip-duration prediction router (Predictive Analytics).
app.include_router(
    eta_router,
    prefix="/eta",
    tags=["Predictive Analytics"],
)

# Register the Fuel consumption prediction router (Model #2, trained on real
# linked GPS + fuel report records).
app.include_router(
    fuel_router,
    prefix="/fuel",
    tags=["Predictive Analytics"],
)

# Register the Inventory leading/forecasting router (Model #4, development
# prototype trained on SAMPLE data).
app.include_router(
    inventory_router,
    prefix="/inventory",
    tags=["Predictive Analytics"],
)

# Register the Delay-prediction router (Model #3). Source-aware: SAMPLE /
# DEMONSTRATION prototype by default; with DELAY_DATA_SOURCE=genuine it serves
# the genuine-operation model after the data-sufficiency gate has passed. It
# NEVER falls back to the sample when genuine mode is requested.
app.include_router(
    delay_router,
    prefix="/delay",
    tags=["Predictive Analytics"],
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
def health_check() -> dict[str, str]:
    return {
        "status": "online",
        "service": "GCT Python Engine",
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