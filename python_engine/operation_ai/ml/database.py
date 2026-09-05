"""Database access layer for the Operation AI training pipeline.

Connects to the Laravel MySQL database (read-only) so the trainer can build
real training datasets from actual historical records. No table is ever
modified by this module; training data is materialized to CSV files that are
then consumed by the training script.
"""

from __future__ import annotations

import logging
from typing import Dict, List, Optional

import pandas as pd
import pymysql

from .config import db_config

logger = logging.getLogger(__name__)


class DbConnection:
    """Thin wrapper around pymysql for read-only queries."""

    def __init__(self) -> None:
        self.cfg = db_config()
        self._conn = None

    def connect(self) -> None:
        if self._conn is not None:
            return
        self._conn = pymysql.connect(
            host=self.cfg["host"],
            port=self.cfg["port"],
            user=self.cfg["user"],
            password=self.cfg["password"],
            database=self.cfg["database"],
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )

    def close(self) -> None:
        if self._conn is not None:
            self._conn.close()
            self._conn = None

    def query(self, sql: str, params: Optional[tuple] = None) -> List[dict]:
        self.connect()
        with self._conn.cursor() as cursor:
            cursor.execute(sql, params or ())
            return list(cursor.fetchall())

    def query_df(self, sql: str, params: Optional[tuple] = None) -> pd.DataFrame:
        rows = self.query(sql, params)
        return pd.DataFrame(rows) if rows else pd.DataFrame()


def fetch_gps_trip_records(
    db: DbConnection,
    min_records: int = 30,
) -> pd.DataFrame:
    """Load the real GPS trip performance records used as bus training labels.

    Each row is a real completed trip by a bus with genuine performance
    measurements (mileage, motion, idling, engine hours, severity label).
    """
    sql = """
        SELECT
            id,
            bus_no,
            `grouping`,
            trip_type,
            beginning_at,
            duration_minutes,
            total_minutes,
            in_motion_minutes,
            idling_minutes,
            mileage_km,
            engine_hours,
            severity,
            source_format
        FROM gps_trip_records
        WHERE bus_no IS NOT NULL
          AND bus_no <> ''
    """
    df = db.query_df(sql)
    if df.empty:
        return df

    # Normalize numeric columns
    for col in [
        "duration_minutes",
        "total_minutes",
        "in_motion_minutes",
        "idling_minutes",
        "mileage_km",
        "engine_hours",
    ]:
        df[col] = pd.to_numeric(df[col], errors="coerce")

    # Normalize severity to a 0-1 label. GPS records carry the authority.
    df["severity"] = df["severity"].astype(str).str.lower()
    severity_map = {"normal": 1.0, "low": 0.75, "medium": 0.5, "warning": 0.5, "critical": 0.0, "high": 0.25}
    df["severity_label"] = df["severity"].map(severity_map).fillna(0.5)

    # Parse beginning_at to derive the hour for trip-context features.
    df["beginning_at"] = pd.to_datetime(df["beginning_at"], errors="coerce")
    df["hour"] = df["beginning_at"].dt.hour.fillna(-1)

    return df


def fetch_fuel_reports(db: DbConnection) -> pd.DataFrame:
    """Load real per-bus fuel efficiency history."""
    sql = """
        SELECT bus_no, km_per_liter, fuel_liters, distance_km, report_date
        FROM fuel_reports
    """
    df = db.query_df(sql)
    for col in ["km_per_liter", "fuel_liters", "distance_km"]:
        df[col] = pd.to_numeric(df[col], errors="coerce")
    return df


def fetch_job_orders(db: DbConnection) -> pd.DataFrame:
    """Load real per-bus maintenance history."""
    sql = """
        SELECT id, bus_no, status, start_date, completion_date, maintenance_type
        FROM job_orders
    """
    df = db.query_df(sql)
    df["start_date"] = pd.to_datetime(df["start_date"], errors="coerce")
    df["completion_date"] = pd.to_datetime(df["completion_date"], errors="coerce")
    return df


def fetch_bus_master(db: DbConnection) -> pd.DataFrame:
    """Load the master bus list with PMS values."""
    sql = """
        SELECT id, bus_no, status, capacity,
               latest_gps_km, next_pms_km, pms_interval_km
        FROM buses
    """
    df = db.query_df(sql)
    for col in ["capacity", "latest_gps_km", "next_pms_km", "pms_interval_km"]:
        df[col] = pd.to_numeric(df[col], errors="coerce")
    return df


def fetch_driver_attendance(db: DbConnection) -> pd.DataFrame:
    """Load real per-driver attendance history (the driver reliability signal)."""
    sql = """
        SELECT
            driver_id,
            driver_name,
            shift,
            attendance_date,
            status
        FROM driver_attendances
    """
    df = db.query_df(sql)
    df["attendance_date"] = pd.to_datetime(df["attendance_date"], errors="coerce")
    return df


def data_availability(db: DbConnection) -> Dict[str, int]:
    """Report the real extent of historical data found in the database."""
    gps = fetch_gps_trip_records(db)
    attendance = fetch_driver_attendance(db)
    fuel = fetch_fuel_reports(db)
    job = fetch_job_orders(db)

    availability = {
        "gps_trip_records": int(len(gps)),
        "gps_distinct_buses": int(gps["bus_no"].nunique()) if not gps.empty else 0,
        "driver_attendance_records": int(len(attendance)),
        "driver_distinct_drivers": int(attendance["driver_id"].nunique()) if not attendance.empty else 0,
        "fuel_reports": int(len(fuel)),
        "fuel_distinct_buses": int(fuel["bus_no"].nunique()) if not fuel.empty else 0,
        "job_orders": int(len(job)),
        "job_distinct_buses": int(job["bus_no"].nunique()) if not job.empty else 0,
    }
    return availability
