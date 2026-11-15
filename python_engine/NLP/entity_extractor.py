import re

try:
    import spacy
    nlp = spacy.load("en_core_web_sm")
except Exception:  # pragma: no cover - environment fallback
    nlp = None


def clean_value(value):
    if value is None:
        return None

    if isinstance(value, (int, float)):
        return str(value)

    value = re.sub(r"\s+", " ", str(value)).strip()
    return value.strip(" .,:;-") or None


def normalize_header(value):
    if value is None:
        return ""

    value = str(value).replace("\ufeff", "")
    value = re.sub(r"[^\w]+", " ", value).strip().lower()
    return re.sub(r"\s+", " ", value)


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


def infer_records_from_table_rows(rows):
    if not rows:
        return []

    records = []

    key_value_rows = [r for r in rows if r.get("type") == "key_value"]
    standard_rows = [r for r in rows if r.get("type") == "standard"]

    if key_value_rows:
        merged_kv = {}
        for row in key_value_rows:
            if isinstance(row.get("values"), dict):
                merged_kv.update(row["values"])

        if merged_kv:
            records.append(merged_kv)
        return records

    if standard_rows:
        header_row = [normalize_header(value) for value in standard_rows[0]["values"]]

        for row in standard_rows[1:]:
            values = row["values"]
            if not any(value and str(value).strip() for value in values):
                continue

            row_data = {}
            for idx, header in enumerate(header_row):
                if idx < len(values):
                    row_data[header] = clean_value(values[idx])
            records.append(row_data)

        return records

    return []


def extract_entities(text: str) -> dict:
    if nlp is not None:
        doc = nlp(text)
        detected_locations = [entity.text for entity in doc.ents if entity.label_ in ["GPE", "LOC", "FAC"]]
    else:
        detected_locations = []

    bus_no = extract_label_value(text, [
        "Bus No.", "Bus No", "Bus Number", "Vehicle No.", "Vehicle No", "Vehicle Number", "Unit No.", "Unit No",
    ])

    if not bus_no:
        bus_no = find_first_match([r"\b(?:Bus|Vehicle|Unit)\s+(?:No\.?\s*)?([A-Za-z0-9-]+)\b"], text)

    grouping = extract_label_value(text, ["Grouping", "Route", "Trip Route"])
    if not grouping:
        grouping = find_first_match([r"\bunder the\s+(.+?)\s+grouping\b", r"\broute\s+(?:is|was|of)?\s*([A-Za-z]+(?:\s*-\s*[A-Za-z]+)+)"], text)

    initial_location = extract_label_value(text, ["Initial Location", "Start Location", "Origin"])
    if not initial_location:
        initial_location = find_first_match([r"\btravelled from\s+(.+?)\s+to\s+", r"\btraveled from\s+(.+?)\s+to\s+", r"\bfrom\s+(.+?)\s+to\s+"], text)

    final_location = extract_label_value(text, ["Final Location", "End Location", "Destination"])
    if not final_location:
        final_location = find_first_match([r"\btravelled from\s+.+?\s+to\s+(.+?)(?:\s+under|\s+on|\s+at|\.|$)", r"\btraveled from\s+.+?\s+to\s+(.+?)(?:\s+under|\s+on|\s+at|\.|$)", r"\bto\s+(.+?)(?:\s+under|\s+on|\s+at|\.|$)"], text)

    beginning = extract_label_value(text, ["Beginning", "Start Time", "Start Date", "Departure"])
    if not beginning:
        beginning = find_first_match([r"\bstarted on\s+(.+?)\s+and ended", r"\bdeparted on\s+(.+?)(?:\s+and|\.)"], text)

    ending = extract_label_value(text, ["End", "End Time", "Arrival"])
    if not ending:
        ending = find_first_match([r"\bended at\s+(.+?)(?:\.|$)", r"\barrived at\s+(.+?)(?:\.|$)"], text)

    mileage = extract_label_value(text, ["Mileage", "Distance"])
    if not mileage:
        mileage = find_first_match([r"([\d,.]+)\s*(?:km|kilometers?)\b"], text)

    idling = extract_label_value(text, ["Idling", "Idle Time", "Idle Duration"])
    if not idling:
        idling = find_first_match([r"([\d,.]+)\s*(?:mins?|minutes?)\s+idling", r"idling\s+(?:time\s+of\s+)?([\d,.]+\s*(?:mins?|minutes?))"], text)

    engine_hours = extract_label_value(text, ["Engine Hours", "Engine Hour"])
    if not engine_hours:
        engine_hours = find_first_match([r"([\d,.]+)\s+engine hours?"], text)

    total_time = extract_label_value(text, ["Total Time", "Duration"])
    if not total_time:
        total_time = find_first_match([r"([\d,.]+)\s*(?:mins?|minutes?)(?:\s+total)?\b"], text)

    in_motion = extract_label_value(text, ["In Motion", "Moving Time"])
    if not in_motion:
        in_motion = find_first_match([r"([\d,.]+)\s*(?:mins?|minutes?)\s+(?:in motion|moving)"], text)

    trip_type = extract_label_value(text, ["Type", "Trip Type"])
    if not trip_type:
        trip_type = find_first_match([r"(?:Trip Type|Type)\s*:\s*([^\n]+)"], text)

    location = extract_label_value(text, ["Location", "Site"])
    coordinates = extract_label_value(text, ["Coordinates", "GPS"])
    description = extract_label_value(text, ["Description", "Remarks", "Notes"])

    return {
        "success": True,
        "bus_no": bus_no,
        "grouping": grouping,
        "trip_type": trip_type,
        "initial_location": initial_location,
        "final_location": final_location,
        "beginning": beginning,
        "ending": ending,
        "duration_minutes": None,
        "total_minutes": total_time,
        "in_motion_minutes": in_motion,
        "idling_minutes": idling,
        "mileage_km": mileage,
        "engine_hours": engine_hours,
        "location": location,
        "coordinates": coordinates,
        "description": description,
        "detected_locations": detected_locations,
    }