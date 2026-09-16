"""End-to-end tests for the inventory demand forecasting model (#4).

Runs against the GENERATED SAMPLE dataset (sample_inventory_training.csv).
Every test is a self-contained PASS/FAIL check in the project's script style
(the runtime image does not bundle pytest):

    1.  Dataset integrity vs required columns / ranges / uniqueness
    2.  Per-series chronology
    3.  Readiness thresholds PASS
    4.  Feature matrix built: exact column set, NO target, NO forbidden fields
    5.  Lag correctness: demand_lag1 equals the PREVIOUS week's issuance
    6.  Chronological split (train is strictly earlier than test)
    7.  Model trains, artifacts + state are written
    8.  Metrics generated: MAE / RMSE / R2 finite and plausible
    9.  Reload from disk and predict deterministically, non-negative, in range
    10. Part lookup by id AND by part name
    11. FastAPI /inventory/status returns model_ready=true
    12. FastAPI /inventory/predict returns a prediction with risk assessment
    13. Unknown part -> 404; model unavailable -> 503
    14. Business-rule threshold detection + recommendation correctness
    15. HONEST-labelling: disclaimer attached, source stays 'sample'

Run with:
    python -m inventory.test_inventory
"""

import json
import sys
from pathlib import Path

logging_basic = __import__("logging").basicConfig
logging_basic(level=__import__("logging").WARNING)

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import joblib  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402

from inventory.config import model_paths, training_data_paths  # noqa: E402
from inventory.model import save_inventory_model, save_state, train_inventory_model  # noqa: E402
from inventory.training_data import (  # noqa: E402
    FORBIDDEN_FEATURES,
    INVENTORY_FEATURE_COLUMNS,
    INVENTORY_TARGET,
    REQUIRED_COLUMNS,
    build_bus_metadata,
    build_encoders,
    build_part_metadata,
    check_readiness_thresholds,
    chronological_split,
    forecast_test_fraction,
    validate_dataset,
)
from inventory import predict as inventory_predict  # noqa: E402
from inventory.predict import evaluate_recommendation  # noqa: E402

PASS = 0
FAIL = 0


def check(label: str, condition: bool, detail: str = "") -> None:
    global PASS, FAIL
    status = "PASS" if condition else "FAIL"
    if condition:
        PASS += 1
    else:
        FAIL += 1
    suffix = f" ({detail})" if detail else ""
    print(f"  [{status}] {label}{suffix}")


print("\n=== Inventory model #4 tests (SAMPLE / DEVELOPMENT data) ===")
print("DISCLAIMER: sample data only - NOT actual GCT operational data.")

csv_path = training_data_paths()["csv"]
features_path = training_data_paths()["features_csv"]
paths = model_paths()

if not csv_path.exists():
    print("  [FAIL] sample training CSV not found:", csv_path)
    print("  Run `python -m inventory.sample_data.generate_sample_data` first.")
    sys.exit(1)

# ============================================================
# 1. Dataset integrity + 2. chronology
# ============================================================
print("\n--- Dataset integrity ---")
df = pd.read_csv(csv_path)
check("sample CSV exists", csv_path.exists(), str(csv_path))
check(
    "all required schema columns present",
    all(c in df.columns for c in REQUIRED_COLUMNS),
    f"{len(df)} rows",
)
check("no negative issued quantity", bool((df["quantity_issued"] >= 0).all()))
check("no negative on-hand value (before)", bool((df["on_hand_before"] >= 0).all()))
check("no negative on-hand value (after)", bool((df["on_hand_after"] >= 0).all()))
check(
    "single-pass accounting (after = before - issued)",
    bool((df["on_hand_after"] == (df["on_hand_before"] - df["quantity_issued"])).all()),
)
check("no negative mileage", bool((df["vehicle_mileage"] >= 0).all()))
check("dates parse and are end-of-week (Mon)", all(pd.to_datetime(df["date"]).dt.dayofweek == 0))
check("no duplicate (date, bus, part)", int(df.duplicated(["date", "bus_id", "part_id"]).sum()) == 0)
check("buses present (>= 15)", df["bus_id"].nunique() >= 15, f"{df['bus_id'].nunique()} buses")
check("parts present (>= 25)", df["part_id"].nunique() >= 25, f"{df['part_id'].nunique()} parts")
check("months-of-history (>= 8)", df["date"].nunique() >= 8 * 4, f"{df['date'].nunique()} weeks")
check(
    "two maintenance types",
    set(df["maintenance_type"].dropna()) == {"Preventive", "Corrective"},
)

bad_series = 0
for _, g in df.groupby(["bus_id", "part_id"], observed=True):
    d = g.sort_values("date")["date"].to_numpy()
    if len(d) > 1 and (d[1:] < d[:-1]).any():
        bad_series += 1
check("every (bus, part) series is chronologically ordered", bad_series == 0, f"{bad_series} bad series")

