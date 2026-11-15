"""Deterministic SAMPLE generator for Delay Model #3.

This module intentionally creates a learnable development/demo dataset from
pre-trip signals only. It does NOT change or fabricate genuine GCT data.

The previous sample generator added mostly hidden random traffic and incident
noise to the target. That made a correct leakage-safe model look useless on a
chronological holdout set (negative R2). This generator keeps randomness, but
makes the strongest delay drivers observable before departure through features
that the live prediction API already supports:

- scheduled hour / weekday / weekend
- route and distance
- season
- driver and trip sequence
- pre-trip incident / breakdown / traffic / replacement flags

Post-trip values such as actual departure, actual arrival, actual duration and
departure delay remain labels/trace fields only and are never model features.
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict, List, Optional

import numpy as np
import pandas as pd

from .config import training_data_paths
from .training_data import (
    BUS_IDS,
    DELAY_INCIDENT_FEATURES,
    DRIVER_DEFS,
    END_DATE,
    REQUIRED_COLUMNS,
    ROUTE_DEFS,
    START_DATE,
    _format_minutes,
    _season_for_month,
)

# Static route congestion sensitivity used only by the deterministic SAMPLE
# generator. These are demo coefficients, not measured GCT operational values.
_ROUTE_TRAFFIC_FACTOR: Dict[str, float] = {
    "Talisay - SM Seaside": 1.35,
    "SM City Cebu - Mactan Airport": 1.15,
    "Fuente Osmena - IT Park": 1.45,
    "Ayala Center - IT Park": 1.30,
    "BDO KTC Emall - Talisay Arcade": 1.10,
    "Mambaling - SM City Cebu": 1.25,
}


def _peak_intensity(hour: int) -> float:
    """Return a 0..1 pre-trip congestion intensity from scheduled hour."""
    morning = max(0.0, 1.0 - abs(hour - 7.5) / 2.5) if 5 <= hour <= 10 else 0.0
    evening = max(0.0, 1.0 - abs(hour - 17.5) / 2.5) if 15 <= hour <= 20 else 0.0
    return float(max(morning, evening))


def generate_sample_dataset(n: int = 500, seed: int = 42) -> pd.DataFrame:
    """Generate a leakage-safe, learnable SAMPLE delay dataset.

    The target still contains uncertainty, but most variance comes from
    information available before departure. This allows chronological test R2
    to measure whether the model learns useful pre-trip patterns instead of
    being dominated by hidden random noise.
    """
    rng = np.random.default_rng(seed)
    route_by_name = {r["route"]: r for r in ROUTE_DEFS}
    driver_by_id = {d["driver_id"]: d for d in DRIVER_DEFS}

    dates = pd.date_range(START_DATE, END_DATE, freq="D")
    rows: List[dict] = []
    route_hist: Dict[str, List[float]] = {r["route"]: [] for r in ROUTE_DEFS}
    driver_hist: Dict[str, List[float]] = {d["driver_id"]: [] for d in DRIVER_DEFS}
    driver_day_seq: Dict[int, Dict[str, int]] = {}

    for day_index, day in enumerate(dates):
        is_weekend = day.weekday() >= 5
        n_trips = int(
            rng.choice([2, 3, 4, 5], p=[0.30, 0.30, 0.25, 0.15])
            if not is_weekend
            else rng.choice([2, 3, 4], p=[0.40, 0.35, 0.25])
        )
        slots = sorted(rng.choice(range(360, 1200), size=n_trips, replace=False))
        seq_for_day = driver_day_seq.setdefault(day_index, {})
        day_str = day.strftime("%Y-%m-%d")
        day_trip_counter = 0

        for slot in slots:
            route = route_by_name[rng.choice(list(route_by_name.keys()))]
            bus_no = str(rng.choice(BUS_IDS))
            driver = driver_by_id[rng.choice(list(driver_by_id.keys()))]
            driver_id = driver["driver_id"]
            hour, minute = divmod(int(slot), 60)

            sched_duration = int(route["duration_min"] + int(rng.integers(0, 6)))
            sched_duration = max(20, sched_duration)
            distance_km = float(route["distance_km"])
            scheduled_dep = _format_minutes(slot)
            scheduled_arr = _format_minutes(slot + sched_duration)
            season = _season_for_month(day.month)

            # Strictly-prior historical context.
            route_values = route_hist[route["route"]]
            route_prior_mean = float(np.mean(route_values)) if route_values else 0.0
            route_prior_rate = (
                float(np.mean([1.0 if value > 5.0 else 0.0 for value in route_values]))
                if route_values
                else 0.0
            )
            driver_values = driver_hist[driver_id]
            driver_prior_mean = float(np.mean(driver_values)) if driver_values else 0.0
            prior_seq = seq_for_day.get(driver_id, 0)
            driver_trip_seq = prior_seq + 1

            # Pre-trip congestion context. The model can infer this from route,
            # scheduled hour, weekday/weekend, season and route distance.
            peak = _peak_intensity(hour)
            weekday_factor = 1.0 if not is_weekend else 0.45
            wet_factor = 1.0 if season == "wet" else 0.0
            route_factor = _ROUTE_TRAFFIC_FACTOR.get(route["route"], 1.0)

            predictable_traffic = (
                1.0
                + 4.8 * peak * weekday_factor * route_factor
                + 1.6 * wet_factor
                + 0.06 * distance_km
            )
            # Keep irreducible uncertainty, but do not let hidden randomness
            # dominate the target as it did in the old SAMPLE generator.
            residual_traffic = max(0.0, float(rng.normal(0.0, 0.8)))
            traffic_delay = predictable_traffic + residual_traffic

            # Pre-trip incident context. Any synthetic incident that affects the
            # target is represented by the same flags accepted by live inference.
            incident_probability = (
                0.025 + 0.025 * peak * weekday_factor + 0.015 * wet_factor
            )
            incident_before = int(rng.random() < incident_probability)
            breakdown_flag = 0
            traffic_flag = 0
            replacement_flag = 0
            incident_delay = 0.0

            if incident_before:
                if rng.random() < 0.30:
                    breakdown_flag = 1
                    replacement_flag = int(rng.random() < 0.65)
                    incident_delay = float(rng.uniform(7.0, 12.0))
                    if replacement_flag:
                        # A replacement dispatched before departure reduces the
                        # expected breakdown impact but does not erase it.
                        incident_delay = max(0.0, incident_delay - 2.5)
                else:
                    traffic_flag = 1
                    incident_delay = float(rng.uniform(5.0, 10.0))

            # Departure delay stays a post-trip trace field, never an input.
            # Its drivers are nevertheless pre-trip-observable: driver identity,
            # peak period and the driver's within-day trip sequence.
            departure_delay = max(
                0.0,
                float(driver["lateness"]) * 4.0
                + 1.5 * peak * weekday_factor
                + 0.35 * max(0, driver_trip_seq - 1)
                + float(rng.normal(0.5, 0.45)),
            )
            departure_delay = round(departure_delay, 1)

            actual_duration = int(
                round(sched_duration + traffic_delay + incident_delay)
            )
            arrival_delay = round(
                departure_delay + (actual_duration - sched_duration), 1
            )
            arrival_delay = max(0.0, arrival_delay)

            actual_dep = int(slot + departure_delay)
            actual_arr = int(slot + departure_delay + actual_duration)

            seq_for_day[driver_id] = driver_trip_seq
            day_trip_counter += 1

            rows.append(
                {
                    "trip_date": day_str,
                    "route": route["route"],
                    "bus_no": bus_no,
                    "driver_id": driver_id,
                    "driver_name": driver["driver_name"],
                    "trip_ticket": f"TRIP-{day.strftime('%y%m%d')}-{day_trip_counter:02d}",
                    "scheduled_departure_time": scheduled_dep,
                    "actual_departure_time": _format_minutes(actual_dep),
                    "scheduled_arrival_time": scheduled_arr,
                    "actual_arrival_time": _format_minutes(actual_arr),
                    "scheduled_duration_minutes": sched_duration,
                    "actual_duration_minutes": actual_duration,
                    "departure_delay_minutes": departure_delay,
                    "arrival_delay_minutes": arrival_delay,
                    "day_of_week": int(day.weekday()),
                    "is_weekend": 1 if is_weekend else 0,
                    "month": int(day.month),
                    "season": season,
                    "scheduled_departure_hour": int(hour),
                    "scheduled_departure_minute": int(minute),
                    "route_distance_km": distance_km,
                    "route_prior_delay_mean_min": round(route_prior_mean, 2),
                    "route_prior_delay_rate": round(route_prior_rate, 4),
                    "driver_prior_delay_mean_min": round(driver_prior_mean, 2),
                    "driver_trip_seq": int(driver_trip_seq),
                    "incident_before_departure": incident_before,
                    "incident_breakdown_flag": breakdown_flag,
                    "incident_traffic_flag": traffic_flag,
                    "incident_replacement_flag": replacement_flag,
                }
            )

            route_hist[route["route"]].append(arrival_delay)
            driver_hist[driver_id].append(arrival_delay)

    columns = REQUIRED_COLUMNS + DELAY_INCIDENT_FEATURES
    df = pd.DataFrame(rows, columns=columns)
    df = df.sort_values(
        ["trip_date", "scheduled_departure_hour", "scheduled_departure_minute"]
    ).reset_index(drop=True)
    if len(df) > n:
        df = df.iloc[-n:].reset_index(drop=True)
    return df


def generate_sample_csv(path: Optional[Path] = None) -> Path:
    """Regenerate and persist the deterministic improved SAMPLE dataset."""
    path = path or training_data_paths()["csv"]
    path.parent.mkdir(parents=True, exist_ok=True)
    generate_sample_dataset().to_csv(path, index=False)
    return path


if __name__ == "__main__":
    output = generate_sample_csv()
    print(f"Wrote improved delay SAMPLE dataset to {output}")
