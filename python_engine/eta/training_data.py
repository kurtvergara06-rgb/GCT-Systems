"""Build the ETA / trip-duration training dataset from verified GCT GPS data.

Each sample is one completed GPS trip whose batch upload is explicitly marked
``data_origin=genuine``. The target is the measured trip duration in minutes.
Only values known before departure are model features.

Demo, sample, generated, synthetic, unknown, and unclassified batch uploads are
excluded at the SQL boundary. This is intentionally fail-closed: historical
rows must be explicitly verified before they can train a production model.
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Dict, List

import pandas as pd

from .config import training_data_paths
from operation_ai.ml.database import DbConnection

logger = logging.getLogger(__name__)

ETA_TARGET = "actual_trip_duration"

ETA_FEATURE_COLUMNS: List[str] = [
    "route_encoded",
    "distance_km",
    "route_estimated_time_minutes",
    "departure_hour",
    "day_of_week",
    "is_weekend",
    "shift_encoded",
    "bus_no_encoded",
]

ETA_TRACE_COLUMNS: List[str] = [
    "gps_record_id",
    "route",
    "bus_no",
    "beginning_at",
    "data_origin",
]

SHIFT_MAP: Dict[str, int] = {
    "morning": 0,
    "afternoon": 1,
    "night": 2,
    "swing": 3,
}


def _normalize_route(value: object) -> str:
    return " ".join(str(value or "").strip().lower().split())


def fetch_trip_outcomes(db: DbConnection) -> pd.DataFrame:
    """Load only verified genuine completed GPS trips for ETA training.

    Records longer than 720 minutes are excluded because they are likely
    full-shift activity periods rather than individual shuttle trips. The query
    is read-only and additionally requires an explicit genuine provenance flag
    on the owning batch upload.
    """
    sql = """
        SELECT
            g.id AS gps_record_id,
            g.bus_no,
            g.`grouping` AS route,
            g.beginning_at,
            g.duration_minutes,
            ts.shift,
            ts.departure_time,
            sr.distance_km,
            sr.estimated_time_minutes AS route_estimated_time_minutes,
            bu.data_origin
        FROM gps_trip_records g
        INNER JOIN batch_uploads bu ON bu.id = g.batch_upload_id
        LEFT JOIN trip_assignments ta ON ta.id = g.trip_assignment_id
        LEFT JOIN trip_schedules ts ON ts.id = ta.trip_schedule_id
        LEFT JOIN shuttle_routes sr ON sr.id = ts.shuttle_route_id
        WHERE g.duration_minutes IS NOT NULL
          AND g.duration_minutes > 0
          AND g.duration_minutes <= 720
          AND LOWER(TRIM(COALESCE(bu.data_origin, ''))) = 'genuine'
    """
    return db.query_df(sql)


def build_route_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Capture per-route distance and operator baseline at training time."""
    metadata: Dict[str, Dict[str, float]] = {}
    for row in df.itertuples(index=False):
        route = str(getattr(row, "route", "") or "").strip()
        if not route or route in metadata:
            continue
        metadata[route] = {
            "distance_km": float(getattr(row, "distance_km", -1.0) or -1.0),
            "estimated_time_minutes": float(
                getattr(row, "route_estimated_time_minutes", -1.0) or -1.0
            ),
        }
    return metadata


def build_route_encodings(df: pd.DataFrame) -> Dict[str, int]:
    """Persist the exact normalized route encoding used by the trainer."""
    if "route" not in df.columns:
        return {}
    routes = sorted(
        {
            _normalize_route(value)
            for value in df["route"].dropna().tolist()
            if _normalize_route(value)
        }
    )
    return {route: idx for idx, route in enumerate(routes)}


def build_bus_encodings(df: pd.DataFrame) -> Dict[str, int]:
    """Persist stable bus encodings used by both training and inference."""
    if "bus_no" not in df.columns:
        return {}
    buses = sorted(df["bus_no"].dropna().astype(str).str.strip().unique().tolist())
    return {bus: idx for idx, bus in enumerate(buses) if bus}