# ============================================================
# 3. Readiness thresholds + validation report
# ============================================================
print("\n--- Readiness ---")
valid, errors, report = validate_dataset(df)
check("validation passes (chronology + schema)", valid, f"errors={errors[:1]}")
thresh_ok, thresh_issues = check_readiness_thresholds(df)
check("readiness thresholds PASS", thresh_ok, "issues=" + ",".join(thresh_issues))
check(
    "date range in report",
    "date_min" in report and "date_max" in report,
    f"{report.get('date_min')}..{report.get('date_max')}",
)

# ============================================================
# 4. Feature matrix: columns / no target / no forbidden fields
# ============================================================
print("\n--- Feature matrix ---")
wide = pd.read_csv(features_path)
wide["date"] = pd.to_datetime(wide["date"], errors="coerce")
wide["date"] = pd.DatetimeIndex(wide["date"]).normalize()
check("feature CSV exists", features_path.exists(), str(features_path))
check(
    "feature matrix has the exact trained feature set",
    set(INVENTORY_FEATURE_COLUMNS).issubset(set(wide.columns)),
    f"{len(INVENTORY_FEATURE_COLUMNS)} features",
)
check("target column absent from features", INVENTORY_TARGET not in INVENTORY_FEATURE_COLUMNS)
check(
    "no forbidden/derived feature in the feature list",
    len(set(FORBIDDEN_FEATURES) & set(INVENTORY_FEATURE_COLUMNS)) == 0,
    f"forbidden={sorted(set(FORBIDDEN_FEATURES) & set(INVENTORY_FEATURE_COLUMNS))}",
)
check("on_hand_after is not even written to the feature matrix", "on_hand_after" not in wide.columns)
check("feature rows > 0", len(wide) > 0, f"{len(wide)} rows")

# ============================================================
# 5. Lag correctness: demand_lag1 == previous week's issuance
# ============================================================
print("\n--- Lag correctness ---")
lag_mismatches = 0
checked = 0
for (bid, pid), g in wide.sort_values("date").groupby(["bus_id", "part_id"], observed=True):
    prev_qty = g["quantity_issued"].shift(1)
    got = g["demand_lag1"]
    mask = prev_qty.notna()
    bad = int(((prev_qty[mask] != got[mask])).sum())
    lag_mismatches += bad
    checked += int(mask.sum())
check(
    "demand_lag1 equals the previous week's issuance on all non-warm-up rows",
    lag_mismatches == 0,
    f"{checked} checked, {lag_mismatches} mismatches",
)

# ============================================================
# 6. Chronological split
# ============================================================
print("\n--- Chronological split ---")
train_df, test_df, periods = chronological_split(wide, forecast_test_fraction())
check(
    "train strictly before test (periods)",
    bool(periods["train_start"]) and bool(periods["test_start"])
    and pd.Timestamp(periods["train_end"]) < pd.Timestamp(periods["test_start"]),
    f"{periods['train_start']}..{periods['train_end']} | {periods['test_start']}..{periods['test_end']}",
)
share = len(test_df) / max(len(train_df) + len(test_df), 1)
check("test fraction ~0.2", abs(share - 0.2) < 0.02, f"{share:.3f}")

# ============================================================
# 7 + 8. Train, metrics, artifacts
# ============================================================
print("\n--- Train / metrics / artifacts ---")
result = train_inventory_model(wide, source="sample")
check("model trains successfully", result.trained, result.message)
check("train/test counts positive", result.n_train > 0 and result.n_test > 0,
      f"train={result.n_train} test={result.n_test}")
check("MAE generated and finite", "mae" in result.metrics and np.isfinite(result.metrics["mae"]))
check("RMSE generated and finite", "rmse" in result.metrics and np.isfinite(result.metrics["rmse"]))
check("R2 in plausible range", -1.0 <= result.metrics["r2"] <= 1.0, f"R2={result.metrics.get('r2'):.4f}")
check(
    "feature importances reported",
    len(result.feature_importances) == len(INVENTORY_FEATURE_COLUMNS),
)

artifacts_saved = True
try:
    encoders = build_encoders(wide)
    save_inventory_model(result, paths, encoders, build_part_metadata(wide), build_bus_metadata(wide))
    save_state(result, paths)
    for suffix in ["rf.pkl", "features.json", "report.txt", "state.json"]:
        path = paths["dir"] / f"inventory_demand_{suffix}"
        artifacts_saved = artifacts_saved and path.exists()
except Exception as exc:  # noqa: BLE001
    artifacts_saved = False
    print(f"    artifact save failed: {exc}")
check("all four model artifacts written", artifacts_saved, str(paths["dir"]))

# ============================================================
# 9 + 10. Reload & prediction
# ============================================================
print("\n--- Reload & prediction ---")
inventory_predict.reset_cache()
loaded = joblib.load(paths["model"])
check("model reloads from disk", hasattr(loaded, "predict"))

status = inventory_predict.inventory_readiness()
check("readiness reports ready", status.ml_ready, status.reason)

