"""Build a real ETA / trip-duration training dataset from the Laravel database.

Each sample = one REAL completed GPS trip. The target is that trip's actual
duration in minutes (`duration_minutes`). Features are limited strictly to
values that are known BEFORE the trip departs:

    route_encoded                 - route category (from GPS grouping)
    distance_km                   - nominal route distance (shuttle_routes)
    route_estimated_time_minutes  - operator route baseline (shuttle_routes)
    departure_hour                - hour of departure (0-23)
    day_of_week                   - 0 (Monday) .. 6 (Sunday)
    is_weekend                    - 1 when day_of_week is Sat/Sun else 0
    shift_encoded                 - Morning / Afternoon / Night
    bus_no_encoded                - bus identity (unknown encoded as -1)

KNOWN-AFTER-THE-TRIP FIELDS ARE NEVER USED (no target leakage):
    duration_minutes/total_minutes (the label itself),
    in_motion_minutes, idling_minutes, mileage_km, engine_hours, severity.

SAFETY FILTER:
    Records with duration > 720 minutes (12 h) are excluded from training.
    Such records are likely full-shift vehicle activity periods rather than
    individual shuttle trips.  The raw GPS records are never deleted; only
    the training SQL applies this filter.

The dataset is saved to CSV so training runs are reproducible and reviewers
can inspect exactly what was learned from.
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

# Kept for reviewer traceability; NOT used as model input.
ETA_TRACE_COLUMNS: List[str] = [
    "gps_record_id",
    "route",
    "bus_no",
    "beginning_at",
]

SHIFT_MAP: Dict[str, int] = {
    "morning": 0,
    "afternoon": 1,
    "night": 2,
    "swing": 3,
}


def fetch_trip_outcomes(db: DbConnection) -> pd.DataFrame:
    """Load real completed GPS trips joined to their schedule and route.

    Every returned row has a real measured duration plus the pre-trip context
    needed for feature engineering. Records with duration > 720 minutes (12 h)
    are excluded — they are likely full-shift vehicle activity periods rather
    than individual shuttle trips. Read-only; never writes to the database.
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
            sr.estimated_time_minutes AS route_estimated_time_minutes
        FROM gps_trip_records g
        LEFT JOIN trip_assignments ta ON ta.id = g.trip_assignment_id
        LEFT JOIN trip_schedules ts ON ts.id = ta.trip_schedule_id
        LEFT JOIN shuttle_routes sr ON sr.id = ts.shuttle_route_id
        WHERE g.duration_minutes IS NOT NULL
          AND g.duration_minutes > 0
          AND g.duration_minutes <= 720
    """
    return db.query_df(sql)


def build_route_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Capture per-route distance + operator baseline at training time.

    The prediction service resolves these values from this map instead of
    hitting the database at request time, keeping inference side-effect free.
    """
    metadata: Dict[str, Dict[str, float]] = {}
    for row in df.itertuples(index=False):
        route = str(getattr(row, "route") or "").strip()
        if not route or route in metadata:
            continue
        metadata[route] = {
            "distance_km": float(getattr(row, "distance_km") or -1.0),
            "estimated_time_minutes": float(
                getattr(row, "route_estimated_time_minutes") or -1.0
            ),
        }
    return metadata


def build_bus_encodings(df: pd.DataFrame) -> Dict[str, int]:
    """Label-encode observed bus numbers in sorted order (stable across runs)."""
    buses = sorted(df["bus_no"].dropna().astype(str).str.strip().unique().tolist())
    return {bus: idx for idx, bus in enumerate(buses)}


