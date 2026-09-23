"""Reviewable ingestion pipeline for extracted/annotated GCT records.

Records are staged first, then explicitly approved or rejected. Approved
records are copied into a promoted JSONL log that can later be used to build
reviewed training datasets. Runtime storage is configurable through
``NLP_INGESTION_DIR`` so tests and deployments do not need to write into the
source tree.
"""

from __future__ import annotations

import json
import os
import threading
import uuid
from copy import deepcopy
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

_LOCK = threading.RLock()


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def storage_dir() -> Path:
    configured = os.getenv("NLP_INGESTION_DIR")
    if configured:
        return Path(configured).expanduser().resolve()
    return Path(__file__).resolve().parents[1] / "training_data" / "nlp"


def _staging_path() -> Path:
    return storage_dir() / "staging.jsonl"


def _promoted_path() -> Path:
    return storage_dir() / "promoted.jsonl"


def _ensure_dir() -> Path:
    directory = storage_dir()
    directory.mkdir(parents=True, exist_ok=True)
    return directory


def _read_jsonl(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        return []
    rows: list[dict[str, Any]] = []
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line:
            continue
        try:
            value = json.loads(line)
        except json.JSONDecodeError:
            continue
        if isinstance(value, dict):
            rows.append(value)
    return rows


def _write_jsonl(path: Path, rows: list[dict[str, Any]]) -> None:
    _ensure_dir()
    temporary = path.with_suffix(path.suffix + ".tmp")
    payload = "".join(json.dumps(row, ensure_ascii=False, sort_keys=True) + "\n" for row in rows)
    temporary.write_text(payload, encoding="utf-8")
    temporary.replace(path)


def _append_jsonl(path: Path, row: dict[str, Any]) -> None:
    _ensure_dir()
    with path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(row, ensure_ascii=False, sort_keys=True) + "\n")


def stage_record(record: dict[str, Any], source_format: str) -> dict[str, Any]:
    """Stage one annotated record for human review."""
    staged_id = str(record.get("_staged_id") or uuid.uuid4())
    staged = {
        "id": staged_id,
        "status": "pending",
        "source_format": source_format,
        "staged_at": _now(),
        "reviewed_at": None,
        "reviewed_by": None,
        "labels": {},
        "record": deepcopy(record),
    }
    staged["record"]["_staged_id"] = staged_id

    with _LOCK:
        rows = _read_jsonl(_staging_path())
        if any(str(row.get("id")) == staged_id for row in rows):
            return next(row for row in rows if str(row.get("id")) == staged_id)
        rows.append(staged)
        _write_jsonl(_staging_path(), rows)

    return staged


def list_staged(status: str | None = None) -> list[dict[str, Any]]:
    """Return staged rows, optionally filtered by pending/approved/rejected."""
    with _LOCK:
        rows = _read_jsonl(_staging_path())
    if status is None:
        return rows
    normalized = status.strip().lower()
    return [row for row in rows if str(row.get("status", "")).lower() == normalized]


def review_record(
    staged_id: str,
    decision: str,
    *,
    labels: dict[str, Any] | None = None,
    reviewer: str | None = None,
) -> dict[str, Any]:
    """Approve or reject a staged record and persist the review decision."""
    decision = decision.strip().lower()
    if decision not in {"approved", "rejected"}:
        raise ValueError("decision must be 'approved' or 'rejected'")

    with _LOCK:
        rows = _read_jsonl(_staging_path())
        target: dict[str, Any] | None = None
        for row in rows:
            if str(row.get("id")) == str(staged_id):
                target = row
                break
        if target is None:
            raise KeyError(f"Unknown staged record: {staged_id}")

        target["status"] = decision
        target["reviewed_at"] = _now()
        target["reviewed_by"] = reviewer
        target["labels"] = deepcopy(labels or {})
        _write_jsonl(_staging_path(), rows)

        if decision == "approved":
            promoted = {
                "id": target["id"],
                "source_format": target.get("source_format"),
                "approved_at": target["reviewed_at"],
                "approved_by": reviewer,
                "labels": target["labels"],
                "record": target.get("record", {}),
            }
            existing = _read_jsonl(_promoted_path())
            if not any(str(row.get("id")) == str(target["id"]) for row in existing):
                _append_jsonl(_promoted_path(), promoted)

        return deepcopy(target)


def approve_record(
    staged_id: str,
    *,
    labels: dict[str, Any] | None = None,
    reviewer: str | None = None,
) -> dict[str, Any]:
    return review_record(staged_id, "approved", labels=labels, reviewer=reviewer)


def reject_record(
    staged_id: str,
    *,
    labels: dict[str, Any] | None = None,
    reviewer: str | None = None,
) -> dict[str, Any]:
    return review_record(staged_id, "rejected", labels=labels, reviewer=reviewer)


def promoted_records() -> list[dict[str, Any]]:
    with _LOCK:
        return _read_jsonl(_promoted_path())


def readiness() -> dict[str, object]:
    try:
        directory = _ensure_dir()
        writable = os.access(directory, os.W_OK)
    except OSError:
        directory = storage_dir()
        writable = False
    return {
        "ready": writable,
        "required": True,
        "storage": str(directory),
        "storage_writable": writable,
        "source": "reviewed_jsonl_pipeline",
    }
