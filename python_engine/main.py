from fastapi import FastAPI, UploadFile, File, HTTPException
from pathlib import Path
import shutil
import uuid

from NLP.pdf_extractor import extract_pdf_text, extract_pdf_rows
from NLP.text_cleaner import clean_text
from NLP.entity_extractor import extract_entities, infer_records_from_table_rows


app = FastAPI(
    title="GCT Python Engine",
    description="Business Analytics and PDF NLP Processing API",
    version="1.0.0"
)


UPLOAD_FOLDER = Path("uploads")
UPLOAD_FOLDER.mkdir(exist_ok=True)


@app.get("/")
def home():
    return {
        "message": "GCT Python Engine is running."
    }


@app.get("/health")
def health_check():
    return {
        "status": "online",
        "service": "GCT Python Engine"
    }


@app.post("/nlp/extract-pdf")
async def extract_pdf_data(pdf_file: UploadFile = File(...)):
    if not pdf_file.filename:
        raise HTTPException(
            status_code=400,
            detail="No PDF file was uploaded."
        )

    if not pdf_file.filename.lower().endswith(".pdf"):
        raise HTTPException(
            status_code=400,
            detail="Only PDF files are allowed."
        )

    unique_name = f"{uuid.uuid4()}_{pdf_file.filename}"
    saved_pdf_path = UPLOAD_FOLDER / unique_name

    try:
        with open(saved_pdf_path, "wb") as buffer:
            shutil.copyfileobj(pdf_file.file, buffer)

        raw_text = extract_pdf_text(str(saved_pdf_path))

        table_result = extract_pdf_rows(str(saved_pdf_path))

        if isinstance(table_result, tuple):
            table_rows, detected_table_type = table_result
        else:
            table_rows = table_result
            detected_table_type = "unknown"

        if not raw_text and not table_rows:
            raise HTTPException(
                status_code=422,
                detail="No readable text was found in this PDF. It may be a scanned image PDF."
            )

        cleaned_text = clean_text(raw_text)
        extracted_data = extract_entities(cleaned_text)

        records = []
        extraction_mode = None
        table_type = detected_table_type

        if table_rows:
            inferred_records = infer_records_from_table_rows(table_rows)

            if inferred_records:
                records = inferred_records
                extraction_mode = "table"

        if not records:
            has_meaningful_value = any([
                extracted_data.get("bus_no"),
                extracted_data.get("grouping"),
                extracted_data.get("beginning"),
                extracted_data.get("ending"),
                extracted_data.get("initial_location"),
                extracted_data.get("final_location"),
                extracted_data.get("location"),
                extracted_data.get("mileage_km"),
                extracted_data.get("engine_hours"),
            ])

            if has_meaningful_value:
                fallback_record = {
                    "record_no": None,
                    "bus_no": extracted_data.get("bus_no"),
                    "grouping": extracted_data.get("grouping"),
                    "trip_type": extracted_data.get("trip_type"),
                    "beginning": extracted_data.get("beginning"),
                    "initial_location": extracted_data.get("initial_location"),
                    "ending": extracted_data.get("ending"),
                    "final_location": extracted_data.get("final_location"),
                    "duration_minutes": extracted_data.get("duration_minutes"),
                    "total_minutes": extracted_data.get("total_minutes"),
                    "in_motion_minutes": extracted_data.get("in_motion_minutes"),
                    "idling_minutes": extracted_data.get("idling_minutes"),
                    "mileage_km": extracted_data.get("mileage_km"),
                    "engine_hours": extracted_data.get("engine_hours"),
                    "location": extracted_data.get("location"),
                    "coordinates": extracted_data.get("coordinates"),
                    "description": extracted_data.get("description"),
                    "source_format": "PDF Text Report",
                    "raw_data": {
                        "raw_text": raw_text
                    }
                }

                records = [fallback_record]
                extraction_mode = "text_fallback"
                table_type = "text_fallback"

        return {
            "success": True,
            "file_name": pdf_file.filename,
            "raw_text": raw_text,
            "cleaned_text": cleaned_text,
            "source_format": "GPS Report",
            "records": records,
            "extracted_data": extracted_data,
            "_debug": {
                "table_rows_found": len(table_rows),
                "records_created": len(records),
                "extraction_mode": extraction_mode,
                "table_type": table_type
            }
        }

    except HTTPException:
        raise

    except Exception as error:
        raise HTTPException(
            status_code=500,
            detail=f"PDF NLP processing failed: {str(error)}"
        )

    finally:
        if saved_pdf_path.exists():
            saved_pdf_path.unlink()