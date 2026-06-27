import re

try:
    import spacy

    nlp = spacy.load("en_core_web_sm")
except Exception:
    nlp = None


HEADER_ALIASES = {
    "bus_no": [
        "bus no",
        "bus number",
        "bus",
        "vehicle no",
        "vehicle number",
        "vehicle",
        "unit no",
        "unit number",
        "fleet no",
    ],
    "record_no": [
        "no",
        "record no",
        "record number",
        "trip no",
    ],
    "grouping": [
        "grouping",
        "groupings",
        "route group",
        "route",
    ],
    "trip_type": [
        "type",
        "trip type",
    ],
    "beginning": [
        "beginning",
        "start",
        "start time",
        "start date",
        "departure",
    ],
    "initial_location": [
        "initial location",
        "start location",
        "origin",
        "from",
    ],
    "ending": [
        "end",
        "ending",
        "end time",
        "end date",
        "arrival",
    ],
    "final_location": [
        "final location",
        "end location",
        "destination",
        "to",
    ],
    "duration_minutes": [
        "duration",
        "trip duration",
    ],
    "total_minutes": [
        "total time",
        "total minutes",
        "total mins",
    ],
    "in_motion_minutes": [
        "in motion",
        "move time",
        "moving time",
        "moving minutes",
    ],
    "idling_minutes": [
        "idling",
        "idle",
        "idle time",
    ],
    "mileage_km": [
        "mileage",
        "mileage in trips",
        "distance",
        "distance km",
    ],
    "engine_hours": [
        "engine hours",
        "engine hour",
    ],
    "location": [
        "location",
        "locations",
        "recorded location",
        "site",
    ],
    "coordinates": [
        "coordinates",
        "coordinate",
        "gps coordinates",
        "gps",
    ],
    "description": [
        "description",
        "remarks",
        "remarks comments",
        "comments",
        "comment",
        "notes",
    ],
}


def clean_value(value):
    if value is None:
        return None

    value = re.sub(r"\s+", " ", str(value)).strip()

    if not value:
        return None

    if value.lower() in {"n a", "na", "n/a", "-", "—"}:
        return None

    return value


def normalize_header(value):
    value = str(value or "").replace("\ufeff", "")
    value = re.sub(r"[^\w]+", " ", value).strip().lower()
    return re.sub(r"\s+", " ", value)


def normalize_number(value):
    value = clean_value(value)

    if not value:
        return None

    match = re.search(r"[\d,.]+", value)

    if not match:
        return None

    return match.group(0).replace(",", "")


def find_canonical_field(header):
    normalized_header = normalize_header(header)

    for field, aliases in HEADER_ALIASES.items():
        if normalized_header in aliases:
            return field

    return None


def is_valid_header_row(values):
    """Detect if a row is a header row for GPS trip records.
    
    GPS headers should have multiple known field names like:
    Bus No., No., Grouping, Type, Beginning, Initial Location, etc.
    
    Returns True if 60%+ of cells are recognized GPS field headers.
    """
    if not values:
        return False
    
    # Clean and normalize all values
    normalized_values = [normalize_header(value) for value in values if clean_value(value)]

    if len(normalized_values) < 3:
        return False

    # Count how many are recognized GPS field headers
    recognized_count = sum(
        1 for value in normalized_values
        if find_canonical_field(value) is not None
    )

    # Require 60%+ of columns to be recognized headers
    min_required = max(3, len(normalized_values) * 0.6)
    return recognized_count >= min_required


def is_header_like_row(values):
    normalized_values = [normalize_header(value) for value in values if clean_value(value)]

    if not normalized_values:
        return True

    recognized_count = sum(
        1 for value in normalized_values
        if find_canonical_field(value) is not None
    )

    return recognized_count >= max(3, len(normalized_values) * 0.45)


def format_record(row_data):
    return {
        "bus_no": clean_value(row_data.get("bus_no")),
        "record_no": clean_value(row_data.get("record_no")),
        "grouping": clean_value(row_data.get("grouping")),
        "trip_type": clean_value(row_data.get("trip_type")),
        "beginning": clean_value(row_data.get("beginning")),
        "initial_location": clean_value(row_data.get("initial_location")),
        "ending": clean_value(row_data.get("ending")),
        "final_location": clean_value(row_data.get("final_location")),
        "duration_minutes": normalize_number(row_data.get("duration_minutes")),
        "total_minutes": normalize_number(row_data.get("total_minutes")),
        "in_motion_minutes": normalize_number(row_data.get("in_motion_minutes")),
        "idling_minutes": normalize_number(row_data.get("idling_minutes")),
        "mileage_km": normalize_number(row_data.get("mileage_km")),
        "engine_hours": normalize_number(row_data.get("engine_hours")),
        "location": clean_value(row_data.get("location")),
        "coordinates": clean_value(row_data.get("coordinates")),
        "description": clean_value(row_data.get("description")),
        "raw_data": row_data,
    }


