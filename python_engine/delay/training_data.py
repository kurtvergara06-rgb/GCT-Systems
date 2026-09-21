"""Load, validate, generate, and feature-engineer the delay dataset.

Two explicit, isolated data sources:
    * ``sample``   (DEFAULT, development): the generated SAMPLE dataset under
                    ``training_data/delay/sample_delay_training.csv`` (project
                    root). Used only for development/demonstration.
    * ``genuine``  (opt-in via ``DELAY_DATA_SOURCE=genuine``): reads the
                    Laravel-exported genuine matched DDR dataset
                    ``training_data/delay/genuine_delay_training.csv``
                    (produced by ``php artisan delay:export-genuine``). If that
                    export is missing or fails the data-sufficiency gate, the
                    pipeline FAILS clearly and never falls back to the sample.

TARGET: ``arrival_delay_minutes`` — the actual arrival delay of a completed
trip (actual arrival - scheduled arrival, in minutes).

FEATURES: only values that are known BEFORE or AT trip departure:
    route encoding, bus encoding, driver encoding, scheduled departure
    hour/minute, day of week, weekend flag, month, season, scheduled duration,
    route distance, route-level historical delay statistics (strictly prior
    observations), driver-level prior delay mean, the driver's trip
    sequence within the day, and pre-trip incident context (incidents reported
    strictly before scheduled departure, plus replacement-bus dispatch).

LEAKAGE EXCLUSIONS (never features):
    actual_departure_time, actual_arrival_time, actual_duration_minutes,
    departure_delay_minutes, arrival_delay_minutes (the label itself),
    incident resolution state (resolved_at / resolution_notes).
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import numpy as np
import pandas as pd

from .config import data_source, forecast_test_fraction, training_data_paths

logger = logging.getLogger(__name__)

DLY_TARGET = "arrival_delay_minutes"

# Pre-trip incident-context features. All are KNOWN strictly before scheduled
# departure (incidents reported earlier that same date for the same trip /
# bus / driver, and any replacement bus already dispatched). Default 0 when no
# incident data is present (e.g. the sample dataset).
DELAY_INCIDENT_FEATURES: List[str] = [
    "incident_before_departure",
    "incident_breakdown_flag",
    "incident_traffic_flag",
    "incident_replacement_flag",
]

# Raw columns written by the Laravel exporter (php artisan delay:export-genuine)
# into genuine_delay_training.csv - one row per DDR <-> schedule match with its
# derived departure/arrival delay labels and pre-trip incident context counts.
GENUINE_RAW_COLUMNS: List[str] = [
    "report_date",
    "trip_code",
    "route_code",
    "route_name",
    "bus_no",
    "driver_id",
    "driver_name",
    "trip_ticket",
    "scheduled_departure_time",
    "actual_departure_time",
    "scheduled_arrival_time",
    "actual_arrival_time",
    "scheduled_duration_minutes",
    "actual_duration_minutes",
    "departure_delay_minutes",
    "arrival_delay_minutes",
    "route_distance_km",
    "incident_before_count",
    "incident_breakdown_count",
    "incident_traffic_count",
    "incident_replacement_count",
]

GENUINE_EXCLUSION_CODES: Dict[str, str] = {
    "demo_schedule": "Trip schedule identified as DEMO (trip_code prefix).",
    "unmatched": "DDR could not be matched to a trip schedule/assignment.",
    "missing_timing": "DDR or schedule is missing departure/arrival timing.",
    "implausible_duration": "Actual duration <= 0 or > 720 minutes (corrupt).",
    "short_schedule": "Scheduled duration < 10 minutes (insufficient trip).",
    "cancelled": "Trip schedule status is Cancelled.",
    "duplicate": "Duplicate (report_date, trip_ticket) match row.",
}


class GenuineDataNotReady(Exception):
    """Raised when genuine training data is missing/insufficient.

    Carries a structured readiness report so callers can print the full
    "NOT READY FOR GENUINE TRAINING" summary without string parsing.
    """

    def __init__(self, message: str, report: Dict[str, object]):
        super().__init__(message)
        self.message = message
        self.report = report

# Every column stored in the SAMPLE CSV (readable trip record + pre-trip
# context + the two delay labels). The feature matrix is a strict subset.
REQUIRED_COLUMNS: List[str] = [
    "trip_date",
    "route",
    "bus_no",
    "driver_id",
    "driver_name",
    "trip_ticket",
    "scheduled_departure_time",
    "actual_departure_time",
    "scheduled_arrival_time",
    "actual_arrival_time",
    "scheduled_duration_minutes",
    "actual_duration_minutes",
    "departure_delay_minutes",
    "arrival_delay_minutes",
    "day_of_week",
    "is_weekend",
    "month",
    "season",
    "scheduled_departure_hour",
    "scheduled_departure_minute",
    "route_distance_km",
    "route_prior_delay_mean_min",
    "route_prior_delay_rate",
    "driver_prior_delay_mean_min",
    "driver_trip_seq",
]

# Model input features - strictly pre-trip / schedule-known information.
DLY_FEATURE_COLUMNS: List[str] = [
    "route_encoded",
    "bus_encoded",
    "driver_encoded",
    "scheduled_departure_hour",
    "scheduled_departure_minute",
    "day_of_week",
    "is_weekend",
    "month",
    "season_encoded",
    "scheduled_duration_minutes",
    "route_distance_km",
    "route_prior_delay_mean_min",
    "route_prior_delay_rate",
    "driver_prior_delay_mean_min",
    "driver_trip_seq",
    *DELAY_INCIDENT_FEATURES,
]

# Fields that would leak the current/future target or post-trip information.
# They are checked during validation and must never enter the feature set.
FORBIDDEN_FEATURES: List[str] = [
    DLY_TARGET,               # the label itself
    "departure_delay_minutes",  # the other post-trip delay label
    "actual_departure_time",  # known only after the trip starts
    "actual_arrival_time",    # known only after the trip ends
    "actual_duration_minutes",  # known only after the trip ends
]

# Identity / trace columns kept for reporting but never used as model inputs.
DLY_TRACE_COLUMNS: List[str] = [
    "trip_date",
    "route",
    "bus_no",
    "driver_id",
    "driver_name",
    "trip_ticket",
    "scheduled_departure_time",
    "actual_departure_time",
    "scheduled_arrival_time",
    "actual_arrival_time",
    "scheduled_duration_minutes",
    "actual_duration_minutes",
    "departure_delay_minutes",
]

SEASON_MAP: Dict[str, int] = {
    "dry": 0,
    "transition": 1,
    "wet": 2,
}

# ---------------------------------------------------------------------------
# Deterministic SAMPLE dataset generation
# ---------------------------------------------------------------------------

ROUTE_DEFS = [
    {"route": "Talisay - SM Seaside", "distance_km": 18.4, "duration_min": 55},
    {"route": "SM City Cebu - Mactan Airport", "distance_km": 12.5, "duration_min": 40},
    {"route": "Fuente Osmena - IT Park", "distance_km": 6.8, "duration_min": 28},
    {"route": "Ayala Center - IT Park", "distance_km": 5.2, "duration_min": 22},
    {"route": "BDO KTC Emall - Talisay Arcade", "distance_km": 9.6, "duration_min": 35},
    {"route": "Mambaling - SM City Cebu", "distance_km": 11.3, "duration_min": 42},
]

BUS_IDS = [f"GCT-{i:03d}" for i in range(101, 113)]  # 12 buses (>= 10)

DRIVER_DEFS = [
    {"driver_id": "DRV-201", "driver_name": "C. Abella", "lateness": 0.30},
    {"driver_id": "DRV-202", "driver_name": "R. Bacus", "lateness": 0.75},
    {"driver_id": "DRV-203", "driver_name": "M. Calvo", "lateness": 0.18},
    {"driver_id": "DRV-204", "driver_name": "J. Dizon", "lateness": 0.52},
    {"driver_id": "DRV-205", "driver_name": "P. Encina", "lateness": 0.08},
    {"driver_id": "DRV-206", "driver_name": "L. Florez", "lateness": 0.63},
    {"driver_id": "DRV-207", "driver_name": "G. Gatchalian", "lateness": 0.35},
    {"driver_id": "DRV-208", "driver_name": "N. Hermoso", "lateness": 0.88},
    {"driver_id": "DRV-209", "driver_name": "T. Javier", "lateness": 0.22},
    {"driver_id": "DRV-210", "driver_name": "S. Kintanar", "lateness": 0.45},
    {"driver_id": "DRV-211", "driver_name": "A. Largo", "lateness": 0.12},
    {"driver_id": "DRV-212", "driver_name": "D. Mendez", "lateness": 0.58},
    {"driver_id": "DRV-213", "driver_name": "R. Navarro", "lateness": 0.28},
    {"driver_id": "DRV-214", "driver_name": "E. Ocampo", "lateness": 0.70},
    {"driver_id": "DRV-215", "driver_name": "F. Pascual", "lateness": 0.40},
    {"driver_id": "DRV-216", "driver_name": "B. Quijano", "lateness": 0.05},
]

START_DATE = "2026-03-16"
END_DATE = "2026-08-31"


def _season_for_month(month: int) -> str:
    if month in {12, 1, 2, 3, 4, 5}:
        return "dry"
    if month in {6, 7, 8, 9}:
        return "wet"
    return "transition"


def _format_minutes(minutes: int) -> str:
    minutes = int(round(minutes))
    h, m = divmod(minutes, 60)
    return f"{h:02d}:{m:02d}"


def generate_sample_dataset(n: int = 500, seed: int = 42) -> pd.DataFrame:
    """Generate a deterministic SAMPLE dataset of ``n`` completed trips.

    SAMPLE / DEMONSTRATION DATA - never real GCT data. The generator walks
    dates forward in time and, for every trip, derives its departure/arrival
    delays from pre-trip context plus plausible operational randomness:

        departure delay   = driver skill + peaking + small queue noise
        actual duration   = scheduled duration + traffic noise + season bump
                           + occasional incident (+10..+28 min on ~6% of trips)
        arrival delay     = departure delay + (actual duration - scheduled)

    All delays are >= 0 by construction (an 'early' arrival would be a small
    negative, but the demo keeps everything non-negative for clean reporting).
    Route- and driver-level "historical" features are computed STRICTLY from
    trips already emitted (earlier departure datetimes), so they are genuinely
    prior information and never leak the label.

    Deterministic: the same seed always reproduces the exact same rows.
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
        # Departure slots between 06:00 and 20:00 (minutes-of-day).
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

            # --- scheduled plan (known before the trip) -------------------
            sched_duration = int(
                route["duration_min"] + int(rng.integers(0, 6))
            )
            if sched_duration < 20:
                sched_duration = 20
            distance_km = float(route["distance_km"])
            scheduled_dep = _format_minutes(slot)
            scheduled_arr = _format_minutes(slot + sched_duration)

            season = _season_for_month(day.month)

            # --- prior route / driver history (strictly prior trips) -----
            hist = route_hist[route["route"]]
            r_mean = float(np.mean(hist)) if hist else 0.0
            r_rate = float(np.mean([1.0 if v > 5.0 else 0.0 for v in hist])) if hist else 0.0
            d_hist = driver_hist[driver_id]
            d_mean = float(np.mean(d_hist)) if d_hist else 0.0
            seq = seq_for_day.get(driver_id, 0)

            # --- departure delay -----------------------------------------
            peak_bump = 0.0
            if 6 <= hour <= 9:
                peak_bump = max(0.0, 4.0 - abs(hour - 7)) * 0.6
            elif 16 <= hour <= 19:
                peak_bump = max(0.0, 3.0 - abs(hour - 17)) * 0.5
            departure_delay = max(
                0.0,
                round(float(driver["lateness"] * 4.0 + peak_bump + float(rng.uniform(0.0, 1.5))), 1),
            )

            # --- actual duration -----------------------------------------
            traffic_noise = float(rng.exponential(scale=3.0))
            season_bump = 2.0 if season == "wet" else 0.0
            incident = 0.0
            if rng.random() < 0.05:
                incident = float(rng.uniform(8.0, 20.0))
            actual_duration = int(
                round(sched_duration + traffic_noise + season_bump + incident)
            )

            # --- delays + actual clock times -----------------------------
            arrival_delay = round(departure_delay + (actual_duration - sched_duration), 1)
            if arrival_delay < 0.0:
                arrival_delay = 0.0
            actual_dep = int(slot + departure_delay)
            actual_arr = int(slot + departure_delay + actual_duration)

            seq_for_day[driver_id] = seq + 1
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
                    "route_prior_delay_mean_min": round(r_mean, 2),
                    "route_prior_delay_rate": round(r_rate, 4),
                    "driver_prior_delay_mean_min": round(d_mean, 2),
                    "driver_trip_seq": int(seq + 1),
                }
            )

            route_hist[route["route"]].append(arrival_delay)
            driver_hist[driver_id].append(arrival_delay)

    df = pd.DataFrame(rows, columns=REQUIRED_COLUMNS)
    df = df.sort_values(["trip_date", "scheduled_departure_hour", "scheduled_departure_minute"]).reset_index(drop=True)
    if len(df) > n:
        # Keep the most recent `n` trips so the count is controlled, while
        # route/driver history still fully precedes every kept trip.
        df = df.iloc[-n:].reset_index(drop=True)
    return df


