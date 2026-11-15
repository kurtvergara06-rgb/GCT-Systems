"""Operational named-entity extraction for GCT GPS/incident text.

This module is REQUIRED by the Python engine.  It deliberately does not fake
ML readiness: deterministic domain patterns always work, while spaCy entities
are added only when ``en_core_web_sm`` is actually installed.
"""

from __future__ import annotations

import re
from typing import Iterable

try:
    import spacy

    try:
        _NLP = spacy.load("en_core_web_sm")
        _SPACY_READY = True
    except Exception:
        _NLP = None
        _SPACY_READY = False
except Exception:
    _NLP = None
    _SPACY_READY = False


_EVENT_PATTERNS: dict[str, tuple[str, ...]] = {
    "DELAY_EVENT": (
        r"\bdelay(?:ed|s)?\b",
        r"\blate\b",
        r"\bbehind schedule\b",
        r"\bslow(?:ed|down)?\b",
    ),
    "TRAFFIC_EVENT": (
        r"\btraffic\b",
        r"\bcongestion\b",
        r"\bgridlock\b",
        r"\bheavy traffic\b",
    ),
    "IDLING_EVENT": (
        r"\bidl(?:e|ing)\b",
        r"\bengine idle\b",
        r"\bwaiting with engine on\b",
    ),
    "ENGINE_ISSUE": (
        r"\bengine (?:issue|problem|failure|fault|overheat(?:ing|ed)?)\b",
        r"\boverheat(?:ing|ed)?\b",
        r"\bcheck engine\b",
        r"\bengine stalled?\b",
    ),
    "BREAKDOWN_EVENT": (
        r"\bbreakdown\b",
        r"\bbroke down\b",
        r"\bvehicle disabled\b",
        r"\bstranded\b",
    ),
    "SAFETY_EVENT": (
        r"\baccident\b",
        r"\bcollision\b",
        r"\bcrash\b",
        r"\bfire\b",
        r"\bunsafe\b",
        r"\bemergency\b",
    ),
}

_BUS_PATTERNS = (
    re.compile(r"\bBUS[-\s]?[A-Z0-9]{1,8}\b", re.IGNORECASE),
    re.compile(r"\b(?:bus|vehicle|unit)\s*(?:no\.?|number|#)?\s*[:#-]?\s*([A-Z]{0,4}-?\d{2,6})\b", re.IGNORECASE),
)

_ROUTE_PATTERN = re.compile(
    r"\b(?:route|grouping)\s*(?:code|name|no\.?|number|#)?\s*[:#-]?\s*"
    r"([^\n,;]{2,80})",
    re.IGNORECASE,
)

_FROM_TO_PATTERN = re.compile(
    r"\bfrom\s+([^\n,;]{2,80}?)\s+to\s+([^\n,;]{2,80}?)(?=[.;]|$)",
    re.IGNORECASE,
)


def _unique(values: Iterable[str]) -> list[str]:
    seen: set[str] = set()
    result: list[str] = []
    for raw in values:
        value = re.sub(r"\s+", " ", str(raw or "")).strip(" .,:;-\t\r\n")
        if not value:
            continue
        key = value.casefold()
        if key in seen:
            continue
        seen.add(key)
        result.append(value)
    return result


def _bus_entities(text: str) -> list[str]:
    values: list[str] = []
    for pattern in _BUS_PATTERNS:
        for match in pattern.finditer(text):
            value = match.group(1) if match.lastindex else match.group(0)
            value = re.sub(r"\s+", "", value.upper())
            if not value.startswith("BUS") and re.fullmatch(r"[A-Z]{0,4}-?\d{2,6}", value):
                value = value
            values.append(value)
    return _unique(values)


def _event_entities(text: str) -> list[dict[str, str]]:
    events: list[dict[str, str]] = []
    seen: set[tuple[str, str]] = set()
    for label, patterns in _EVENT_PATTERNS.items():
        for raw_pattern in patterns:
            for match in re.finditer(raw_pattern, text, flags=re.IGNORECASE):
                surface = match.group(0).strip()
                key = (label, surface.casefold())
                if key in seen:
                    continue
                seen.add(key)
                events.append({"label": label, "text": surface})
    return events


def extract_entities(text: str | None) -> dict[str, object]:
    """Extract operational entities from free text.

    Returned keys are stable so downstream severity/analytics code can depend
    on them even when the optional spaCy language model is unavailable.
    """
    text = str(text or "").strip()
    if not text:
        return {
            "bus": [],
            "route": [],
            "origin": [],
            "destination": [],
            "events": [],
            "locations": [],
            "organizations": [],
            "source": "domain_rules",
        }

    routes = [match.group(1) for match in _ROUTE_PATTERN.finditer(text)]
    origins: list[str] = []
    destinations: list[str] = []
    for match in _FROM_TO_PATTERN.finditer(text):
        origins.append(match.group(1))
        destinations.append(match.group(2))

    locations: list[str] = []
    organizations: list[str] = []
    source = "domain_rules"

    if _NLP is not None:
        try:
            doc = _NLP(text)
            for entity in doc.ents:
                if entity.label_ in {"GPE", "LOC", "FAC"}:
                    locations.append(entity.text)
                elif entity.label_ == "ORG":
                    organizations.append(entity.text)
            source = "domain_rules+spacy"
        except Exception:
            # Domain extraction remains valid even when spaCy fails on a
            # particular input.  We intentionally do not invent entities.
            pass

    return {
        "bus": _bus_entities(text),
        "route": _unique(routes),
        "origin": _unique(origins),
        "destination": _unique(destinations),
        "events": _event_entities(text),
        "locations": _unique(locations),
        "organizations": _unique(organizations),
        "source": source,
    }


def readiness() -> dict[str, object]:
    """Report real capability without claiming an unavailable spaCy model."""
    return {
        "ready": True,
        "required": True,
        "domain_rules": True,
        "spacy_model_ready": _SPACY_READY,
        "source": "domain_rules+spacy" if _SPACY_READY else "domain_rules",
    }