def infer_records_from_table_rows(rows):
    """Extract GPS records from structured table rows.
    
    Returns dict with:
    - records: list of GPS trip records
    - skipped_headers: number of header-like rows skipped
    - skipped_no_bus_no: number of data rows skipped due to missing Bus No.
    - debug_info: detailed diagnostic data
    """
    if not rows:
        return {
            "records": [],
            "skipped_headers": 0,
            "skipped_no_bus_no": 0,
            "debug_info": {"detected_header": None, "sample_rows": [], "num_standard_rows": 0, "num_key_value_rows": 0}
        }

    records = []
    skipped_headers = 0
    skipped_no_bus_no = 0
    detected_header = None
    sample_rows = []

    # Process key-value format tables
    key_value_rows = [row for row in rows if row.get("type") == "key_value"]

    for item in key_value_rows:
        values = item.get("values", {})

        if not isinstance(values, dict):
            continue

        mapped_data = {}

        for key, value in values.items():
            canonical_field = find_canonical_field(key)

            if canonical_field:
                mapped_data[canonical_field] = value

        if not mapped_data:
            continue

        bus_no = clean_value(mapped_data.get("bus_no"))

        if not bus_no:
            skipped_no_bus_no += 1
            continue

        records.append(format_record(mapped_data))

    # Process standard data table format (with headers)
    standard_rows = [row for row in rows if row.get("type") == "standard"]

    # Group rows by page/table to keep tables separate
    tables = {}

    for row in standard_rows:
        table_key = (
            row.get("page"),
            row.get("table"),
        )

        tables.setdefault(table_key, []).append(row.get("values", []))

    # Process each table independently
    for _, table_rows in tables.items():
        if not table_rows:
            continue

        # Find header row in this table
        header_index = None
        header_values = None

        for index, values in enumerate(table_rows):
            if is_valid_header_row(values):
                header_index = index
                header_values = values
                detected_header = [str(v) for v in values][:5]  # Store first 5 for debug
                break

        # If no header found, skip this table
        if header_index is None:
            continue

        # Map header column names to canonical field names
        mapped_headers = [
            find_canonical_field(header)
            for header in header_values
        ]

        # Process data rows after header
        for row_num, values in enumerate(table_rows[header_index + 1:]):
            # Skip empty rows
            if not any(clean_value(value) for value in values):
                continue

            # Skip rows that look like headers (e.g., repeated headers mid-table)
            if is_header_like_row(values):
                skipped_headers += 1
                continue

            # Map row values to canonical fields
            raw_row = {}
            mapped_row = {}

            for index, value in enumerate(values):
                if index >= len(mapped_headers):
                    continue

                header_field = mapped_headers[index]

                if not header_field:
                    continue

                raw_row[header_values[index]] = clean_value(value)
                mapped_row[header_field] = clean_value(value)

            if not mapped_row:
                continue

            # Enforce Bus No. requirement
            bus_no = clean_value(mapped_row.get("bus_no"))

            if not bus_no:
                skipped_no_bus_no += 1
                continue

            # Format and store record
            formatted_record = format_record(mapped_row)

            if not formatted_record["record_no"]:
                formatted_record["record_no"] = None

            formatted_record["raw_data"] = raw_row
            records.append(formatted_record)
            
            # Capture first 3 data rows for debug
            if len(sample_rows) < 3:
                sample_rows.append({"row_num": row_num, "bus_no": bus_no, "values_count": len(values)})

    return {
        "records": records,
        "skipped_headers": skipped_headers,
        "skipped_no_bus_no": skipped_no_bus_no,
        "debug_info": {
            "detected_header": detected_header,
            "sample_rows": sample_rows,
            "num_standard_rows": len(standard_rows),
            "num_key_value_rows": len(key_value_rows),
        }
    }


def find_first_match(patterns, text):
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE | re.MULTILINE)

        if match:
            return clean_value(match.group(1))

    return None


def extract_label_value(text, labels):
    for label in labels:
        escaped_label = re.escape(label)

        patterns = [
            rf"{escaped_label}\s*:\s*([^\n]+)",
            rf"{escaped_label}\s*\n\s*([^\n]+)",
        ]

        value = find_first_match(patterns, text)

        if value:
            return value

    return None


def extract_entities(text: str) -> dict:
    detected_locations = []

    if nlp is not None:
        doc = nlp(text)

        detected_locations = [
            entity.text
            for entity in doc.ents
            if entity.label_ in ["GPE", "LOC", "FAC"]
        ]

    bus_no = extract_label_value(text, [
        "Bus No.",
        "Bus No",
        "Bus Number",
        "Vehicle No.",
        "Vehicle No",
        "Vehicle Number",
        "Unit No.",
        "Unit No",
    ])

    grouping = extract_label_value(text, [
        "Grouping",
        "Groupings",
        "Route",
    ])

    return {
        "success": True,
        "bus_no": bus_no,
        "grouping": grouping,
        "trip_type": None,
        "initial_location": None,
        "final_location": None,
        "beginning": None,
        "ending": None,
        "duration_minutes": None,
        "total_minutes": None,
        "in_motion_minutes": None,
        "idling_minutes": None,
        "mileage_km": None,
        "engine_hours": None,
        "location": None,
        "coordinates": None,
        "description": None,
        "detected_locations": detected_locations,
    }