def build_dataset(df: pd.DataFrame) -> pd.DataFrame:
    """Turn raw verified GPS rows into the feature matrix and real target."""
    if df.empty or "route" not in df.columns:
        return pd.DataFrame(columns=ETA_FEATURE_COLUMNS + ETA_TRACE_COLUMNS + [ETA_TARGET])

    out = df.copy()
    out["duration_minutes"] = pd.to_numeric(out["duration_minutes"], errors="coerce")
    out["distance_km"] = pd.to_numeric(out["distance_km"], errors="coerce")
    out["route_estimated_time_minutes"] = pd.to_numeric(
        out["route_estimated_time_minutes"], errors="coerce"
    )

    out["beginning_at"] = pd.to_datetime(out["beginning_at"], errors="coerce")
    out["departure_hour"] = out["beginning_at"].dt.hour.fillna(-1.0).astype(float)
    out["day_of_week"] = out["beginning_at"].dt.dayofweek.fillna(-1.0).astype(float)
    out["is_weekend"] = (out["day_of_week"] >= 5).astype(float)
    out.loc[out["beginning_at"].isna(), "is_weekend"] = -1.0

    out["shift_encoded"] = (
        out["shift"].astype(str).str.lower().str.strip().map(SHIFT_MAP).fillna(-1.0)
    )
    out.loc[out["beginning_at"].isna(), "shift_encoded"] = -1.0

    route_map = build_route_encodings(out)
    out["route_encoded"] = out["route"].map(
        lambda value: float(route_map.get(_normalize_route(value), -1.0))
    )

    bus_map = build_bus_encodings(out)
    out["bus_no_encoded"] = (
        out["bus_no"].astype(str).str.strip().map(bus_map).fillna(-1.0)
    )

    out["distance_km"] = out["distance_km"].fillna(-1.0)
    out["route_estimated_time_minutes"] = out["route_estimated_time_minutes"].fillna(-1.0)
    out[ETA_TARGET] = out["duration_minutes"].clip(lower=1.0)

    if "data_origin" not in out.columns:
        out["data_origin"] = "unknown"
    out["data_origin"] = out["data_origin"].fillna("unknown").astype(str).str.strip().str.lower()

    trace = pd.DataFrame(
        {
            "gps_record_id": out.get("gps_record_id"),
            "route": out["route"],
            "bus_no": out["bus_no"],
            "beginning_at": out["beginning_at"],
            "data_origin": out["data_origin"],
        }
    )

    result = pd.concat([out[ETA_FEATURE_COLUMNS], trace, out[[ETA_TARGET]]], axis=1)
    return result.dropna(subset=ETA_FEATURE_COLUMNS + [ETA_TARGET]).reset_index(drop=True)


def write_training_csv(
    db: DbConnection,
    out_path: Path | None = None,
) -> Dict[str, object]:
    """Build the provenance-filtered ETA dataset and persist it to CSV."""
    out_path = out_path or training_data_paths()["csv"]
    out_path.parent.mkdir(parents=True, exist_ok=True)
    raw = fetch_trip_outcomes(db)
    dataset = build_dataset(raw)
    dataset.to_csv(out_path, index=False)

    origins = (
        sorted(dataset["data_origin"].dropna().astype(str).unique().tolist())
        if not dataset.empty and "data_origin" in dataset.columns
        else []
    )
    summary = {
        "rows": int(len(dataset)),
        "distinct_routes": int(dataset["route"].nunique()) if not dataset.empty else 0,
        "distinct_buses": int(dataset["bus_no"].nunique()) if not dataset.empty else 0,
        "data_origins": origins,
        "target_min": float(dataset[ETA_TARGET].min()) if not dataset.empty else 0.0,
        "target_max": float(dataset[ETA_TARGET].max()) if not dataset.empty else 0.0,
        "target_mean": float(dataset[ETA_TARGET].mean()) if not dataset.empty else 0.0,
        "csv_path": str(out_path),
        "dataset": dataset,
    }
    logger.info(
        "Saved ETA training data (%d rows, %d routes, origins=%s) to %s",
        len(dataset),
        summary["distinct_routes"],
        origins,
        out_path,
    )
    return summary
