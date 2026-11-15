import re
import spacy


nlp = spacy.load("en_core_web_sm")


def find_first_match(patterns: list[str], text: str):
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE)

        if match:
            return match.group(1).strip()

    return None


def extract_entities(text: str) -> dict:
    doc = nlp(text)

    bus_no = find_first_match(
        [
            r"(?:Bus No\.?|Bus Number|Vehicle No\.?|Vehicle Number|Unit No\.?|Unit)\s*[:#-]?\s*([A-Za-z0-9-]+)",
        ],
        text,
    )

    grouping = find_first_match(
        [
            r"(?:Grouping|Route|Trip Route)\s*:\s*(.+)",
        ],
        text,
    )

    initial_location = find_first_match(
        [
            r"(?:Initial Location|Start Location|Origin|From)\s*:\s*(.+)",
        ],
        text,
    )

    final_location = find_first_match(
        [
            r"(?:Final Location|End Location|Destination|To)\s*:\s*(.+)",
        ],
        text,
    )

    beginning = find_first_match(
        [
            r"(?:Beginning|Start Time|Start Date|Departure)\s*:\s*(.+)",
        ],
        text,
    )

    ending = find_first_match(
        [
            r"(?:End|End Time|Arrival)\s*:\s*(.+)",
        ],
        text,
    )

    mileage = find_first_match(
        [
            r"(?:Mileage|Distance)\s*:\s*([\d,.]+)",
        ],
        text,
    )

    idling = find_first_match(
        [
            r"(?:Idling|Idle Time|Idle Duration)\s*:\s*([\d,.]+\s*(?:mins?|minutes?|hours?|hrs?)?)",
        ],
        text,
    )

    engine_hours = find_first_match(
        [
            r"(?:Engine Hours|Engine Hour)\s*:\s*([\d,.]+)",
        ],
        text,
    )

    locations_from_spacy = [
        entity.text
        for entity in doc.ents
        if entity.label_ in ["GPE", "LOC", "FAC"]
    ]

    return {
        "bus_no": bus_no,
        "grouping": grouping,
        "initial_location": initial_location,
        "final_location": final_location,
        "beginning": beginning,
        "ending": ending,
        "mileage_km": mileage,
        "idling_time": idling,
        "engine_hours": engine_hours,
        "detected_locations": locations_from_spacy,
    }