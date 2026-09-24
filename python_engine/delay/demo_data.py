"""Frontend-visible DEMO dataset adapter for Delay Model #3.

The Laravel client-demo seeder writes synthetic trip schedules + DDR records
into the same tables used by the normal frontend. ``php artisan delay:export-demo``
exports those rows to ``training_data/delay/demo_delay_training.csv``.

This module converts that export into the same leakage-safe wide layout used by
the delay pipeline. It never relabels these rows as genuine.
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict, List, Optional, Tuple

import numpy as np
import pandas as pd

from .config import demo_csv_path
from .training_data import (
    DELAY_INCIDENT_FEATURES,
    GENUINE_RAW_COLUMNS,
    REQUIRED_COLUMNS,
)


def load_demo_csv(path: Optional[Path] = None) -> pd.DataFrame:
    path = Path(path) if path else demo_csv_path()
    if not path.exists():
        raise FileNotFoundError(
            f"Demo delay export not found: {path}. Run "
            "`php artisan delay:export-demo` from the Laravel project first."
        )

    df = pd.read_csv(path)
    missing = [column for column in GENUINE_RAW_COLUMNS if column not in df.columns]
    if missing:
        raise ValueError(
            f"Demo delay export is missing required columns: {missing}. "
            "Re-run `php artisan delay:export-demo`."
        )
    if df.empty:
        raise ValueError(
            "Demo delay export contains no rows. Seed ClientDemoDataSeeder first, "
            "then run `php artisan delay:export-demo`."
        )
    return df


def _season_for_month(month: int) -> str:
    if month in {12, 1, 2, 3, 4, 5}:
        return "dry"
    if month in {6, 7, 8, 9}:
        return "wet"
    return "transition"


def build_demo_features(df: pd.DataFrame) -> pd.DataFrame:
    """Convert exported DEMO DDR rows into the standard delay-wide dataset."""
    out = df.copy()

    # Strictly accept the records generated for the frontend client demo.
    out = out[
        out["trip_code"].astype(str).str.startswith("TRIP-DEMO-")
    ].reset_index(drop=True)
    if out.empty:
        return pd.DataFrame(columns=REQUIRED_COLUMNS + DELAY_INCIDENT_FEATURES)

    out["trip_date"] = pd.to_datetime(out["report_date"], errors="coerce")
    out = out.sort_values(["trip_date", "scheduled_departure_time"]).reset_index(drop=True)

    out["route"] = out["route_code"].astype(str).str.strip()
    out["incident_before_departure"] = (
        pd.to_numeric(out["incident_before_count"], errors="coerce").fillna(0) > 0
    ).astype(int)
    out["incident_breakdown_flag"] = (
        pd.to_numeric(out["incident_breakdown_count"], errors="coerce").fillna(0) > 0
    ).astype(int)
    out["incident_traffic_flag"] = (
        pd.to_numeric(out["incident_traffic_count"], errors="coerce").fillna(0) > 0
    ).astype(int)
    out["incident_replacement_flag"] = (
        pd.to_numeric(out["incident_replacement_count"], errors="coerce").fillna(0) > 0
    ).astype(int)

    out["day_of_week"] = out["trip_date"].dt.dayofweek.astype(int)
    out["is_weekend"] = (out["day_of_week"] >= 5).astype(int)
    out["month"] = out["trip_date"].dt.month.astype(int)
    out["season"] = out["trip_date"].dt.month.map(_season_for_month)

    def _hm(value: str) -> Tuple[int, int]:
        try:
            parts = str(value).strip().split(":")
            return int(parts[0]), int(parts[1])
        except (ValueError, IndexError):
            return -1, -1

    clock = out["scheduled_departure_time"].map(_hm)
    out["scheduled_departure_hour"] = clock.map(lambda value: value[0]).astype(int)
    out["scheduled_departure_minute"] = clock.map(lambda value: value[1]).astype(int)

    route_hist: Dict[str, List[float]] = {}
    driver_hist: Dict[str, List[float]] = {}
    driver_day_seq: Dict[str, int] = {}
    last_day: Optional[str] = None

    route_means: List[float] = []
    route_rates: List[float] = []
    driver_means: List[float] = []
    driver_sequences: List[int] = []

    for _, row in out.iterrows():
        day = str(row["trip_date"].date())
        if day != last_day:
            driver_day_seq = {}
            last_day = day

        route = str(row["route_code"]).strip()
        driver_id = str(row["driver_id"]).strip()
        route_values = route_hist.get(route, [])
        driver_values = driver_hist.get(driver_id, [])

        route_means.append(float(np.mean(route_values)) if route_values else 0.0)
        route_rates.append(
            float(np.mean([1.0 if value > 5.0 else 0.0 for value in route_values]))
            if route_values else 0.0
        )
        driver_means.append(float(np.mean(driver_values)) if driver_values else 0.0)
        driver_sequences.append(driver_day_seq.get(driver_id, 0) + 1)

        delay = float(row["arrival_delay_minutes"])
        route_hist.setdefault(route, []).append(delay)
        driver_hist.setdefault(driver_id, []).append(delay)
        driver_day_seq[driver_id] = driver_day_seq.get(driver_id, 0) + 1

    out["route_prior_delay_mean_min"] = np.round(route_means, 2)
    out["route_prior_delay_rate"] = np.round(route_rates, 4)
    out["driver_prior_delay_mean_min"] = np.round(driver_means, 2)
    out["driver_trip_seq"] = driver_sequences

    columns = [column for column in REQUIRED_COLUMNS if column in out.columns]
    return out[columns + DELAY_INCIDENT_FEATURES].copy()
