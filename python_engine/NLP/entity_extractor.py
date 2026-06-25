import re
import spacy


nlp = spacy.load("en_core_web_sm")


def clean_value(value):
    if not value:
        return None

    value = re.sub(r"\s+", " ", value).strip()
    return value.strip(" .,:;-")


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
    doc = nlp(text)

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

    if not bus_no:
        bus_no = find_first_match([
            r"\b(?:Bus|Vehicle|Unit)\s+(?:No\.?\s*)?([A-Za-z0-9-]+)\b",
        ], text)

    grouping = extract_label_value(text, [
        "Grouping",
        "Route",
        "Trip Route",
    ])

    if not grouping:
        grouping = find_first_match([
            r"\bunder the\s+(.+?)\s+grouping\b",
            r"\broute\s+(?:is|was|of)?\s*([A-Za-z]+(?:\s*-\s*[A-Za-z]+)+)",
        ], text)

    initial_location = extract_label_value(text, [
        "Initial Location",
        "Start Location",
        "Origin",
    ])

    if not initial_location:
        initial_location = find_first_match([
            r"\btravelled from\s+(.+?)\s+to\s+",
            r"\btraveled from\s+(.+?)\s+to\s+",
            r"\bfrom\s+(.+?)\s+to\s+",
        ], text)

    final_location = extract_label_value(text, [
        "Final Location",
        "End Location",
        "Destination",
    ])

    if not final_location:
        final_location = find_first_match([
            r"\btravelled from\s+.+?\s+to\s+(.+?)(?:\s+under|\s+on|\s+at|\.|$)",
            r"\btraveled from\s+.+?\s+to\s+(.+?)(?:\s+under|\s+on|\s+at|\.|$)",
            r"\bto\s+(.+?)(?:\s+under|\s+on|\s+at|\.|$)",
        ], text)

    beginning = extract_label_value(text, [
        "Beginning",
        "Start Time",
        "Start Date",
        "Departure",
    ])

    if not beginning:
        beginning = find_first_match([
            r"\bstarted on\s+(.+?)\s+and ended",
            r"\bdeparted on\s+(.+?)(?:\s+and|\.)",
        ], text)

    ending = extract_label_value(text, [
        "End",
        "End Time",
        "Arrival",
    ])

    if not ending:
        ending = find_first_match([
            r"\bended at\s+(.+?)(?:\.|$)",
            r"\barrived at\s+(.+?)(?:\.|$)",
        ], text)

    mileage = extract_label_value(text, [
        "Mileage",
        "Distance",
    ])

    if not mileage:
        mileage = find_first_match([
            r"([\d,.]+)\s*(?:km|kilometers?)\b",
        ], text)

    idling = extract_label_value(text, [
        "Idling",
        "Idle Time",
        "Idle Duration",
    ])

    if not idling:
        idling = find_first_match([
            r"([\d,.]+)\s*(?:mins?|minutes?)\s+idling",
            r"idling\s+(?:time\s+of\s+)?([\d,.]+\s*(?:mins?|minutes?))",
        ], text)

    engine_hours = extract_label_value(text, [
        "Engine Hours",
        "Engine Hour",
    ])

    if not engine_hours:
        engine_hours = find_first_match([
            r"([\d,.]+)\s+engine hours?",
        ], text)

    total_time = extract_label_value(text, [
        "Total Time",
        "Duration",
    ])

    in_motion = extract_label_value(text, [
        "In Motion",
        "Moving Time",
    ])

    detected_locations = []

    for entity in doc.ents:
        if entity.label_ in ["GPE", "LOC", "FAC"]:
            detected_locations.append(entity.text)

    return {
        "bus_no": bus_no,
        "grouping": grouping,
        "initial_location": initial_location,
        "final_location": final_location,
        "beginning": beginning,
        "ending": ending,
        "mileage_km": mileage,
        "total_time": total_time,
        "in_motion": in_motion,
        "idling_time": idling,
        "engine_hours": engine_hours,
        "detected_locations": detected_locations,
    }