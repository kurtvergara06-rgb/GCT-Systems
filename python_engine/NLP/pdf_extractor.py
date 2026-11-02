import fitz
import re
from typing import Any

from fastapi import HTTPException


KEY_VALUE_LABELS = {
    "record no",
    "record number",
    "trip no",
    "no",

    "bus no",
    "bus number",
    "bus",
    "vehicle no",
    "vehicle number",
    "vehicle",
    "unit no",
    "unit number",
    "unit",
    "fleet no",
    "fleet number",

    "grouping",
    "groupings",
    "group",
    "route",
    "route group",
    "route name",

    "type",
    "trip type",
    "trip",

    "beginning",
    "beginning at",
    "start",
    "start time",
    "start date",
    "departure",

    "initial location",
    "start location",
    "origin",
    "from",

    "end",
    "ending",
    "end time",
    "end date",
    "arrival",

    "final location",
    "end location",
    "destination",
    "to",

    "duration",
    "duration minutes",
    "trip duration",

    "total time",
    "total minutes",
    "total mins",
    "total duration",

    "in motion",
    "in motion minutes",
    "move time",
    "moving time",
    "moving minutes",
    "moving mins",

    "idling",
    "idle",
    "idle time",
    "idle duration",
    "idle minutes",
    "idle mins",

    "mileage",
    "mileage km",
    "mileage in trips",
    "distance",
    "distance km",
    "km",

    "engine hours",
    "engine hour",
    "hrs",

    "location",
    "location name",
    "recorded location",
    "site",

    "coordinates",
    "coordinate",
    "lat lng",
    "gps coordinates",
    "gps",

    "description",
    "remarks",
    "remarks comments",
    "comments",
    "comment",
    "notes",
}


def _open_pdf(pdf_path: str):
    """Open a PDF and surface malformed uploads as a client error."""
    try:
        return fitz.open(pdf_path)
    except fitz.FileDataError as error:
        raise HTTPException(
            status_code=400,
            detail="Corrupted or unreadable PDF document.",
        ) from error


def clean_cell(value: Any) -> str:
    """
    Convert a PDF table cell to a clean single-line string.
    """
    return re.sub(
        r"\s+",
        " ",
        str(value or ""),
    ).strip()


def normalize_label(value: Any) -> str:
    """
    Normalize field labels so values such as:

    Record No.
    Record No
    record-no

    all become:

    record no
    """
    value = clean_cell(value).lower()

    value = re.sub(
        r"[^\w]+",
        " ",
        value,
    )

    return re.sub(
        r"\s+",
        " ",
        value,
    ).strip()


def compact_row(row: list[Any]) -> list[str]:
    """
    Remove empty spacer cells produced by PyMuPDF.

    Example:

    [
        "Record No.",
        None,
        "GPS-0001",
        None,
        "Duration",
        "84 minutes",
        ""
    ]

    becomes:

    [
        "Record No.",
        "GPS-0001",
        "Duration",
        "84 minutes"
    ]
    """
    compacted: list[str] = []

    for cell in row:
        cleaned = clean_cell(cell)

        if cleaned:
            compacted.append(cleaned)

    return compacted


def extract_pdf_text(pdf_path: str) -> str:
    """
    Extract plain text from every page of the PDF.
    """
    document = _open_pdf(pdf_path)
    extracted_pages: list[str] = []

    try:
        for page in document:
            page_text = page.get_text("text")

            if page_text:
                extracted_pages.append(page_text)
    finally:
        document.close()

    return "\n".join(extracted_pages).strip()


def is_key_value_table(
    rows: list[list[str]],
) -> bool:
    """
    Detect key-value tables with one or more label-value pairs per row.

    Supported formats:

    Label | Value

    Label | Value | Label | Value

    Label | Value | Label | Value | Label | Value
    """
    if not rows:
        return False

    meaningful_rows = [
        compact_row(row)
        for row in rows
        if row and any(clean_cell(cell) for cell in row)
    ]

    if not meaningful_rows:
        return False

    recognized_labels = 0
    possible_labels = 0
    rows_with_labels = 0

    for row in meaningful_rows:
        if len(row) < 2:
            continue

        row_recognized = 0

        for index in range(0, len(row) - 1, 2):
            label = normalize_label(row[index])
            value = clean_cell(row[index + 1])

            if not label or not value:
                continue

            possible_labels += 1

            if label in KEY_VALUE_LABELS:
                recognized_labels += 1
                row_recognized += 1

        if row_recognized > 0:
            rows_with_labels += 1

    if possible_labels == 0:
        return False

    label_ratio = recognized_labels / possible_labels
    row_ratio = rows_with_labels / len(meaningful_rows)

    return (
        recognized_labels >= 2
        and label_ratio >= 0.50
        and row_ratio >= 0.50
    )