def generate_sample_csv(path: Optional[Path] = None) -> Path:
    """Generate the SAMPLE CSV (deterministic) and write it to disk."""
    path = path or training_data_paths()["csv"]
    path.parent.mkdir(parents=True, exist_ok=True)
    df = generate_sample_dataset()
    df.to_csv(path, index=False)
    logger.info("Wrote SAMPLE delay dataset (%d rows) to %s", len(df), path)
    return path


# ---------------------------------------------------------------------------
# Loading
# ---------------------------------------------------------------------------
def load_sample_csv(path: Optional[Path] = None) -> pd.DataFrame:
    """Load the generated SAMPLE dataset from CSV."""
    path = path or training_data_paths()["csv"]
    if not Path(path).exists():
        raise FileNotFoundError(
            f"Sample delay CSV not found: {path}. Run "
            "`python -m delay.prepare_training_data` first."
        )
    df = pd.read_csv(path)
    df["trip_date"] = pd.to_datetime(df["trip_date"], errors="coerce")
    return df


def load_genuine_csv(path: Optional[Path] = None) -> pd.DataFrame:
    """Load the genuine matched DDR dataset exported by Laravel.

    Raises ``GenuineDataNotReady`` (with a full readiness report) when the
    export is missing or fails structural validation. Never falls back to the
    sample dataset.
    """
    from .config import genuine_csv_path

    path = Path(path) if path else genuine_csv_path()
    if not path.exists():
        report = {
            "matched_trips": 0,
            "ready": False,
            "missing": ["genuine export"],
            "export_path": str(path),
        }
        raise GenuineDataNotReady(
            "GENUINE delay data not found. Run `php artisan delay:export-genuine` "
            "inside the Laravel app to export the matched DDR history before "
            "training. Genuine mode never falls back to the sample dataset.",
            report,
        )

    df = pd.read_csv(path)
    missing = [c for c in GENUINE_RAW_COLUMNS if c not in df.columns]
    if missing:
        report = {"matched_trips": int(len(df)), "ready": False,
                  "missing": ["export columns"], "missing_columns": missing}
        raise GenuineDataNotReady(
            f"Genuine delay export is missing required columns: {missing}. "
            "Re-run `php artisan delay:export-genuine`.",
            report,
        )
    if df.empty:
        report = {
            "matched_trips": 0,
            "ready": False,
            "missing": ["genuine export data rows"],
            "export_path": str(path),
        }
        raise GenuineDataNotReady(
            "Genuine delay export exists but contains no data rows. "
            "Run `php artisan delay:export-genuine` to export matched DDR history.",
            report,
        )
    return df


