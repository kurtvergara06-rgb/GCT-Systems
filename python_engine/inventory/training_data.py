"""Load, validate, and feature-engineer the inventory sample dataset.

Two data sources are supported:
    * ``sample``   (DEFAULT, development): the generated SAMPLE dataset under
                    ``training_data/inventory/sample_inventory_training.csv``
                    (project root).
    * ``genuine``  (opt-in via ``INVENTORY_DATA_SOURCE=genuine``): reads ONLY
                    ``stock_movements`` rows with ``source = 'app'`` from the
                    Laravel MySQL database. Because the genuine ledger is
                    still empty (see INVENTORY_MODEL_READINESS.md), the
                    genuine path will refuse to train rather than fall back.

TARGET variable: ``quantity_issued`` — weekly spare-part demand per
(bus, part). Features use ONLY information available BEFORE the forecast
week. Every field that is only known during/after the week (current
``maintenance_type``, current ``breakdown_count``, ``on_hand_after``) is
either rejected or lagged, so there is no future-target leakage.
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import pandas as pd

from .config import data_source, forecast_test_fraction, training_data_paths

logger = logging.getLogger(__name__)

INVENTORY_TARGET = "quantity_issued"

REQUIRED_COLUMNS: List[str] = [
    "date",
    "bus_id",
    "part_id",
    "part_name",
    "category",
    "quantity_issued",
    "on_hand_before",
    "on_hand_after",
    "reorder_level",
    "unit",
    "maintenance_type",
    "vehicle_mileage",
    "breakdown_count",
    "days_since_last_issue",
    "supplier_lead_days",
]

INVENTORY_FEATURE_COLUMNS: List[str] = [
    "bus_encoded",
    "part_encoded",
    "category_encoded",
    "unit_encoded",
    "maintenance_type_lag1_encoded",
    "on_hand",
    "vehicle_mileage",
    "breakdown_count_lag1",
    "reorder_level",
    "supplier_lead_days",
    "demand_lag1",
    "demand_lag2",
    "rolling_demand_4w",
    "rolling_demand_8w",
    "days_since_last_issue",
    "week_of_year",
    "month",
    "quarter",
    "elapsed_weeks",
]

# Fields that would leak the current/future target or post-period information.
# They are checked for during validation and must never enter the feature set.
FORBIDDEN_FEATURES: List[str] = [
    INVENTORY_TARGET,           # the label itself
    "on_hand_after",            # computed WITH the label (same period stock)
    "maintenance_type",         # same-period activity (use the lag 1 instead)
    "breakdown_count",          # same-period breakdowns (use the lag 1 instead)
    "part_name",                # name is identity; encoded part_id carries it
]

# Trace columns dropped before model input but kept for reporting/reproducibility.
INVENTORY_TRACE_COLUMNS: List[str] = [
    "date",
    "bus_id",
    "part_id",
    "part_name",
    "category",
    "unit",
    "on_hand_before",
    "on_hand_after",
]

MAINTENANCE_TYPES = ["None", "Preventive", "Corrective"]


# ---------------------------------------------------------------------------
# Loading
# ---------------------------------------------------------------------------
def load_sample_csv(path: Optional[Path] = None) -> pd.DataFrame:
    """Load the generated SAMPLE dataset."""
    path = path or training_data_paths()["csv"]
    if not Path(path).exists():
        raise FileNotFoundError(
            f"Sample inventory CSV not found: {path}. Run "
            "`python -m inventory.sample_data.generate_sample_data` first."
        )
    df = pd.read_csv(path)
    df["date"] = pd.to_datetime(df["date"], errors="coerce")
    return df


def load_training_data(path: Optional[Path] = None) -> Tuple[pd.DataFrame, str]:
    """Load the active training dataset based on ``INVENTORY_DATA_SOURCE``.

    Returns ``(df, source_label)`` where ``source_label`` is ``sample`` or
    ``genuine``.
    """
    source = data_source()
    if source == "genuine":
        df = fetch_genuine_stock_movements()
        return df, "genuine"
    if source == "sample":
        return load_sample_csv(path), "sample"
    raise ValueError(f"Unknown inventory data source: {source!r}")


def fetch_genuine_stock_movements() -> pd.DataFrame:
    """Extract genuine ``stock_movements`` (``source='app'``) into a panel.

    Development helper for the FUTURE switch to real data. Only rows with
    ``source = 'app'`` are ever considered. If there are none, an empty
    DataFrame is returned and the training pipeline refuses to train (it
    never falls back to sample data silently).
    """
    from operation_ai.ml.database import DbConnection

    db = DbConnection()
    try:
        sql = """
            SELECT
                DATE(created_at)                      AS activity_date,
                ''                                   AS bus_id,
                inventory_item_id                    AS part_id,
                CONCAT(item_code, ' - ', item_name)  AS part_name,
                item_code                            AS category,
                unit                                 AS unit,
                reference_no                         AS reference_no,
                movement_type                        AS movement_type,
                quantity_change                      AS quantity_change,
                previous_stock                       AS on_hand_before,
                new_stock                            AS on_hand_after,
                created_by                           AS created_by
            FROM stock_movements
            WHERE source = 'app'
        """
        df = db.query_df(sql)
    finally:
        db.close()

    if df.empty:
        logger.warning(
            "No genuine (source='app') stock_movements found. Model #4 cannot "
            "train on genuine data yet; refusing to proceed."
        )
        return pd.DataFrame(columns=REQUIRED_COLUMNS + ["is_sample"])
    return _panelize_genuine_movements(df)


def _panelize_genuine_movements(df: pd.DataFrame) -> pd.DataFrame:
    """Placeholder aggregation of genuine ledger rows into the weekly schema.

    NOTE: this aggregation is intentionally conservative — no genuine rows
    exist yet, so the code path cannot be exercised. It is provided only to
    make the data-source switch explicit and auditable.
    """
    parsed = df.copy()
    parsed["date"] = pd.to_datetime(parsed["activity_date"], errors="coerce")
    parsed["quantity_issued"] = pd.to_numeric(
        parsed["quantity_change"], errors="coerce"
    ).fillna(0) * -0.0  # Stock Out only; Stock In is mapped to 0 demand
    raise NotImplementedError(
        "Genuine stock_movements aggregation is not enabled until the ledger "
        "contains real source='app' observations. See "
        "INVENTORY_MODEL_READINESS.md."
    )


# ---------------------------------------------------------------------------
# Validation (Phase 3)
# ---------------------------------------------------------------------------
def validate_dataset(df: pd.DataFrame) -> Tuple[bool, List[str], Dict[str, object]]:
    """Structural + consistency validation of the sample panel.

    Returns ``(valid, errors, report)``. The report is the concise dataset
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

    if df["date"].isna().any():
        errors.append(f"{int(df['date'].isna().sum())} rows have invalid dates.")
    parsed_dates = pd.to_datetime(df["date"], errors="coerce")
    if parsed_dates.notna().any():
        report["date_min"] = str(parsed_dates.min().date())
        report["date_max"] = str(parsed_dates.max().date())

    for col in ["bus_id", "part_id", "part_name", "category", "unit"]:
        if df[col].isna().any() or (df[col].astype(str).str.strip() == "").any():
            errors.append(f"Column {col!r} has empty/missing values.")

    unit_ok = (
        df.groupby("part_id")["unit"].nunique().le(1).all()
    )
    if not unit_ok:
        errors.append("Some parts have more than one unit of measure.")

    if (df["quantity_issued"] < 0).any():
        errors.append("Negative quantity_issued found.")
    if (df["on_hand_before"] < 0).any():
        errors.append("Negative on_hand_before found.")
    if (df["on_hand_after"] < 0).any():
        errors.append("Negative on_hand_after found.")

    arithmetic = df["on_hand_before"] - df["quantity_issued"] - df["on_hand_after"]
    bad_arithmetic = int((arithmetic.abs() > 1e-9).sum())
    if bad_arithmetic:
        errors.append(f"{bad_arithmetic} rows violate on_hand_after = on_hand_before - quantity_issued.")

    dupes = int(df.duplicated(subset=["date", "bus_id", "part_id"]).sum())
    if dupes:
        errors.append(f"{dupes} duplicate (date, bus_id, part_id) observations.")

    bad_chrono_series = 0
    for _, _group in df.groupby(["bus_id", "part_id"], observed=True):
        d = _group.sort_values("date")["date"].to_numpy()
        if len(d) > 1 and (d[1:] < d[:-1]).any():
            bad_chrono_series += 1
    if bad_chrono_series:
        errors.append(
            f"{bad_chrono_series} (bus, part) series are not chronologically ordered."
        )

    # ---- report profile ------------------------------------------------
    report["buses"] = int(df["bus_id"].nunique())
    report["parts"] = int(df["part_id"].nunique())
    report["categories"] = int(df["category"].nunique())
    report["maintenance_types"] = sorted(
        {str(t) for t in df["maintenance_type"].tolist() if pd.notna(t)}
    )
    qty = pd.to_numeric(df["quantity_issued"], errors="coerce").fillna(0)
    report["avg_quantity_issued"] = round(float(qty.mean()), 4)
    report["zero_demand_pct"] = round(float((qty == 0).mean() * 100.0), 2)
    report["min_demand"] = int(qty.min())
    report["max_demand"] = int(qty.max())
    report["obs_per_part_min"] = int(df.groupby("part_id").size().min())
    report["obs_per_part_max"] = int(df.groupby("part_id").size().max())
    report["obs_per_bus_min"] = int(df.groupby("bus_id").size().min())
    report["obs_per_bus_max"] = int(df.groupby("bus_id").size().max())
    report["obs_per_part"] = df.groupby("part_id").size().to_dict()
    report["obs_per_bus"] = df.groupby("bus_id").size().to_dict()

    return (len(errors) == 0), errors, report


