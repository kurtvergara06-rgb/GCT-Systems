from fastapi import FastAPI, UploadFile, File, HTTPException
from pathlib import Path
import shutil
import uuid

from NLP.pdf_extractor import extract_pdf_text
from NLP.text_cleaner import clean_text
from NLP.entity_extractor import extract_entities


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

        if not raw_text:
            raise HTTPException(
                status_code=422,
                detail="No readable text was found in this PDF. It may be a scanned image PDF."
            )

        cleaned_text = clean_text(raw_text)

        extracted_data = extract_entities(cleaned_text)

        return {
            "success": True,
            "file_name": pdf_file.filename,
            "raw_text": raw_text,
            "cleaned_text": cleaned_text,
            "extracted_data": extracted_data
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