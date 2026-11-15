"""Transparent operational severity classification for extracted GCT records.

This module is required at runtime. It does not pretend to be a trained ML
model: results explicitly identify ``operational_rules`` as their source.
A learned classifier can later replace or augment this implementation once
reviewed, genuine labels are available.
"""

from __future__ import annotations

import math
import re
from typing import Any


_HIGH_PATTERNS = {
    "accident": r"\baccident\b|\bcollision\b|\bcrash\b",
    "fire": r"\bfire\b|\bsmoke\b",
    "breakdown": r"\bbreakdown\b|\bbroke down\b|\bstranded\b|\bvehicle disabled\b",
    "engine_failure": r"\bengine failure\b|\bengine stalled?\b|\bengine fault\b",
    "safety": r"\bemergency\b|\bunsafe\b|\binjur(?:y|ed)\b",
}

_MEDIUM_PATTERNS = {
    "delay": r"\bdelay(?:ed|s)?\b|\blate\b|\bbehind schedule\b",
    "traffic": r"\btraffic\b|\bcongestion\b|\bgridlock\b",
    "overheating": r"\boverheat(?:ing|ed)?\b|\bcheck engine\b",
    "repair": r"\brepair\b|\bmaintenance required\b|\bwarning\b",
}


def _number(record: dict[str, Any], key: str) -> float | None:
    value = record.get(key)
    if value in (None, ""):
        return None
    try:
        number = float(str(value).replace(",", "").strip())
        return number if math.isfinite(number) else None
    except (TypeError, ValueError):
        return None


def _text(record: dict[str, Any]) -> str:
    parts = [
        record.get("description"),
        record.get("remarks"),
        record.get("grouping"),
        record.get("trip_type"),
        record.get("location"),
    ]
    return " ".join(str(value) for value in parts if value not in (None, "")).strip()


def predict_record(record: dict[str, Any]) -> dict[str, Any]:
    """Classify an operational record as Low, Medium, or High severity."""
    text = _text(record)
    signals: list[str] = []
    score = 0

    for name, pattern in _HIGH_PATTERNS.items():
        if re.search(pattern, text, flags=re.IGNORECASE):
            signals.append(name)
            score += 4

    for name, pattern in _MEDIUM_PATTERNS.items():
        if re.search(pattern, text, flags=re.IGNORECASE):
            signals.append(name)
            score += 2

    idling = _number(record, "idling_minutes")
    total = _number(record, "total_minutes") or _number(record, "duration_minutes")

    if idling is not None and idling >= 60:
        signals.append("extended_idling")
        score += 2

    if total is not None and total > 720:
        signals.append("activity_period_over_12h")
        score += 1

    if idling is not None and total and total > 0 and idling / total >= 0.60:
        signals.append("high_idling_ratio")
        score += 2

    label = "High" if score >= 4 else ("Medium" if score >= 2 else "Low")
    confidence = min(0.95, 0.58 + (0.07 * min(score, 5))) if score else 0.58

    return {
        "label": label,
        "confidence": round(confidence, 3),
        "score": score,
        "signals": sorted(set(signals)),
        "source": "operational_rules",
        "model_ready": False,
    }


def readiness() -> dict[str, object]:
    return {
        "ready": True,
        "required": True,
        "source": "operational_rules",
        "trained_model_ready": False,
        "message": (
            "Operational severity classification is active. A learned severity "
            "model is intentionally not claimed until reviewed genuine labels exist."
        ),
    }
