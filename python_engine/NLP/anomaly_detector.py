"""Operational anomaly detection for extracted GCT records.

The detector is always available and checks physically inconsistent or
operationally extreme combinations.  It reports ``operational_rules`` as its
source; it does not claim an unsupervised ML model when no genuine trained
artifact exists.
"""

from __future__ import annotations

import math
from typing import Any


def _number(record: dict[str, Any], key: str) -> float | None:
    value = record.get(key)
    if value in (None, ""):
        return None
    try:
        number = float(str(value).replace(",", "").strip())
        return number if math.isfinite(number) else None
    except (TypeError, ValueError):
        return None


def anomaly_details(record: dict[str, Any]) -> dict[str, Any]:
    """Return an explainable anomaly flag, score, and contributing signals."""
    duration = _number(record, "duration_minutes")
    total = _number(record, "total_minutes")
    moving = _number(record, "in_motion_minutes")
    idling = _number(record, "idling_minutes")
    mileage = _number(record, "mileage_km")
    engine_hours = _number(record, "engine_hours")

    score = 0.0
    signals: list[str] = []

    for name, value in (
        ("duration_minutes", duration),
        ("total_minutes", total),
        ("in_motion_minutes", moving),
        ("idling_minutes", idling),
        ("mileage_km", mileage),
        ("engine_hours", engine_hours),
    ):
        if value is not None and value < 0:
            signals.append(f"negative_{name}")
            score += 1.0

    effective_total = total if total is not None else duration

    if effective_total is not None:
        if effective_total > 1440:
            signals.append("activity_period_over_24h")
            score += 0.75
        elif effective_total > 720:
            signals.append("activity_period_over_12h")
            score += 0.30

        if moving is not None and moving > effective_total + 1:
            signals.append("moving_time_exceeds_total")
            score += 0.85

        if idling is not None and idling > effective_total + 1:
            signals.append("idling_time_exceeds_total")
            score += 0.85

        if idling is not None and effective_total >= 30 and idling / effective_total >= 0.75:
            signals.append("extreme_idling_ratio")
            score += 0.50

    if mileage is not None and mileage > 1500:
        signals.append("extreme_single_record_mileage")
        score += 0.55

    if engine_hours is not None and engine_hours > 24:
        signals.append("engine_hours_over_24")
        score += 0.55

    score = round(min(score, 1.0), 4)

    return {
        "is_anomaly": score >= 0.50,
        "anomaly_score": score,
        "signals": sorted(set(signals)),
        "source": "operational_consistency_rules",
        "model_ready": False,
    }


def readiness() -> dict[str, object]:
    return {
        "ready": True,
        "required": True,
        "source": "operational_consistency_rules",
        "trained_model_ready": False,
        "message": (
            "Explainable operational anomaly checks are active. A genuine "
            "unsupervised model is not claimed until suitable real history exists."
        ),
    }