def build_genuine_features(df: pd.DataFrame) -> pd.DataFrame:
    """Derive the model-facing wide matrix from genuine raw matched rows.

    Accepts the GENUINE_RAW_COLUMNS rows exported by Laravel and produces the
    REQUIRED_COLUMNS-wide layout consumed by :func:`build_dataset`. Strictly
    chronological prior statistics and per-day driver trip sequences are
    computed here; the incident counts become pre-trip flags.
    """
    from .config import demo_trip_prefixes

    out = df.copy()
    prefixes = demo_trip_prefixes()

    def is_demo(code: str) -> bool:
        return any(prefix and code.startswith(prefix) for prefix in prefixes)

    demo_mask = out["trip_code"].astype(str).str.strip().map(is_demo)
    if demo_mask.any():
        logger.warning("Excluding %d demo-schedule rows from genuine features.",
                       int(demo_mask.sum()))
        out = out[~demo_mask].reset_index(drop=True)

    out["trip_date"] = pd.to_datetime(out["report_date"], errors="coerce")
    out = out.sort_values(
        ["trip_date", "scheduled_departure_time"]
    ).reset_index(drop=True)

    if out.empty:
        return pd.DataFrame(columns=REQUIRED_COLUMNS + DELAY_INCIDENT_FEATURES)

    # --- pre-trip incident flags (strictly known before departure) ---------
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

    # --- calendar fields from report date ---------------------------------
    out["day_of_week"] = out["trip_date"].dt.dayofweek.astype(int)
    out["is_weekend"] = (out["day_of_week"] >= 5).astype(int)
    out["month"] = out["trip_date"].dt.month.astype(int)
    out["season"] = out["trip_date"].dt.month.map(_season_for_month)

    # --- scheduled departure hour/minute ----------------------------------
    def _hm(value: str) -> Tuple[int, int]:
        try:
            parts = str(value).strip().split(":")
            return int(parts[0]), int(parts[1])
        except (ValueError, IndexError):
            return -1, -1

    parts = out["scheduled_departure_time"].map(_hm)
    out["scheduled_departure_hour"] = parts.map(lambda p: p[0]).astype(int)
    out["scheduled_departure_minute"] = parts.map(lambda p: p[1]).astype(int)

    # --- strictly-prior route/driver statistics + driver day sequence ------
    route_hist: Dict[str, List[float]] = {}
    driver_hist: Dict[str, List[float]] = {}
    driver_day_seq: Dict[str, int] = {}
    last_day: Optional[str] = None

    r_mean: List[float] = []
    r_rate: List[float] = []
    d_mean: List[float] = []
    d_seq: List[int] = []

    for _, row in out.iterrows():
        day = str(row["trip_date"].date())
        if day != last_day:
            driver_day_seq = {}
            last_day = day

        route = str(row["route_code"]).strip()
        driver_id = str(row["driver_id"]).strip()

        hist = route_hist.get(route, [])
        r_mean.append(float(np.mean(hist)) if hist else 0.0)
        r_rate.append(
            float(np.mean([1.0 if v > 5.0 else 0.0 for v in hist])) if hist else 0.0
        )
        d_hist = driver_hist.get(driver_id, [])
        d_mean.append(float(np.mean(d_hist)) if d_hist else 0.0)
        d_seq.append(driver_day_seq.get(driver_id, 0) + 1)

        route_hist.setdefault(route, []).append(float(row["arrival_delay_minutes"]))
        driver_hist.setdefault(driver_id, []).append(float(row["arrival_delay_minutes"]))
        driver_day_seq[driver_id] = driver_day_seq.get(driver_id, 0) + 1

    out["route_prior_delay_mean_min"] = np.round(r_mean, 2)
    out["route_prior_delay_rate"] = np.round(r_rate, 4)
    out["driver_prior_delay_mean_min"] = np.round(d_mean, 2)
    out["driver_trip_seq"] = d_seq

    wide = out[[
        col for col in REQUIRED_COLUMNS if col in out.columns
    ] + DELAY_INCIDENT_FEATURES].copy()
    return wide


