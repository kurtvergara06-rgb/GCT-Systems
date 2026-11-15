"""NER-aware severity classification for GCT operational records.

The classifier combines transparent operational rules with entities/events
extracted by :mod:`NLP.ner_extractor`.  It is deliberately labelled as a
rule-based source until a reviewed genuine-label model is trained.
"""

from __future__ import annotations

from typing import Any

from .ner_extractor import extract_entities
from .severity_predictor import predict_record as base_severity


_EVENT_WEIGHTS = {
    "SAFETY_EVENT": 5,
    "BREAKDOWN_EVENT": 4,
    "ENGINE_ISSUE": 3,
    "TRAFFIC_EVENT": 2,
    "DELAY_EVENT": 2,
    "IDLING_EVENT": 1,
}


def build_text(record: dict[str, Any]) -> str:
    fields = (
        "bus_no",
        "grouping",
        "trip_type",
        "initial_location",
        "final_location",
        "location",
        "description",
        "remarks",
    )
    return " | ".join(
        str(record.get(field)).strip()
        for field in fields
        if record.get(field) not in (None, "")
    )


def predict_record(record: dict[str, Any]) -> dict[str, Any]:
    base = base_severity(record)
    entities = extract_entities(build_text(record))
    event_labels = {
        str(event.get("label"))
        for event in entities.get("events", [])
        if isinstance(event, dict) and event.get("label")
    }

    event_score = sum(_EVENT_WEIGHTS.get(label, 0) for label in event_labels)
    combined_score = max(int(base.get("score", 0)), event_score)

    label = "High" if combined_score >= 4 else (
        "Medium" if combined_score >= 2 else "Low"
    )
    confidence = min(0.96, 0.60 + (0.06 * min(combined_score, 6)))

    signals = sorted(
        set(base.get("signals", []))
        | {label_name.lower() for label_name in event_labels}
    )

    return {
        "label": label,
        "confidence": round(confidence, 3),
        "score": combined_score,
        "signals": signals,
        "entities": entities,
        "source": "ner_operational_rules",
        "model_ready": False,
    }


def readiness() -> dict[str, object]:
    return {
        "ready": True,
        "required": True,
        "source": "ner_operational_rules",
        "trained_model_ready": False,
        "message": (
            "NER-aware severity classification is active without pretending "
            "that a genuine trained severity model exists."
        ),
    }