def extract_pdf_key_value_table(
    rows: list[list[str]],
) -> dict[str, str]:
    """
    Convert a key-value table into a normalized dictionary.

    Example output:

    {
        "record no": "GPS-0001",
        "bus no": "BUS-1001",
        "grouping": "Batangas-Lucena",
        "duration": "84 minutes"
    }
    """
    result: dict[str, str] = {}

    for original_row in rows:
        row = compact_row(original_row)

        if len(row) < 2:
            continue

        for index in range(0, len(row) - 1, 2):
            label = normalize_label(row[index])
            value = clean_cell(row[index + 1])

            if not label or not value:
                continue

            if label not in KEY_VALUE_LABELS:
                continue

            result[label] = value

    return result


def extract_pdf_rows(
    pdf_path: str,
) -> tuple[list[dict], str | None]:
    """
    Extract and classify every table found in a PDF.

    Returns:

    (
        extracted_rows,
        table_type
    )

    table_type may be:

    - key_value_table
    - standard_data_table
    - mixed_tables
    - None
    """
    document = _open_pdf(pdf_path)

    extracted_rows: list[dict] = []
    found_standard_table = False
    found_key_value_table = False

    try:
        for page_number, page in enumerate(
            document,
            start=1,
        ):
            table_finder = page.find_tables()

            tables = getattr(
                table_finder,
                "tables",
                [],
            )

            for table_number, table in enumerate(
                tables,
                start=1,
            ):
                raw_rows = table.extract()

                if not raw_rows:
                    continue

                cleaned_rows: list[list[str]] = []

                for row in raw_rows:
                    cleaned_row = [
                        clean_cell(cell)
                        for cell in row
                    ]

                    if any(cleaned_row):
                        cleaned_rows.append(
                            cleaned_row
                        )

                if not cleaned_rows:
                    continue

                if is_key_value_table(cleaned_rows):
                    key_value_data = (
                        extract_pdf_key_value_table(
                            cleaned_rows
                        )
                    )

                    if key_value_data:
                        extracted_rows.append({
                            "page": page_number,
                            "table": table_number,
                            "type": "key_value",
                            "values": key_value_data,
                        })

                        found_key_value_table = True

                    continue

                for row in cleaned_rows:
                    extracted_rows.append({
                        "page": page_number,
                        "table": table_number,
                        "type": "standard",
                        "values": row,
                    })

                found_standard_table = True

    finally:
        document.close()

    if (
        found_key_value_table
        and found_standard_table
    ):
        table_type = "mixed_tables"

    elif found_key_value_table:
        table_type = "key_value_table"

    elif found_standard_table:
        table_type = "standard_data_table"

    else:
        table_type = None

    return extracted_rows, table_type