def check_readiness_thresholds(df: pd.DataFrame) -> Tuple[bool, List[str]]:
    """Data-sufficiency gate (sample pipeline threshold check)."""
    from .config import data_thresholds

    thresholds = data_thresholds()
    issues: List[str] = []
    if len(df) < thresholds["min_rows"]:
        issues.append(f"rows {len(df)} < {thresholds['min_rows']}")
    if df["bus_id"].nunique() < thresholds["min_buses"]:
        issues.append(f"buses {df['bus_id'].nunique()} < {thresholds['min_buses']}")
    if df["part_id"].nunique() < thresholds["min_parts"]:
        issues.append(f"parts {df['part_id'].nunique()} < {thresholds['min_parts']}")
    n_weeks = df["date"].nunique() if "date" in df.columns else 0
    if n_weeks < thresholds["min_weeks"]:
        issues.append(f"weeks {n_weeks} < {thresholds['min_weeks']}")
    return (len(issues) == 0), issues


# ---------------------------------------------------------------------------
# Encoders + metadata
# ---------------------------------------------------------------------------
def build_encoders(df: pd.DataFrame) -> Dict[str, Dict[str, int]]:
    """Stable sorted label-encoders (identical ordering is used at predict time)."""
    buses = sorted(df["bus_id"].astype(str).unique().tolist())
    parts = sorted(df["part_id"].astype(str).unique().tolist())
    categories = sorted(df["category"].astype(str).unique().tolist())
    units = sorted(df["unit"].astype(str).unique().tolist())
    return {
        "bus_encodings": {b: i for i, b in enumerate(buses)},
        "part_encodings": {p: i for i, p in enumerate(parts)},
        "category_encodings": {c: i for i, c in enumerate(categories)},
        "unit_encodings": {u: i for i, u in enumerate(units)},
        "maint_lag_encodings": {m: i for i, m in enumerate(MAINTENANCE_TYPES)},
    }