# ---------------------------------------------------------------------------
# Loading
# ---------------------------------------------------------------------------
def load_training_data(path: Optional[Path] = None) -> Tuple[pd.DataFrame, str]:
    """Load the active training dataset based on ``DELAY_DATA_SOURCE``.

    Returns ``(df, source_label)`` where source_label is 'sample' or 'genuine'.
    ``genuine`` NEVER falls back to sample: if the Laravel-exported genuine
    dataset is missing or insufficient, ``GenuineDataNotReady`` is raised so
    the caller can report "NOT READY FOR GENUINE TRAINING".
    """
    source = data_source()
    if source == "genuine":
        return build_genuine_features(load_genuine_csv(path)), "genuine"
    if source == "sample":
        return load_sample_csv(path), "sample"
    raise ValueError(f"Unknown delay data source: {source!r}")


# ---------------------------------------------------------------------------
# Validation (mirrors the Phase-3 data-sufficiency mindset)
# ---------------------------------------------------------------------------
def validate_dataset(df: pd.DataFrame) -> Tuple[bool, List[str], Dict[str, object]]:
    """Structural + consistency validation of the SAMPLE panel.

    Returns ``(valid, errors, report)``. ``report`` is the concise dataset
    profile printed by ``prepare_training_data``.
    """
    errors: List[str] = []
    report: Dict[str, object] = {}

    missing_cols = [c for c in REQUIRED_COLUMNS if c not in df.columns]
    if missing_cols:
        errors.append(f"Missing required columns: {missing_cols}")
        return False, errors, report

    n = len(df)
    report["total_rows"] = int(n)
    if n == 0:
        errors.append("Dataset is empty.")
        return False, errors, report

    if df["trip_date"].isna().any():
        errors.append(f"{int(df['trip_date'].isna().sum())} rows have invalid trip dates.")

    parsed_dates = pd.to_datetime(df["trip_date"], errors="coerce")
    if parsed_dates.notna().any():
        report["date_min"] = str(parsed_dates.min().date())
        report["date_max"] = str(parsed_dates.max().date())
        report["distinct_dates"] = int(parsed_dates.nunique())
        span_days = int((parsed_dates.max() - parsed_dates.min()).days)
        report["span_days"] = span_days
        report["distinct_weeks"] = max(1, int(span_days / 7) + 1)

    for col in ["route", "bus_no", "driver_id", "driver_name", "trip_ticket",
                "scheduled_departure_time", "actual_departure_time",
                "scheduled_arrival_time", "actual_arrival_time", "season"]:
        empty = int(df[col].isna().sum() + (df[col].astype(str).str.strip() == "").sum())
        if empty:
            errors.append(f"Column {col!r} has {empty} empty/missing values.")

    dupes = int(df.duplicated(subset=["trip_date", "trip_ticket"]).sum())
    if dupes:
        errors.append(f"{dupes} duplicate (trip_date, trip_ticket) rows.")

    for col in ["scheduled_duration_minutes", "actual_duration_minutes",
                "route_distance_km", "departure_delay_minutes",
                "arrival_delay_minutes"]:
        bad = int(pd.to_numeric(df[col], errors="coerce").isna().sum())
        if bad:
            errors.append(f"Column {col!r} has {bad} non-numeric values.")

    if (pd.to_numeric(df["scheduled_duration_minutes"], errors="coerce") < 10).any():
        errors.append("Some scheduled durations are impossibly short (< 10 min).")
    if (pd.to_numeric(df["arrival_delay_minutes"], errors="coerce") < 0).any():
        errors.append("Negative arrival delays found (demo dataset keeps delays >= 0).")
    if not bool(pd.to_datetime(df["trip_date"]).is_monotonic_increasing):
        errors.append("Dataset is not sorted chronologically by trip_date.")

    # Internal consistency: arrival_delay == (actual arrival - scheduled arrival).
    diff = pd.to_numeric(df["arrival_delay_minutes"], errors="coerce") - (
        pd.to_numeric(df["departure_delay_minutes"], errors="coerce")
        + pd.to_numeric(df["actual_duration_minutes"], errors="coerce")
        - pd.to_numeric(df["scheduled_duration_minutes"], errors="coerce")
    )
    bad_arith = int((diff.abs() > 1e-6).sum())
    if bad_arith:
        errors.append(
            f"{bad_arith} rows violate arrival_delay = departure_delay + "
            "(actual_duration - scheduled_duration)."
        )

    report["routes"] = int(df["route"].nunique())
    report["buses"] = int(df["bus_no"].nunique())
    report["drivers"] = int(df["driver_id"].nunique())
    arrive = pd.to_numeric(df["arrival_delay_minutes"], errors="coerce")
    report["arrival_delay_min"] = float(arrive.min())
    report["arrival_delay_max"] = float(arrive.max())
    report["arrival_delay_mean"] = round(float(arrive.mean()), 2)
    report["on_time_pct"] = round(float((arrive <= 5).mean() * 100.0), 1)
    report["delay_distro"] = {
        "on_time_0_5": int((arrive <= 5).sum()),
        "minor_6_10": int(((arrive > 5) & (arrive <= 10)).sum()),
        "moderate_11_20": int(((arrive > 10) & (arrive <= 20)).sum()),
        "high_21_plus": int((arrive > 20).sum()),
    }

    return (len(errors) == 0), errors, report