pred1 = inventory_predict.predict_inventory_demand("GCT-101", "P001", current_stock=12)
pred2 = inventory_predict.predict_inventory_demand("GCT-101", "P001", current_stock=12)
check("prediction is not None", pred1 is not None)
check(
    "prediction is deterministic",
    pred1 is not None and pred2 is not None
    and pred1.predicted_quantity_issued == pred2.predicted_quantity_issued,
)
check("prediction non-negative", pred1 is not None and pred1.predicted_quantity_issued >= 0)
check("prediction unit comes from part metadata", pred1 is not None and bool(pred1.unit))
state = json.loads(paths["state"].read_text(encoding="utf-8"))
target_max = float((state.get("target_range") or {}).get("max", 0) or 0)
check(
    "prediction within observed training range",
    pred1 is not None and pred1.predicted_quantity_issued <= max(target_max, 0) + 1e-9,
    f"pred={pred1.predicted_quantity_issued:.3f} max={target_max}",
)
check("forecast period is next_week", pred1 is not None and pred1.forecast_period == "next_week")
check("part resolvable by name too", inventory_predict.resolve_part("Oil Filter") is not None)
check("unknown part resolves to None", inventory_predict.resolve_part("NOPE-99") is None)

# ============================================================
# 11 + 12 + 13. FastAPI router (status / predict / error handling)
# ============================================================
print("\n--- FastAPI router ---")
from fastapi import FastAPI  # noqa: E402
from fastapi.testclient import TestClient  # noqa: E402
from inventory.router import router as inv_router  # noqa: E402

app = FastAPI()
app.include_router(inv_router, prefix="/inventory")
client = TestClient(app)

r = client.get("/inventory/status")
check("GET /inventory/status -> 200", r.status_code == 200, f"http {r.status_code}")
check("status body reports model ready", r.json().get("model_ready") is True)
check("status body is honest about the sample source", r.json().get("source") == "sample")

resp = client.post(
    "/inventory/predict",
    json={"bus_id": "GCT-101", "part_id": "P001", "current_stock": 8},
)
check("POST /inventory/predict -> 200", resp.status_code == 200, f"http {resp.status_code}")
body = resp.json()
check(
    "predict body has forecast + assessment layers",
    "predicted_quantity_issued" in body and "risk_status" in body and "recommended_action" in body,
)
check("predict body non-empty unit", bool(body.get("unit")))
check(
    "predict body risk_status is one of the enum",
    body.get("risk_status") in {"STOCKOUT_RISK", "LOW_STOCK", "ELEVATED_DEMAND", "NORMAL"},
)

r404 = client.post("/inventory/predict", json={"bus_id": "GCT-101", "part_id": "MISSING-PART"})
check("unknown part -> 404", r404.status_code == 404, f"http {r404.status_code}")

# not-ready -> 503 by pointing the service at a nonexistent model directory
inventory_predict.reset_cache()
orig_paths = inventory_predict._paths
fake = {
    "model": Path("inventory/__missing__/inventory_demand_rf.pkl"),
    "features": Path("inventory/__missing__/inventory_demand_features.json"),
    "report": Path("inventory/__missing__/inventory_demand_report.txt"),
    "state": Path("inventory/__missing__/inventory_demand_state.json"),
    "dir": Path("inventory/__missing__"),
}
inventory_predict._paths = fake
client2 = TestClient(app)
r503 = client2.post("/inventory/predict", json={"bus_id": "GCT-101", "part_id": "P001"})
check("model unavailable -> 503", r503.status_code == 503, f"http {r503.status_code}")
inventory_predict._paths = orig_paths
inventory_predict.reset_cache()

# ============================================================
# 14. Business-rule threshold detection
# ============================================================
print("\n--- Business-rule layer ---")
rec = evaluate_recommendation(5.0, 2.0, 3.0, 4.0)
check(
    "stockout risk detected",
    rec["risk_status"] == "STOCKOUT_RISK" and rec["risk_flags"]["stockout_risk"],
    rec["risk_status"],
)
check(
    "suggested order qty = reorder + demand - stock",
    abs(rec["suggested_order_qty"] - (3 + 5 - 2)) < 1e-6,
)
rec2 = evaluate_recommendation(0.5, 3.0, 3.0, 4.0)
check("low stock detected at reorder level", rec2["risk_status"] == "LOW_STOCK", rec2["risk_status"])
rec3 = evaluate_recommendation(0.1, 50.0, 3.0, 4.0)
check("normal status when stock is ample", rec3["risk_status"] == "NORMAL", rec3["risk_status"])
check("rules transparently labelled as rules", "business-rule" in rec["rule_layer"], rec["rule_layer"])

# ============================================================
# 15. Honest-labelling guards
# ============================================================
print("\n--- Honest-labelling ---")
check("readiness doc ships with the package", Path("inventory/INVENTORY_MODEL_READINESS.md").exists())
check(
    "predictions embed the SAMPLE disclaimer string",
    pred1 is not None and "SAMPLE / DEVELOPMENT" in pred1.disclaimer,
)
check("model state source is 'sample' (never 'app')", status.source == "sample", status.source)

print(f"\n=== Inventory tests: {PASS} passed, {FAIL} failed ===")
sys.exit(1 if FAIL else 0)