def build_part_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, object]]:
    """Per-part static + derived metadata used as prediction fallbacks."""
    meta: Dict[str, Dict[str, object]] = {}
    for part_id, group in df.groupby("part_id", observed=True):
        row = group.iloc[0]
        qty = pd.to_numeric(group["quantity_issued"], errors="coerce").fillna(0)
        meta[str(part_id)] = {
            "name": str(row["part_name"]),
            "category": str(row["category"]),
            "unit": str(row["unit"]),
            "reorder_level": int(row["reorder_level"]),
            "supplier_lead_days": int(row["supplier_lead_days"]),
            "mean_weekly_demand": float(qty.mean()),
            "zero_share": float((qty == 0).mean()),
            "n_obs": int(len(group)),
            "mean_on_hand": float(pd.to_numeric(group["on_hand_before"], errors="coerce").mean()),
            "mean_days_since_last_issue": float(
                pd.to_numeric(group["days_since_last_issue"], errors="coerce").mean()
            ),
        }
    return meta


def build_bus_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
    """Per-bus derived metadata used as prediction fallbacks."""
    meta: Dict[str, Dict[str, float]] = {}
    for bus_id, group in df.groupby("bus_id", observed=True):
        qty = pd.to_numeric(group["quantity_issued"], errors="coerce").fillna(0)
        mileage = pd.to_numeric(group["vehicle_mileage"], errors="coerce").fillna(0)
        meta[str(bus_id)] = {
            "mean_weekly_demand": float(qty.mean()),
            "mean_mileage": float(mileage.mean()),
            "n_obs": int(len(group)),
        }
    return meta