def readiness_report(df: pd.DataFrame) -> Dict[str, object]:
    """Detailed data-sufficiency report against the configured thresholds.

    Used by the genuine pipeline (and echoed in status). Counts reflected the
    MATCHED rows present in the dataset: matched trips, distinct routes, buses,
    drivers, and the history span in weeks. Each threshold gets its own
    ``{threshold, actual, passed}`` entry so downstream callers can print the
    exact "missing" list without string parsing.
    """
    from .config import data_thresholds

    thresholds = data_thresholds()

    n = int(len(df))
    routes = int(df["route"].nunique()) if n else 0
    buses = int(df["bus_no"].nunique()) if n else 0
    drivers = int(df["driver_id"].nunique()) if n else 0

    span_days = 0
    weeks = 0
    date_min = date_max = ""
    if n:
        parsed = pd.to_datetime(df["trip_date"], errors="coerce").dropna()
        if len(parsed):
            date_min = str(parsed.min().date())
            date_max = str(parsed.max().date())
            span_days = int((parsed.max() - parsed.min()).days)
            weeks = max(1, int(span_days / 7) + 1)

    detail = {
        "min_records": {"threshold": thresholds["min_records"], "actual": n,
                        "passed": n >= thresholds["min_records"]},
        "min_routes": {"threshold": thresholds["min_routes"], "actual": routes,
                       "passed": routes >= thresholds["min_routes"]},
        "min_buses": {"threshold": thresholds["min_buses"], "actual": buses,
                      "passed": buses >= thresholds["min_buses"]},
        "min_drivers": {"threshold": thresholds["min_drivers"], "actual": drivers,
                        "passed": drivers >= thresholds["min_drivers"]},
        "min_weeks": {"threshold": thresholds["min_weeks"], "actual": weeks,
                      "passed": weeks >= thresholds["min_weeks"]},
    }
    missing = [name for name, d in detail.items() if not d["passed"]]

    return {
        "matched_trips": n,
        "routes": routes,
        "buses": buses,
        "drivers": drivers,
        "date_min": date_min,
        "date_max": date_max,
        "span_days": span_days,
        "weeks": weeks,
        "thresholds": detail,
        "missing": missing,
        "ready": len(missing) == 0,
    }


