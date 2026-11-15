import fitz
import re


def clean_cell(value) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def extract_pdf_text(pdf_path: str) -> str:
    document = fitz.open(pdf_path)
    extracted_pages = []

    for page in document:
        page_text = page.get_text("text")

        if page_text:
            extracted_pages.append(page_text)

    document.close()

    return "\n".join(extracted_pages).strip()


def is_key_value_table(rows: list) -> bool:
    """Strictly detect key-value tables (2-column label-value pairs only).
    
    A 17-column GPS table should NOT be classified as key-value.
    Only mark as key-value if:
    - Table has exactly 2 columns (label | value)
    - OR all rows are consistently pairs in even-positioned columns
    """
    if not rows or len(rows) < 2:
        return False

    known_labels = {
        "bus no", "bus number", "vehicle no", "vehicle number",
        "unit no", "fleet no", "grouping", "route",
        "beginning", "start", "start time", "start date", "departure",
        "initial location", "origin", "from",
        "end", "ending", "end time", "end date", "arrival",
        "final location", "destination", "to",
        "duration", "total time", "total minutes", "total mins",
        "in motion", "move time", "moving time",
        "idling", "idle", "idle time",
        "engine hours", "mileage", "location", "coordinates",
        "description", "remarks", "comments", "notes",
    }

    # Check if all rows have exactly 2 columns (clear label-value format)
    if all(len(row) == 2 for row in rows if row):
        # With 2-column format, check if left column has labels
        label_count = 0
        for row in rows:
            if row and len(row) >= 2:
                label = clean_cell(row[0]).lower()
                normalized = re.sub(r"[^\w]+", " ", label).strip()
                if normalized in known_labels:
                    label_count += 1
        
        # If 80%+ of rows have known labels in column 0, it's key-value
        if rows and label_count >= len(rows) * 0.8:
            return True
    
    # For multi-column tables, never classify as key-value
    # (prevents GPS 17-column tables from being misclassified)
    return False


def extract_pdf_key_value_table(rows: list) -> dict:
    result = {}

    for row in rows:
        if not row:
            continue

        for index in range(0, len(row) - 1, 2):
            label = clean_cell(row[index])
            value = clean_cell(row[index + 1])

            if not label or not value:
                continue

            label_key = re.sub(r"[^\w]+", " ", label)
            label_key = re.sub(r"\s+", " ", label_key).strip().lower()

            result[label_key] = value

    return result


def extract_pdf_rows(pdf_path: str) -> tuple[list, str]:
    """Extract table rows from PDF with proper table grouping.
    
    Returns structured rows with page/table grouping to preserve context.
    Detects key-value vs standard data tables correctly.
    """
    document = fitz.open(pdf_path)

    rows = []
    found_standard_table = False
    found_key_value_table = False

    for page_number, page in enumerate(document, start=1):
        tables = page.find_tables()

        for table_number, table in enumerate(tables, start=1):
            raw_rows = table.extract()

            if not raw_rows:
                continue

            cleaned_rows = []

            for row in raw_rows:
                cleaned_row = [clean_cell(cell) for cell in row]

                if any(cleaned_row):
                    cleaned_rows.append(cleaned_row)

            if not cleaned_rows:
                continue

            # Check if this table is key-value format (2-column label-value pairs)
            if is_key_value_table(cleaned_rows):
                key_value_data = extract_pdf_key_value_table(cleaned_rows)

                if key_value_data:
                    rows.append({
                        "page": page_number,
                        "table": table_number,
                        "type": "key_value",
                        "values": key_value_data,
                    })

                    found_key_value_table = True

                continue

            # Otherwise treat as standard data table (with headers and data rows)
            for row in cleaned_rows:
                rows.append({
                    "page": page_number,
                    "table": table_number,
                    "type": "standard",
                    "values": row,
                })

            found_standard_table = True

    document.close()

    if found_standard_table:
        table_type = "standard_data_table"
    elif found_key_value_table:
        table_type = "key_value_table"
    else:
        table_type = None

    return rows, table_type