def build_dataset(df: pd.DataFrame) -> pd.DataFrame:
    """Turn raw GPS rows into the feature matrix + the real duration target."""
    if df.empty or "route" not in df.columns:
        return pd.DataFrame(columns=ETA_FEATURE_COLUMNS + ETA_TRACE_COLUMNS + [ETA_TARGET])

    out = df.copy()

    # Normalize numeric columns.
    out["duration_minutes"] = pd.to_numeric(out["duration_minutes"], errors="coerce")
    out["distance_km"] = pd.to_numeric(out["distance_km"], errors="coerce")
    out["route_estimated_time_minutes"] = pd.to_numeric(
        out["route_estimated_time_minutes"], errors="coerce"
    )

    # Real departure datetime is authoritative (schedule times mirror it).
    out["beginning_at"] = pd.to_datetime(out["beginning_at"], errors="coerce")
    out["departure_hour"] = out["beginning_at"].dt.hour.fillna(-1.0).astype(float)
    out["day_of_week"] = out["beginning_at"].dt.dayofweek.fillna(-1.0).astype(float)
    out["is_weekend"] = (out["day_of_week"] >= 5).astype(float)

    # Shift -> numeric (unknown shift encoded as -1, matching repo convention).
    out["shift_encoded"] = (
        out["shift"].astype(str).str.lower().map(SHIFT_MAP).fillna(-1.0)
    )
    out.loc[out["beginning_at"].isna(), "shift_encoded"] = -1.0

    # Ordered label-encoding so the mapping stays stable across runs.
    routes = sorted(out["route"].dropna().astype(str).str.strip().unique().tolist())
    route_map = {route: idx for idx, route in enumerate(routes)}
    out["route_encoded"] = out["route"].astype(str).str.strip().map(route_map).fillna(-1.0)

    buses = sorted(out["bus_no"].dropna().astype(str).str.strip().unique().tolist())
    bus_map = {bus: idx for idx, bus in enumerate(buses)}
    out["bus_no_encoded"] = out["bus_no"].astype(str).str.strip().map(bus_map).fillna(-1.0)

    # Missing pre-trip context is encoded as -1 (unknown) rather than invented.
    out["distance_km"] = out["distance_km"].fillna(-1.0)
    out["route_estimated_time_minutes"] = out["route_estimated_time_minutes"].fillna(-1.0)

    out[ETA_TARGET] = out["duration_minutes"].clip(lower=1.0)

    trace = pd.DataFrame(
        {
            "gps_record_id": out.get("gps_record_id"),
            "route": out["route"],
            "bus_no": out["bus_no"],
            "beginning_at": out["beginning_at"],
        }
    )

    result = pd.concat(
        [out[ETA_FEATURE_COLUMNS], trace, out[[ETA_TARGET]]],
        axis=1,
    )
    # A sample is only usable when every feature and the real label are present.
    result = result.dropna(subset=ETA_FEATURE_COLUMNS + [ETA_TARGET]).reset_index(drop=True)
    return result


def write_training_csv(
    db: DbConnection,
    out_path: Path | None = None,
) -> Dict[str, object]:
    """Build the real ETA dataset and persist it to CSV.

    Returns a summary dict (row count, route/bus spread, target stats) and the
    full DataFrame under the key ``dataset`` for in-process reuse.
    """
    out_path = out_path or training_data_paths()["csv"]
    raw = fetch_trip_outcomes(db)
    dataset = build_dataset(raw)
    dataset.to_csv(out_path, index=False)

    summary = {
        "rows": int(len(dataset)),
        "distinct_routes": int(dataset["route"].nunique()) if not dataset.empty else 0,
        "distinct_buses": int(dataset["bus_no"].nunique()) if not dataset.empty else 0,
        "target_min": float(dataset[ETA_TARGET].min()) if not dataset.empty else 0.0,
        "target_max": float(dataset[ETA_TARGET].max()) if not dataset.empty else 0.0,
        "target_mean": float(dataset[ETA_TARGET].mean()) if not dataset.empty else 0.0,
        "csv_path": str(out_path),
        "dataset": dataset,
    }
    if not dataset.empty:
        logger.info(
            "Saved ETA training data (%d rows, %d routes, %d buses) to %s",
            len(dataset),
            summary["distinct_routes"],
            summary["distinct_buses"],
            out_path,
        )
    return summary