def check_readiness_thresholds(df: pd.DataFrame) -> Tuple[bool, List[str]]:
    """Data-sufficiency gate for the delay dataset (sample or genuine)."""
    report = readiness_report(df)
    issues = [
        f"{field} {detail['actual']} < {detail['threshold']}"
        for field, detail in report["thresholds"].items()
        if not detail["passed"]
    ]
    return report["ready"], issues


# ---------------------------------------------------------------------------
# Encoders + metadata (stable ordering used identically at predict time)
# ---------------------------------------------------------------------------
def build_encoders(df: pd.DataFrame) -> Dict[str, Dict[str, int]]:
    """Stable sorted label-encoders for the categorical features."""
    routes = sorted(df["route"].astype(str).str.strip().unique().tolist())
    buses = sorted(df["bus_no"].astype(str).str.strip().unique().tolist())
    drivers = sorted(df["driver_id"].astype(str).str.strip().unique().tolist())
    return {
        "route_encodings": {r: i for i, r in enumerate(routes)},
        "bus_encodings": {b: i for i, b in enumerate(buses)},
        "driver_encodings": {d: i for i, d in enumerate(drivers)},
    }


def build_route_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Per-route static metadata captured at training time (predict fallbacks)."""
    meta: Dict[str, Dict[str, float]] = {}
    for route, group in df.groupby("route", observed=True):
        first = group.iloc[0]
        meta[str(route)] = {
            "distance_km": float(first["route_distance_km"]),
            "duration_minutes": float(first["scheduled_duration_minutes"]),
            "prior_delay_mean": float(first["route_prior_delay_mean_min"]),
            "prior_delay_rate": float(first["route_prior_delay_rate"]),
            "n_obs": int(len(group)),
        }
    return meta


def build_driver_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Per-driver derived metadata used as prediction fallbacks."""
    meta: Dict[str, Dict[str, float]] = {}
    for driver_id, group in df.groupby("driver_id", observed=True):
        meta[str(driver_id)] = {
            "prior_delay_mean": float(group["driver_prior_delay_mean_min"].mean()),
            "n_obs": int(len(group)),
        }
    return meta