def extract_engine_hours_records(pdf_path: str) -> dict:
    """Read the vehicle-grouped GPS Engine Hours PDF format.

    Keep interval records only. Vehicle totals, repeated headings, and
    inconsistent/missing numeric records are excluded and counted for review.
    The PDF is processed deterministically using PyMuPDF table extraction,
    within the existing FastAPI NLP workflow.
    """
    from datetime import datetime

    vehicle_title = re.compile(
        r"GPS Engine Hours Report\s*[-–]\s*(\d+)_([A-Z0-9-]+)",
        re.IGNORECASE,
    )
    record_number = re.compile(r"^\d+\.\d+$")
    datetime_format = "%m-%d-%Y %H:%M:%S"
    required_headers = [
        "no", "grouping", "beginning", "initial location", "end",
        "final location", "engine hours", "total time",
        "in motion", "idling", "mileage",
    ]
    records = []
    warnings = []
    seen = set()
    current_vehicle = None
    skipped_totals = 0
    skipped_invalid = 0

    def seconds(value):
        value = clean_cell(value).lower()
        if not value or value in {"-----", "-", "n/a"}:
            return None
        days = 0
        match = re.match(r"^(\d+)\s+days?\s+", value)
        if match:
            days = int(match.group(1))
            value = value[match.end():]
        clock = re.fullmatch(r"(\d+):(\d{2}):(\d{2})", value)
        if not clock:
            return None
        hours, minutes, secs = (int(chunk) for chunk in clock.groups())
        if minutes >= 60 or secs >= 60:
            return None
        return days * 86400 + hours * 3600 + minutes * 60 + secs

    document = _open_pdf(pdf_path)
    try:
        for page_number, page in enumerate(document, 1):
            header_text = page.get_text("text")[:350]
            title = vehicle_title.search(header_text)
            if title:
                current_vehicle = title.group(2).upper()

            # Continuation pages inherit their vehicle identifier.
            if not current_vehicle:
                continue

            for table in page.find_tables().tables:
                rows = table.extract() or []
                if not rows:
                    continue
                headings = [normalize_label(c) for c in rows[0]]
                if len(headings) != 11 or headings != required_headers:
                    continue

                for row in rows[1:]:
                    if len(row) != 11:
                        skipped_invalid += 1
                        continue
                    cells = [clean_cell(c) for c in row]
                    no, grouping, start, origin, end, destination, engine, total, motion, idle, mileage = cells
                    if grouping.lower() == "total" or no.lower() == "total" or not record_number.fullmatch(no):
                        skipped_totals += 1
                        continue
                    try:
                        beginning_at = datetime.strptime(start, datetime_format)
                        ending_at = datetime.strptime(end, datetime_format)
                    except ValueError:
                        skipped_invalid += 1
                        continue
                    engine_seconds = seconds(engine)
                    total_seconds = seconds(total)
                    motion_seconds = seconds(motion)
                    idle_seconds = seconds(idle)
                    mileage_value = re.fullmatch(r"(\d+(?:\.\d+)?)\s*km", mileage, re.IGNORECASE)
                    if (
                        ending_at < beginning_at
                        or any(v is None for v in (engine_seconds, total_seconds, motion_seconds, idle_seconds))
                        or mileage_value is None
                        or abs(motion_seconds + idle_seconds - total_seconds) > 60
                        or abs(engine_seconds - total_seconds) > 60
                    ):
                        skipped_invalid += 1
                        if len(warnings) < 25:
                            warnings.append({"page": page_number, "vehicle": current_vehicle, "row": no, "reason": "Missing or inconsistent source values"})
                        continue
                    key = (current_vehicle, beginning_at, ending_at, no)
                    if key in seen:
                        continue
                    seen.add(key)
                    records.append({
                        "bus_no": current_vehicle,
                        "record_no": f"{current_vehicle}-{no}",
                        "grouping": None,
                        "trip_type": "Engine Activity",
                        "beginning": beginning_at.strftime("%Y-%m-%d %H:%M:%S"),
                        "ending": ending_at.strftime("%Y-%m-%d %H:%M:%S"),
                        "initial_location": origin or None,
                        "final_location": destination or None,
                        "duration_minutes": round((ending_at - beginning_at).total_seconds() / 60, 2),
                        "total_minutes": round(total_seconds / 60, 2),
                        "in_motion_minutes": round(motion_seconds / 60, 2),
                        "idling_minutes": round(idle_seconds / 60, 2),
                        "mileage_km": float(mileage_value.group(1)),
                        "engine_hours": round(engine_seconds / 3600, 4),
                        "description": "GPS engine activity interval; not a scheduled trip",
                        "raw_data": {
                            "pdf_page": page_number,
                            "vehicle": current_vehicle,
                            "source_row": no,
                            "engine_hours_source": engine,
                            "total_time_source": total,
                            "in_motion_source": motion,
                            "idling_source": idle,
                            "mileage_source": mileage,
                        },
                    })
    finally:
        document.close()

    return {
        "records": records,
        "skipped_totals": skipped_totals,
        "skipped_invalid": skipped_invalid,
        "warnings": warnings,
    }
