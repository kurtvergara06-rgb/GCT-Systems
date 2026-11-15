"""Build a real fuel-consumption training dataset from the Laravel database.

Each sample = one REAL fuel report that is linked (via ``gps_trip_record_id``)
to a completed GPS trip. The target is that trip's measured fuel consumed in
liters (``fuel_reports.fuel_liters``). Features are the real operational
measurements recorded for that trip:

    route_encoded          - route category (from GPS grouping)
    bus_no_encoded         - bus identity
    distance_km            - real GPS distance (fuel_reports, GPS-sourced)
    trip_duration_minutes  - trip clock time (GPS duration_minutes)
    in_motion_minutes      - trip time actually moving
    idling_minutes         - trip time idling
    engine_on_hours        - engine-hours recorded for the trip
    average_speed_kmh      - distance / (trip_duration / 60)
    departure_hour         - hour of departure (0-23)
    day_of_week            - 0 (Monday) .. 6 (Sunday)
    is_weekend             - 1 when day_of_week is Sat/Sun else 0

REJECTED FIELDS (target leakage / unavailable / duplicates / constants):
    km_per_liter            - DERIVED DIRECTLY FROM THE LABEL (distance / fuel).
                              Including it would be full target leakage.
    total_minutes           - identical to duration_minutes on every row
                              (verified: in_motion + idling == duration).
    mileage_km              - identical to fuel_reports.distance_km (GPS-sourced).
    severity                - trip-quality classification derived from idling /
                              motion thresholds; a proxy of the idling inputs.
    shift / route schedule
        distance / time     - NOT AVAILABLE: the gps -> assignment -> schedule
                              -> route join has zero coverage on fuel rows.
    status, distance_source - constant across all real rows ('Completed' / 'GPS').
    driver_name / remarks /
        manual_distance_... - free text / exception notes, no predictive signal.
    report_date             - temporal identity; would let the model memorize dates.

The dataset is saved to CSV so training runs are reproducible and reviewers
can inspect exactly what was learned from. No fake or invented samples are
ever generated.
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Dict, List

import pandas as pd

from .config import training_data_paths
from operation_ai.ml.database import DbConnection

logger = logging.getLogger(__name__)

FUEL_TARGET = "fuel_liters"

FUEL_FEATURE_COLUMNS: List[str] = [
    "route_encoded",
    "bus_no_encoded",
    "distance_km",
    "trip_duration_minutes",
    "in_motion_minutes",
    "idling_minutes",
    "engine_on_hours",
    "average_speed_kmh",
    "departure_hour",
    "day_of_week",
    "is_weekend",
]

# Kept for reviewer traceability; NOT used as model input.
FUEL_TRACE_COLUMNS: List[str] = [
    "fuel_report_id",
    "gps_record_id",
    "route",
    "bus_no",
    "beginning_at",
]


def fetch_fuel_outcomes(db: DbConnection) -> pd.DataFrame:
    """Load real fuel reports joined to their linked GPS trip.

    Every returned row has a real measured fuel volume plus the operational
    trip measurements needed for feature engineering. Read-only; the query
    only ever reads from the database.
    """
    sql = """
        SELECT
            f.id AS fuel_report_id,
            f.report_date,
            f.bus_no,
            f.distance_km,
            f.fuel_liters,
            g.id AS gps_record_id,
            g.`grouping` AS route,
            g.beginning_at,
            g.duration_minutes,
            g.in_motion_minutes,
            g.idling_minutes,
            g.engine_hours
        FROM fuel_reports f
        INNER JOIN gps_trip_records g ON g.id = f.gps_trip_record_id
        WHERE f.fuel_liters IS NOT NULL
          AND f.fuel_liters > 0
          AND f.distance_km IS NOT NULL
          AND f.distance_km > 0
    """
    return db.query_df(sql)


def build_route_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Capture per-route measured distance statistics at training time.

    The prediction service resolves a missing distance from this map instead
    of hitting the database at request time, keeping inference side-effect
    free. Values come from the real training distribution, never from the label.
    """
    metadata: Dict[str, Dict[str, float]] = {}
    for route, group in df.groupby("route"):
        route = str(route or "").strip()
        if not route or route in metadata:
            continue
        distances = pd.to_numeric(group["distance_km"], errors="coerce").dropna()
        if distances.empty:
            continue
        metadata[route] = {
            "distance_km_mean": float(distances.mean()),
            "distance_km_min": float(distances.min()),
            "distance_km_max": float(distances.max()),
        }
    return metadata


def build_bus_encodings(df: pd.DataFrame) -> Dict[str, int]:
    """Label-encode observed bus numbers in sorted order (stable across runs)."""
    buses = sorted(df["bus_no"].dropna().astype(str).str.strip().unique().tolist())
    return {bus: idx for idx, bus in enumerate(buses)}