def build_bus_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Per-bus derived metadata used as prediction fallbacks."""
    meta: Dict[str, Dict[str, float]] = {}
    for bus_no, group in df.groupby("bus_no", observed=True):
        meta[str(bus_no)] = {
            "n_obs": int(len(group)),
            "mean_route_prior_delay": float(group["route_prior_delay_mean_min"].mean()),
        }
    return meta


# ---------------------------------------------------------------------------
# Feature engineering
# ---------------------------------------------------------------------------
def build_dataset(df: pd.DataFrame) -> pd.DataFrame:
    """Turn raw SAMPLE rows into the leakage-safe feature matrix + target."""
    if df.empty:
        return pd.DataFrame(
            columns=DLY_FEATURE_COLUMNS + DLY_TRACE_COLUMNS + [DLY_TARGET]
        )

    out = df.copy()
    out["trip_date"] = pd.to_datetime(out["trip_date"], errors="coerce")
    out = out.sort_values(
        ["trip_date", "scheduled_departure_hour", "scheduled_departure_minute"]
    ).reset_index(drop=True)

    encoders = build_encoders(out)
    out["route_encoded"] = (
        out["route"].astype(str).str.strip().map(encoders["route_encodings"]).fillna(-1)
    )
    out["bus_encoded"] = (
        out["bus_no"].astype(str).str.strip().map(encoders["bus_encodings"]).fillna(-1)
    )
    out["driver_encoded"] = (
        out["driver_id"].astype(str).str.strip().map(encoders["driver_encodings"]).fillna(-1)
    )
    out["season_encoded"] = (
        out["season"].astype(str).str.strip().str.lower().map(SEASON_MAP).fillna(-1)
    )

    # Sample mode has no incident data: incident-context features default to 0.
    for col in DELAY_INCIDENT_FEATURES:
        if col not in out.columns:
            out[col] = 0

    for col in DLY_FEATURE_COLUMNS:
        out[col] = pd.to_numeric(out[col], errors="coerce")

    target = pd.to_numeric(out[DLY_TARGET], errors="coerce").fillna(0.0).clip(lower=0.0)
    out[DLY_TARGET] = target

    trace = out[DLY_TRACE_COLUMNS].copy()
    result = pd.concat(
        [out[DLY_FEATURE_COLUMNS], trace, out[[DLY_TARGET]]],
        axis=1,
    )
    result = result.dropna(subset=DLY_FEATURE_COLUMNS + [DLY_TARGET]).reset_index(drop=True)
    return result


# ---------------------------------------------------------------------------
# Chronological split
# ---------------------------------------------------------------------------
def chronological_split(
    df: pd.DataFrame, test_fraction: float | None = None
) -> Tuple[pd.DataFrame, pd.DataFrame, Dict[str, str]]:
    """Chronological (time-aware) train/test split on the DATE axis.

    The earliest ``(1 - test_fraction)`` distinct dates form the training set
    and the most recent dates form the test set. A date never straddles the
    boundary, so no future observation can enter the training folds.
    """
    if df.empty or "trip_date" not in df.columns:
        return df, pd.DataFrame(), dict(train_start="", train_end="", test_start="", test_end="")

    test_fraction = test_fraction or forecast_test_fraction()
    dates = sorted(df["trip_date"].dropna().unique().tolist())
    if len(dates) < 2:
        return df, pd.DataFrame(), dict(train_start="", train_end="", test_start="", test_end="")

    split = max(1, int(round(len(dates) * (1.0 - test_fraction))))
    split = min(split, len(dates) - 1)
    train_dates = set(dates[:split])
    test_dates = set(dates[split:])

    train = df[df["trip_date"].isin(train_dates)].reset_index(drop=True)
    test = df[df["trip_date"].isin(test_dates)].reset_index(drop=True)

    periods = {
        "train_start": str(dates[0].date()),
        "train_end": str(dates[split - 1].date()),
        "test_start": str(dates[split].date()),
        "test_end": str(dates[-1].date()),
    }
    return train, test, periods


def write_features_csv(df: pd.DataFrame, path: Optional[Path] = None) -> Path:
    """Persist the leakage-safe feature matrix to CSV."""
    path = path or training_data_paths()["features_csv"]
    path.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(path, index=False)
    return path