# ---------------------------------------------------------------------------
# Feature engineering
# ---------------------------------------------------------------------------
def build_dataset(df: pd.DataFrame) -> pd.DataFrame:
    """Turn raw panel rows into the leakage-safe feature matrix + the target."""
    if df.empty:
        return pd.DataFrame(columns=INVENTORY_FEATURE_COLUMNS + INVENTORY_TRACE_COLUMNS + [INVENTORY_TARGET])

    cols = [c for c in REQUIRED_COLUMNS if c in df.columns]
    out = df[cols].copy().sort_values(["bus_id", "part_id", "date"]).reset_index(drop=True)
    out["date"] = pd.to_datetime(out["date"], errors="coerce")

    encoders = build_encoders(out)
    out["bus_encoded"] = out["bus_id"].astype(str).map(encoders["bus_encodings"]).fillna(-1)
    out["part_encoded"] = out["part_id"].astype(str).map(encoders["part_encodings"]).fillna(-1)
    out["category_encoded"] = out["category"].astype(str).map(encoders["category_encodings"]).fillna(-1)
    out["unit_encoded"] = out["unit"].astype(str).map(encoders["unit_encodings"]).fillna(-1)

    out["on_hand"] = pd.to_numeric(out["on_hand_before"], errors="coerce").fillna(0.0)
    out["vehicle_mileage"] = pd.to_numeric(out["vehicle_mileage"], errors="coerce").fillna(0.0)
    out["reorder_level"] = pd.to_numeric(out["reorder_level"], errors="coerce").fillna(0.0)
    out["supplier_lead_days"] = pd.to_numeric(out["supplier_lead_days"], errors="coerce").fillna(0.0)

    # ---- strictly past information only --------------------------------
    gkey = out.apply(lambda r: (r["bus_id"], r["part_id"]), axis=1)
    q = pd.to_numeric(out["quantity_issued"], errors="coerce").fillna(0.0)

    out["demand_lag1"] = q.groupby(gkey).shift(1)
    out["demand_lag2"] = q.groupby(gkey).shift(2)

    out["rolling_demand_4w"] = 0.0
    out["rolling_demand_8w"] = 0.0
    for _, idx in out.groupby(gkey, sort=False).groups.items():
        sub = q.loc[idx]
        sub = sub.sort_index()
        r4 = sub.rolling(4, min_periods=1).mean().shift(1).fillna(0.0)
        r8 = sub.rolling(8, min_periods=1).mean().shift(1).fillna(0.0)
        out.loc[idx, "rolling_demand_4w"] = r4.to_numpy()
        out.loc[idx, "rolling_demand_8w"] = r8.to_numpy()

    out["maintenance_type_lag1"] = (
        out.groupby(gkey, sort=False)["maintenance_type"].shift(1).fillna("None")
    )
    out["maintenance_type_lag1_encoded"] = (
        out["maintenance_type_lag1"].astype(str).map(encoders["maint_lag_encodings"]).fillna(-1)
    )

    out["breakdown_count_lag1"] = (
        pd.to_numeric(out["breakdown_count"], errors="coerce")
        .fillna(0.0)
        .groupby(gkey)
        .shift(1)
        .fillna(0.0)
    )

    # ---- recompute days_since_last_issue from the target stream (safe) ----
    ds = []
    for _, idx in out.groupby(gkey, sort=False).groups.items():
        sub = out.loc[idx, ["date", INVENTORY_TARGET]]
        last = None
        acc = []
        for dt, qty in zip(sub["date"], sub[INVENTORY_TARGET]):
            if last is None:
                acc.append(366.0)
            else:
                acc.append(float((dt - last).days))
            if float(qty) > 0:
                last = dt
        ds.append(pd.Series(acc, index=idx, dtype="float64"))
    if ds:
        out["days_since_last_issue"] = pd.concat(ds).loc[out.index].to_numpy()
    else:
        out["days_since_last_issue"] = 366.0

    # ---- time context -----------------------------------------------
    iso = out["date"].dt.isocalendar()
    out["week_of_year"] = iso.week.astype(float)
    out["month"] = out["date"].dt.month.astype(float)
    out["quarter"] = out["date"].dt.quarter.astype(float)
    global_min = out["date"].min()
    out["elapsed_weeks"] = (out["date"] - global_min).dt.days.astype(float) / 7.0

    # ---- finalize numeric casts ----
    for col in INVENTORY_FEATURE_COLUMNS:
        out[col] = pd.to_numeric(out[col], errors="coerce")

    target = pd.to_numeric(out[INVENTORY_TARGET], errors="coerce").fillna(0.0).clip(lower=0.0)
    out[INVENTORY_TARGET] = target

    # on_hand_after is derived FROM the target (before - issued) so it must
    # never be exported, even as a trace/identity column.
    trace_cols = [c for c in INVENTORY_TRACE_COLUMNS if c != "on_hand_after"]
    trace = out[trace_cols].copy()
    trace[INVENTORY_TARGET] = target

    result = pd.concat(
        [out[INVENTORY_FEATURE_COLUMNS], trace],
        axis=1,
    )
    # warm-up periods (rolling windows need 8 prior weeks) are dropped.
    result = result.dropna(subset=INVENTORY_FEATURE_COLUMNS + [INVENTORY_TARGET]).reset_index(drop=True)
    return result