def build_dataset(df: pd.DataFrame) -> pd.DataFrame:
    """Turn raw fuel-report rows into the feature matrix + the real fuel target."""
    if df.empty:
        return pd.DataFrame(columns=FUEL_FEATURE_COLUMNS + FUEL_TRACE_COLUMNS + [FUEL_TARGET])

    out = df.copy()

    # Normalize numeric columns.
    for col in [
        "distance_km",
        "duration_minutes",
        "in_motion_minutes",
        "idling_minutes",
        "engine_hours",
    ]:
        out[col] = pd.to_numeric(out[col], errors="coerce")

    # Real trip measurements.
    out["trip_duration_minutes"] = out["duration_minutes"]
    out["engine_on_hours"] = out["engine_hours"]
    out["average_speed_kmh"] = (
        out["distance_km"] / (out["trip_duration_minutes"] / 60.0)
    ).where(out["trip_duration_minutes"] > 0, other=-1.0)

    out["beginning_at"] = pd.to_datetime(out["beginning_at"], errors="coerce")
    out["departure_hour"] = out["beginning_at"].dt.hour.fillna(-1.0).astype(float)
    out["day_of_week"] = out["beginning_at"].dt.dayofweek.fillna(-1.0).astype(float)
    out["is_weekend"] = (out["day_of_week"] >= 5).astype(float)
    out.loc[out["beginning_at"].isna(), "is_weekend"] = -1.0

    # Ordered label-encoding so the mapping stays stable across runs.
    routes = sorted(out["route"].dropna().astype(str).str.strip().unique().tolist())
    route_map = {route: idx for idx, route in enumerate(routes)}
    out["route_encoded"] = out["route"].astype(str).str.strip().map(route_map).fillna(-1.0)

    buses = sorted(out["bus_no"].dropna().astype(str).str.strip().unique().tolist())
    bus_map = {bus: idx for idx, bus in enumerate(buses)}
    out["bus_no_encoded"] = out["bus_no"].astype(str).str.strip().map(bus_map).fillna(-1.0)

    # Missing operational context is encoded as -1 (unknown) rather than invented.
    out["distance_km"] = out["distance_km"].fillna(-1.0)
    out["trip_duration_minutes"] = out["trip_duration_minutes"].fillna(-1.0)
    out["in_motion_minutes"] = out["in_motion_minutes"].fillna(-1.0)
    out["idling_minutes"] = out["idling_minutes"].fillna(-1.0)
    out["engine_on_hours"] = out["engine_on_hours"].fillna(-1.0)

    out[FUEL_TARGET] = pd.to_numeric(out[FUEL_TARGET], errors="coerce").clip(lower=0.0)

    trace = pd.DataFrame(
        {
            "fuel_report_id": out.get("fuel_report_id"),
            "gps_record_id": out.get("gps_record_id"),
            "route": out["route"],
            "bus_no": out["bus_no"],
            "beginning_at": out["beginning_at"],
        }
    )

    result = pd.concat(
        [out[FUEL_FEATURE_COLUMNS], trace, out[[FUEL_TARGET]]],
        axis=1,
    )
    # A sample is only usable when every feature and the real label are present.
    result = result.dropna(subset=FUEL_FEATURE_COLUMNS + [FUEL_TARGET]).reset_index(drop=True)
    return result


def write_training_csv(
    db: DbConnection,
    out_path: Path | None = None,
) -> Dict[str, object]:
    """Build the real fuel dataset and persist it to CSV.

    Returns a summary dict (row count, route/bus spread, target stats) and the
    full DataFrame under the key ``dataset`` for in-process reuse.
    """
    out_path = out_path or training_data_paths()["csv"]
    raw = fetch_fuel_outcomes(db)
    dataset = build_dataset(raw)
    dataset.to_csv(out_path, index=False)

    summary = {
        "rows": int(len(dataset)),
        "distinct_routes": int(dataset["route"].nunique()) if not dataset.empty else 0,
        "distinct_buses": int(dataset["bus_no"].nunique()) if not dataset.empty else 0,
        "target_min": float(dataset[FUEL_TARGET].min()) if not dataset.empty else 0.0,
        "target_max": float(dataset[FUEL_TARGET].max()) if not dataset.empty else 0.0,
        "target_mean": float(dataset[FUEL_TARGET].mean()) if not dataset.empty else 0.0,
        "csv_path": str(out_path),
        "dataset": dataset,
    }
    if not dataset.empty:
        logger.info(
            "Saved fuel training data (%d rows, %d routes, %d buses) to %s",
            len(dataset),
            summary["distinct_routes"],
            summary["distinct_buses"],
            out_path,
        )
    return summary