"""Inventory Model #4 data loading, validation and feature engineering.

Development may use generated sample data. Genuine mode reads only warehouse
ledger rows written by the application (``stock_movements.source='app'``) and
converts them into a weekly fleet-level spare-part demand panel.

The genuine model intentionally forecasts demand by PART, not by BUS. The
warehouse ledger does not reliably identify a vehicle for every issuance, so
inventing a bus id would corrupt provenance. Genuine rows use the explicit
scope id ``FLEET`` and retain the real part, quantity, stock and timestamp
history from the ledger.
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import pandas as pd

from .config import data_source, data_thresholds, forecast_test_fraction, training_data_paths

logger = logging.getLogger(__name__)

INVENTORY_TARGET = "quantity_issued"
GENUINE_SCOPE_ID = "FLEET"

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

FORBIDDEN_FEATURES: List[str] = [
    INVENTORY_TARGET,
    "on_hand_after",
    "maintenance_type",
    "breakdown_count",
    "part_name",
]

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


def load_sample_csv(path: Optional[Path] = None) -> pd.DataFrame:
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
    source = data_source()
    if source == "genuine":
        return fetch_genuine_stock_movements(), "genuine"
    if source == "sample":
        return load_sample_csv(path), "sample"
    raise ValueError(f"Unknown inventory data source: {source!r}")


def fetch_genuine_stock_movements() -> pd.DataFrame:
    """Build a weekly panel from genuine application-written ledger rows."""
    from operation_ai.ml.database import DbConnection

    db = DbConnection()
    try:
        sql = """
            SELECT
                sm.id                                   AS movement_id,
                sm.created_at                           AS activity_at,
                sm.inventory_item_id                    AS part_id,
                COALESCE(ii.item_code, sm.item_code)   AS item_code,
                COALESCE(ii.item_name, sm.item_name)   AS part_name,
                COALESCE(ii.category, 'Uncategorized') AS category,
                COALESCE(sm.unit, ii.unit_of_measurement, 'pcs') AS unit,
                sm.reference_no                         AS reference_no,
                sm.movement_type                        AS movement_type,
                sm.quantity_change                      AS quantity_change,
                sm.previous_stock                       AS previous_stock,
                sm.new_stock                            AS new_stock,
                COALESCE(ii.reorder_level, 0)           AS reorder_level
            FROM stock_movements sm
            LEFT JOIN inventory_items ii
              ON ii.id = sm.inventory_item_id
            WHERE sm.source = 'app'
              AND sm.inventory_item_id IS NOT NULL
            ORDER BY sm.inventory_item_id, sm.created_at, sm.id
        """
        df = db.query_df(sql)
    finally:
        db.close()

    if df.empty:
        logger.warning(
            "No genuine source='app' stock movements found. Inventory Model #4 "
            "remains MODEL NOT READY; no sample fallback will be used."
        )
        return pd.DataFrame(columns=REQUIRED_COLUMNS + ["stock_out_events", "data_source"])

    return _panelize_genuine_movements(df)


def _panelize_genuine_movements(df: pd.DataFrame) -> pd.DataFrame:
    """Aggregate real ledger movements into one row per part per week.

    ``quantity_issued`` is the sum of real Stock Out quantities in that week.
    Weeks without an issue are retained with zero demand so the model sees
    actual quiet periods rather than only positive-demand events.
    """
    if df.empty:
        return pd.DataFrame(columns=REQUIRED_COLUMNS + ["stock_out_events", "data_source"])

    parsed = df.copy()
    if "activity_at" not in parsed.columns and "activity_date" in parsed.columns:
        parsed["activity_at"] = parsed["activity_date"]

    parsed["activity_at"] = pd.to_datetime(parsed["activity_at"], errors="coerce")
    parsed = parsed.dropna(subset=["activity_at", "part_id"]).copy()
    if parsed.empty:
        return pd.DataFrame(columns=REQUIRED_COLUMNS + ["stock_out_events", "data_source"])

    parsed["week_start"] = (
        parsed["activity_at"].dt.to_period("W-SUN").dt.start_time
    )
    parsed["quantity_change"] = pd.to_numeric(
        parsed["quantity_change"], errors="coerce"
    ).fillna(0.0)
    parsed["previous_stock"] = pd.to_numeric(
        parsed["previous_stock"], errors="coerce"
    ).fillna(0.0)
    parsed["new_stock"] = pd.to_numeric(
        parsed["new_stock"], errors="coerce"
    ).fillna(0.0)
    parsed["reorder_level"] = pd.to_numeric(
        parsed.get("reorder_level", 0), errors="coerce"
    ).fillna(0.0)

    rows: List[Dict[str, object]] = []

    for part_id, group in parsed.groupby("part_id", observed=True):
        group = group.sort_values(["activity_at", "movement_id"] if "movement_id" in group.columns else ["activity_at"])
        first_week = group["week_start"].min()
        last_week = group["week_start"].max()
        meta = group.iloc[-1]
        carry_stock: Optional[float] = None

        for week_start in pd.date_range(first_week, last_week, freq="7D"):
            week_rows = group[group["week_start"] == week_start]

            if week_rows.empty:
                opening = float(carry_stock or 0.0)
                closing = opening
                issued = 0.0
                event_count = 0
            else:
                opening = float(week_rows.iloc[0]["previous_stock"])
                closing = float(week_rows.iloc[-1]["new_stock"])
                stock_out = week_rows[
                    week_rows["movement_type"].astype(str).str.strip().str.casefold()
                    == "stock out"
                ]
                issued = float(stock_out["quantity_change"].abs().sum())
                event_count = int(len(stock_out))
                carry_stock = closing

            rows.append(
                {
                    "date": pd.Timestamp(week_start),
                    "bus_id": GENUINE_SCOPE_ID,
                    "part_id": str(part_id),
                    "part_name": str(meta.get("part_name") or meta.get("item_code") or part_id),
                    "category": str(meta.get("category") or "Uncategorized"),
                    "quantity_issued": issued,
                    "on_hand_before": max(0.0, opening),
                    "on_hand_after": max(0.0, closing),
                    "reorder_level": max(0.0, float(meta.get("reorder_level") or 0.0)),
                    "unit": str(meta.get("unit") or "pcs"),
                    "maintenance_type": "None",
                    "vehicle_mileage": 0.0,
                    "breakdown_count": 0.0,
                    "days_since_last_issue": 366.0,
                    "supplier_lead_days": 0.0,
                    "stock_out_events": event_count,
                    "data_source": "genuine",
                }
            )

    panel = pd.DataFrame(rows).sort_values(["part_id", "date"]).reset_index(drop=True)
    return panel


def validate_dataset(df: pd.DataFrame) -> Tuple[bool, List[str], Dict[str, object]]:
    errors: List[str] = []
    report: Dict[str, object] = {}

    missing_cols = [c for c in REQUIRED_COLUMNS if c not in df.columns]
    if missing_cols:
        errors.append(f"Missing required columns: {missing_cols}")
        return False, errors, report

    n = len(df)
    report["total_rows"] = int(n)
    if n == 0:
        errors.append("No inventory training observations are available.")
        return False, errors, report

    df = df.copy()
    df["date"] = pd.to_datetime(df["date"], errors="coerce")
    if df["date"].isna().any():
        errors.append(f"{int(df['date'].isna().sum())} rows have invalid dates.")
    if df["date"].notna().any():
        report["date_min"] = str(df["date"].min().date())
        report["date_max"] = str(df["date"].max().date())

    for col in ["bus_id", "part_id", "part_name", "category", "unit"]:
        if df[col].isna().any() or (df[col].astype(str).str.strip() == "").any():
            errors.append(f"Column {col!r} has empty/missing values.")

    if not df.groupby("part_id")["unit"].nunique().le(1).all():
        errors.append("Some parts have more than one unit of measure.")

    for col in ["quantity_issued", "on_hand_before", "on_hand_after"]:
        numeric = pd.to_numeric(df[col], errors="coerce")
        if numeric.isna().any():
            errors.append(f"Column {col!r} contains non-numeric values.")
        elif (numeric < 0).any():
            errors.append(f"Negative {col} found.")

    fleet_scope = set(df["bus_id"].astype(str).unique()) == {GENUINE_SCOPE_ID}
    if not fleet_scope:
        arithmetic = (
            pd.to_numeric(df["on_hand_before"], errors="coerce")
            - pd.to_numeric(df["quantity_issued"], errors="coerce")
            - pd.to_numeric(df["on_hand_after"], errors="coerce")
        )
        bad = int((arithmetic.abs() > 1e-9).sum())
        if bad:
            errors.append(
                f"{bad} rows violate on_hand_after = on_hand_before - quantity_issued."
            )

    dupes = int(df.duplicated(subset=["date", "bus_id", "part_id"]).sum())
    if dupes:
        errors.append(f"{dupes} duplicate (date, scope, part) observations.")

    report["buses"] = int(df["bus_id"].nunique())
    report["parts"] = int(df["part_id"].nunique())
    report["categories"] = int(df["category"].nunique())
    report["maintenance_types"] = sorted(
        {str(t) for t in df["maintenance_type"].tolist() if pd.notna(t)}
    )
    qty = pd.to_numeric(df["quantity_issued"], errors="coerce").fillna(0)
    report["avg_quantity_issued"] = round(float(qty.mean()), 4)
    report["zero_demand_pct"] = round(float((qty == 0).mean() * 100.0), 2)
    report["min_demand"] = float(qty.min())
    report["max_demand"] = float(qty.max())
    report["obs_per_part_min"] = int(df.groupby("part_id").size().min())
    report["obs_per_part_max"] = int(df.groupby("part_id").size().max())
    report["obs_per_bus_min"] = int(df.groupby("bus_id").size().min())
    report["obs_per_bus_max"] = int(df.groupby("bus_id").size().max())
    report["obs_per_part"] = df.groupby("part_id").size().to_dict()
    report["obs_per_bus"] = df.groupby("bus_id").size().to_dict()
    report["stock_out_events"] = int(
        pd.to_numeric(df.get("stock_out_events", 0), errors="coerce").fillna(0).sum()
        if "stock_out_events" in df.columns
        else 0
    )

    return len(errors) == 0, errors, report


def check_readiness_thresholds(df: pd.DataFrame) -> Tuple[bool, List[str]]:
    thresholds = data_thresholds()
    issues: List[str] = []
    if df.empty:
        return False, ["no genuine observations"]

    if len(df) < thresholds["min_rows"]:
        issues.append(f"rows {len(df)} < {thresholds['min_rows']}")

    fleet_scope = set(df["bus_id"].astype(str).unique()) == {GENUINE_SCOPE_ID}
    if not fleet_scope and df["bus_id"].nunique() < thresholds["min_buses"]:
        issues.append(f"buses {df['bus_id'].nunique()} < {thresholds['min_buses']}")

    if df["part_id"].nunique() < thresholds["min_parts"]:
        issues.append(f"parts {df['part_id'].nunique()} < {thresholds['min_parts']}")

    n_weeks = pd.to_datetime(df["date"], errors="coerce").nunique()
    if n_weeks < thresholds["min_weeks"]:
        issues.append(f"weeks {n_weeks} < {thresholds['min_weeks']}")

    min_events = int(thresholds.get("min_stock_out_events", 0))
    if fleet_scope and min_events > 0:
        events = int(
            pd.to_numeric(df.get("stock_out_events", 0), errors="coerce").fillna(0).sum()
            if "stock_out_events" in df.columns
            else 0
        )
        if events < min_events:
            issues.append(f"genuine Stock Out events {events} < {min_events}")

    return len(issues) == 0, issues


def build_encoders(df: pd.DataFrame) -> Dict[str, Dict[str, int]]:
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
    meta: Dict[str, Dict[str, object]] = {}
    for part_id, group in df.groupby("part_id", observed=True):
        row = group.iloc[0]
        qty = pd.to_numeric(group["quantity_issued"], errors="coerce").fillna(0)
        meta[str(part_id)] = {
            "name": str(row["part_name"]),
            "category": str(row["category"]),
            "unit": str(row["unit"]),
            "reorder_level": int(float(row["reorder_level"])),
            "supplier_lead_days": int(float(row.get("supplier_lead_days", 0) or 0)),
            "mean_weekly_demand": float(qty.mean()),
            "zero_share": float((qty == 0).mean()),
            "n_obs": int(len(group)),
            "mean_on_hand": float(
                pd.to_numeric(group["on_hand_before"], errors="coerce").mean()
            ),
            "mean_days_since_last_issue": float(
                pd.to_numeric(group["days_since_last_issue"], errors="coerce").mean()
            ),
        }
    return meta


def build_bus_metadata(df: pd.DataFrame) -> Dict[str, Dict[str, float]]:
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


def build_dataset(df: pd.DataFrame) -> pd.DataFrame:
    """Create a leakage-safe feature matrix from the raw weekly panel."""
    if df.empty:
        return pd.DataFrame(
            columns=INVENTORY_FEATURE_COLUMNS + INVENTORY_TRACE_COLUMNS + [INVENTORY_TARGET]
        )

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

    gkey = out.apply(lambda r: (r["bus_id"], r["part_id"]), axis=1)
    q = pd.to_numeric(out[INVENTORY_TARGET], errors="coerce").fillna(0.0)

    out["demand_lag1"] = q.groupby(gkey).shift(1)
    out["demand_lag2"] = q.groupby(gkey).shift(2)
    out["rolling_demand_4w"] = 0.0
    out["rolling_demand_8w"] = 0.0

    for _, idx in out.groupby(gkey, sort=False).groups.items():
        sub = q.loc[idx].sort_index()
        out.loc[idx, "rolling_demand_4w"] = (
            sub.rolling(4, min_periods=1).mean().shift(1).fillna(0.0).to_numpy()
        )
        out.loc[idx, "rolling_demand_8w"] = (
            sub.rolling(8, min_periods=1).mean().shift(1).fillna(0.0).to_numpy()
        )

    out["maintenance_type_lag1"] = (
        out.groupby(gkey, sort=False)["maintenance_type"].shift(1).fillna("None")
    )
    out["maintenance_type_lag1_encoded"] = (
        out["maintenance_type_lag1"].astype(str)
        .map(encoders["maint_lag_encodings"])
        .fillna(-1)
    )
    out["breakdown_count_lag1"] = (
        pd.to_numeric(out["breakdown_count"], errors="coerce")
        .fillna(0.0)
        .groupby(gkey)
        .shift(1)
        .fillna(0.0)
    )

    day_series: List[pd.Series] = []
    for _, idx in out.groupby(gkey, sort=False).groups.items():
        sub = out.loc[idx, ["date", INVENTORY_TARGET]]
        last_issue = None
        values: List[float] = []
        for dt, qty in zip(sub["date"], sub[INVENTORY_TARGET]):
            values.append(366.0 if last_issue is None else float((dt - last_issue).days))
            if float(qty) > 0:
                last_issue = dt
        day_series.append(pd.Series(values, index=idx, dtype="float64"))
    out["days_since_last_issue"] = (
        pd.concat(day_series).loc[out.index].to_numpy() if day_series else 366.0
    )

    iso = out["date"].dt.isocalendar()
    out["week_of_year"] = iso.week.astype(float)
    out["month"] = out["date"].dt.month.astype(float)
    out["quarter"] = out["date"].dt.quarter.astype(float)
    global_min = out["date"].min()
    out["elapsed_weeks"] = (out["date"] - global_min).dt.days.astype(float) / 7.0

    for col in INVENTORY_FEATURE_COLUMNS:
        out[col] = pd.to_numeric(out[col], errors="coerce")

    target = pd.to_numeric(out[INVENTORY_TARGET], errors="coerce").fillna(0.0).clip(lower=0.0)
    out[INVENTORY_TARGET] = target

    trace_cols = [c for c in INVENTORY_TRACE_COLUMNS if c != "on_hand_after"]
    trace = out[trace_cols].copy()
    trace[INVENTORY_TARGET] = target
    result = pd.concat([out[INVENTORY_FEATURE_COLUMNS], trace], axis=1)
    return result.dropna(
        subset=INVENTORY_FEATURE_COLUMNS + [INVENTORY_TARGET]
    ).reset_index(drop=True)


def chronological_split(
    df: pd.DataFrame, test_fraction: float | None = None
) -> Tuple[pd.DataFrame, pd.DataFrame, Dict[str, str]]:
    if df.empty or "date" not in df.columns:
        return df, pd.DataFrame(), {
            "train_start": "",
            "train_end": "",
            "test_start": "",
            "test_end": "",
        }

    work = df.copy()
    work["date"] = pd.to_datetime(work["date"], errors="coerce")
    test_fraction = test_fraction or forecast_test_fraction()
    weeks = sorted(work["date"].dropna().unique().tolist())
    if len(weeks) < 2:
        return work.iloc[0:0], work.iloc[0:0], {
            "train_start": "",
            "train_end": "",
            "test_start": "",
            "test_end": "",
        }

    split = max(1, int(round(len(weeks) * (1.0 - test_fraction))))
    split = min(split, len(weeks) - 1)
    train_weeks = set(weeks[:split])
    test_weeks = set(weeks[split:])

    train = work[work["date"].isin(train_weeks)].drop(columns=["date"]).reset_index(drop=True)
    test = work[work["date"].isin(test_weeks)].drop(columns=["date"]).reset_index(drop=True)
    periods = {
        "train_start": str(pd.Timestamp(weeks[0]).date()),
        "train_end": str(pd.Timestamp(weeks[split - 1]).date()),
        "test_start": str(pd.Timestamp(weeks[split]).date()),
        "test_end": str(pd.Timestamp(weeks[-1]).date()),
    }
    return train, test, periods


def write_features_csv(df: pd.DataFrame, path: Optional[Path] = None) -> Path:
    path = path or training_data_paths()["features_csv"]
    path.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(path, index=False)
    return path