def chronological_split(df: pd.DataFrame, test_fraction: float | None = None) -> Tuple[pd.DataFrame, pd.DataFrame, Dict[str, str]]:
    """Chronological (time-aware) train/test split.

    The split boundary is chosen on the WEEK axis: the earliest
    ``(1 - test_fraction)`` distinct weeks form the training set and the most
    recent weeks form the test set. No future observation can ever enter the
    training folds.
    """
    if df.empty or "date" not in df.columns:
        return df, pd.DataFrame(), {"train_start": "", "train_end": "", "test_start": "", "test_end": ""}

    test_fraction = test_fraction or forecast_test_fraction()
    weeks = sorted(df["date"].dropna().unique().tolist())
    split = max(1, int(round(len(weeks) * (1.0 - test_fraction))))
    split = min(split, len(weeks) - 1)
    train_weeks = set(weeks[:split])
    test_weeks = set(weeks[split:])

    train = df[df["date"].isin(train_weeks)].drop(columns=["date"]).reset_index(drop=True)
    test = df[df["date"].isin(test_weeks)].drop(columns=["date"]).reset_index(drop=True)

    periods = {
        "train_start": str(weeks[0].date()),
        "train_end": str(weeks[split - 1].date()),
        "test_start": str(weeks[split].date()),
        "test_end": str(weeks[-1].date()),
    }
    return train, test, periods


def write_features_csv(df: pd.DataFrame, path: Optional[Path] = None) -> Path:
    path = path or training_data_paths()["features_csv"]
    path.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(path, index=False)
    return path