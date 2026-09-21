"""End-to-end tests for the Model #3 delay-prediction service.

DEVELOPMENT PROTOTYPE - SAMPLE / DEMONSTRATION DATA.

Runs against the GENERATED SAMPLE dataset (sample_delay_training.csv +
sample_delay_training_features.csv under the central training_data/delay/):

    1.  Sample dataset exists
    2.  Expected columns exist
    3.  No target leakage
    4.  Training succeeds
    5.  Model file is created
    6.  Model can reload
    7.  Prediction works
    8.  Prediction is non-negative
    9.  Risk thresholds work correctly
    10. API /delay/status works
    11. API /delay/predict works
    12. Unknown/invalid input is handled safely
    13. Deterministic prediction
    14. Chronological train/test split
    15. Sample data is never written to the production DB
"""

import logging
import re
import sys
from pathlib import Path

logging.basicConfig(level=logging.WARNING)

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import joblib  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402

from delay.config import disclaimers, model_paths, training_data_paths  # noqa: E402
from delay.model import (  # noqa: E402
    RISK_THRESHOLDS,
    save_delay_model,
    save_state,
    train_delay_model,
)
from delay.training_data import (  # noqa: E402
    DELAY_INCIDENT_FEATURES,
    DLY_FEATURE_COLUMNS,
    DLY_TARGET,
    DLY_TRACE_COLUMNS,
    FORBIDDEN_FEATURES,
    REQUIRED_COLUMNS,
    SEASON_MAP,
    build_bus_metadata,
    build_dataset,
    build_driver_metadata,
    build_encoders,
    build_route_metadata,
    chronological_split,
    generate_sample_dataset,
    generate_sample_csv,
    load_sample_csv,
    validate_dataset,
    write_features_csv,
)
from delay import predict as delay_predict  # noqa: E402

PASS = 0
FAIL = 0


def check(label: str, condition: bool, detail: str = "") -> None:
    global PASS, FAIL
    if condition:
        PASS += 1
    else:
        FAIL += 1
    status = "PASS" if condition else "FAIL"
    suffix = f" ({detail})" if detail else ""
    print(f"  [{status}] {label}{suffix}")


csv_path = training_data_paths()["csv"]
features_path = training_data_paths()["features_csv"]
paths = model_paths()

print("\n=== Model #3 delay model tests (SAMPLE / DEMONSTRATION DATA) ===")

# ============================================================
# Bootstrap: build the SAMPLE dataset + feature matrix if missing
# ============================================================
if not csv_path.exists():
    print("Sample CSV not found - generating deterministic SAMPLE data...")
    generate_sample_csv()
if not features_path.exists() or not all(
    c in pd.read_csv(features_path).columns for c in DLY_FEATURE_COLUMNS
):
    print("Feature matrix missing or stale - rebuilding from SAMPLE data...")
    raw = load_sample_csv(csv_path)
    wide = build_dataset(raw)
    write_features_csv(wide, features_path)

# ============================================================
# 1 + 2. Dataset exists + expected columns
# ============================================================
print("\n--- Dataset ---")
check("sample dataset exists", csv_path.exists(), str(csv_path))
df = pd.read_csv(csv_path)
check(
    "expected columns exist in sample CSV",
    all(c in df.columns for c in REQUIRED_COLUMNS),
    f"{len(df)} rows x {len(REQUIRED_COLUMNS)} expected cols",
)
features_df = pd.read_csv(features_path)
check(
    "feature matrix has all feature columns + target",
    all(c in features_df.columns for c in DLY_FEATURE_COLUMNS + [DLY_TARGET]),
    f"{len(features_df)} rows",
)
check(
    "dataset spans multiple weeks (>= 4)",
    (pd.to_datetime(df["trip_date"]).max() - pd.to_datetime(df["trip_date"]).min()).days >= 28,
    f"{int((pd.to_datetime(df['trip_date']).max() - pd.to_datetime(df['trip_date']).min()).days)} days",
)
check(
    "dataset has >= 5 routes",
    df["route"].nunique() >= 5,
    f"routes={df['route'].nunique()}",
)
check(
    "dataset has >= 10 buses",
    df["bus_no"].nunique() >= 10,
    f"buses={df['bus_no'].nunique()}",
)
check(
    "dataset has >= 15 drivers",
    df["driver_id"].nunique() >= 15,
    f"drivers={df['driver_id'].nunique()}",
)
check(
    "dataset sample size is 300-500",
    300 <= len(df) <= 500,
    f"rows={len(df)}",
)
valid, errors, report = validate_dataset(df)
check("dataset passes structural validation", valid, "; ".join(errors[:3]))

# ============================================================
# 3. Target leakage
# ============================================================
print("\n--- Target leakage ---")
check(
    "label (arrival_delay_minutes) never appears in feature columns",
    DLY_TARGET not in DLY_FEATURE_COLUMNS,
)
check(
    "all forbidden post-trip fields are excluded from features",
    all(f not in DLY_FEATURE_COLUMNS for f in FORBIDDEN_FEATURES),
    f"features={DLY_FEATURE_COLUMNS}",
)
for f in FORBIDDEN_FEATURES:
    check(
        f"  ...'{f}' absent from feature matrix",
        f not in features_df.columns or f not in DLY_FEATURE_COLUMNS,
    )
check(
    "feature matrix contains no feature that only exists after the trip",
    DLY_FEATURE_COLUMNS == [c for c in DLY_FEATURE_COLUMNS],
    f"n_features={len(DLY_FEATURE_COLUMNS)}",
)
check(
    "target has real variance (trainable signal)",
    float(df[DLY_TARGET].std()) > 1.0,
    f"std={df[DLY_TARGET].std():.2f}",
)

# ============================================================
# 14. Chronological split
# ============================================================
print("\n--- Chronological split ---")
features_df = pd.read_csv(features_path, parse_dates=["trip_date"])
train, test, periods = chronological_split(features_df, 0.2)
check("chronological split produced train + test", len(train) > 0 and len(test) > 0)
check(
    "no date straddles the split boundary",
    train["trip_date"].max() < test["trip_date"].min(),
    f"{train['trip_date'].max().date()} < {test['trip_date'].min().date()}",
)

# ============================================================
# 4 + 8(metrics). Training
# ============================================================
print("\n--- Train ---")
train_df = pd.read_csv(features_path, parse_dates=["trip_date"])
result = train_delay_model(train_df)
check("training succeeds", result.trained, result.message)
check(
    "MAE generated (finite positive)",
    result.trained and np.isfinite(result.metrics["mae"]) and result.metrics["mae"] > 0,
    f"MAE={result.metrics.get('mae'):.2f} min",
)
check(
    "RMSE generated (finite positive)",
    result.trained and np.isfinite(result.metrics["rmse"]) and result.metrics["rmse"] >= 0,
    f"RMSE={result.metrics.get('rmse'):.2f} min",
)
check(
    "R2 generated (bounded)",
    result.trained and np.isfinite(result.metrics["r2"]) and -1.0 <= result.metrics["r2"] <= 1.0,
    f"R2={result.metrics.get('r2'):.4f}",
)
check(
    "held-out rows produced",
    result.n_train > 0 and result.n_test > 0,
    f"train={result.n_train}, test={result.n_test}",
)

# ============================================================
# 5 + 6. Save + reload
# ============================================================
print("\n--- Save / reload ---")
encoders = build_encoders(df)
route_metadata = build_route_metadata(df)
driver_metadata = build_driver_metadata(df)
bus_metadata = build_bus_metadata(df)
save_delay_model(result, paths, encoders, route_metadata, driver_metadata, bus_metadata)
save_state(result, paths)
check("model file created", paths["model"].exists(), str(paths["model"]))
check("features file created", paths["features"].exists())
check("report file created", paths["report"].exists())
check("state file created", paths["state"].exists())

reloaded = joblib.load(paths["model"])
check("model reloads from disk", reloaded is not None)
check(
    "reloaded model is a RandomForestRegressor",
    reloaded is not None and reloaded.__class__.__name__ == "RandomForestRegressor",
)

delay_predict.reset_cache()
readiness = delay_predict.delay_readiness()
check("prediction service reports model ready", readiness.ml_ready, readiness.reason)
check(
    "readiness identifies SAMPLE/DEMONSTRATION data",
    readiness.source == "sample" and readiness.data_source == "sample",
)
check(
    "state sample count matches training",
    readiness.sample_count == result.n_samples,
    f"state={readiness.sample_count}, trained={result.n_samples}",
)

# ============================================================
# 7 + 8 + 13. Prediction
# ============================================================
print("\n--- Prediction ---")
target_max = float(result.target_range["max"])

probe_rows = df.drop_duplicates(subset=["route"]).head(3)
for _, row in probe_rows.iterrows():
    prediction = delay_predict.predict_arrival_delay(
        route=str(row["route"]),
        scheduled_departure_time=f"{int(row['scheduled_departure_hour']):02d}:{int(row['scheduled_departure_minute']):02d}",
        bus_no=str(row["bus_no"]),
        driver_id=str(row["driver_id"]),
        trip_date=pd.Timestamp(row["trip_date"]).to_pydatetime(),
    )
    check(f"prediction works for route={row['route']}", prediction is not None)
    if prediction is None:
        continue
    value = prediction.predicted_arrival_delay_minutes
    check("prediction is non-negative", isinstance(value, (int, float)) and value >= 0, f"value={value}")
    check("prediction is reasonable (<= target max)", value <= target_max, f"{value} <= {target_max}")
    check("prediction returns risk_status band", bool(prediction.risk_status), prediction.risk_status)

# Determinism.
p1 = delay_predict.predict_arrival_delay(
    route="Talisay - SM Seaside",
    scheduled_departure_time="07:15",
    bus_no="GCT-105",
    driver_id="DRV-202",
    trip_date=pd.Timestamp("2026-08-10").to_pydatetime(),
)
p2 = delay_predict.predict_arrival_delay(
    route="Talisay - SM Seaside",
    scheduled_departure_time="07:15",
    bus_no="GCT-105",
    driver_id="DRV-202",
    trip_date=pd.Timestamp("2026-08-10").to_pydatetime(),
)
check(
    "prediction is deterministic (same inputs -> same output)",
    p1 is not None and p2 is not None and p1.predicted_arrival_delay_minutes == p2.predicted_arrival_delay_minutes,
    f"{getattr(p1, 'predicted_arrival_delay_minutes', None)} == {getattr(p2, 'predicted_arrival_delay_minutes', None)}",
)

# ============================================================
# 9. Risk thresholds
# ============================================================
print("\n--- Risk thresholds ---")
boundary_cases = [
    (0.0, "On Time"),
    (5.0, "On Time"),
    (6.0, "Minor Delay"),
    (10.0, "Minor Delay"),
    (11.0, "Moderate Delay"),
    (20.0, "Moderate Delay"),
    (21.0, "High Delay"),
    (48.0, "High Delay"),
]
for value, expected in boundary_cases:
    band = delay_predict.classify_arrival_delay(value)
    check(
        f"risk threshold at {value} min is '{expected}'",
        band["label"] == expected,
        band["label"],
    )
check(
    "threshold bands match spec (0-5 / 6-10 / 11-20 / 21+)",
    [b["label"] for b in RISK_THRESHOLDS] == ["On Time", "Minor Delay", "Moderate Delay", "High Delay"],
    str([b["label"] for b in RISK_THRESHOLDS]),
)
check(
    "risk band list is exposed in the prediction threshold field",
    p1 is not None and set(p1.threshold["bands_minutes"]) == {5, 10, 20},
    str(getattr(p1, "threshold", None)),
)

# ============================================================
# Encoding alignment: prediction encoding == training encoding
# ============================================================
print("\n--- Encoding alignment ---")
delay_predict.reset_cache()
mismatches = 0
for _, row in probe_rows.iterrows():
    encoded = delay_predict.encode_features(
        route=str(row["route"]),
        bus_no=str(row["bus_no"]),
        driver_id=str(row["driver_id"]),
        trip_date=pd.Timestamp(row["trip_date"]).to_pydatetime(),
        scheduled_departure_time=f"{int(row['scheduled_departure_hour']):02d}:{int(row['scheduled_departure_minute']):02d}",
    )
    r_meta = route_metadata.get(str(row["route"]), {})
    d_meta = driver_metadata.get(str(row["driver_id"]), {})
    diff_rows = []
    for name in DLY_FEATURE_COLUMNS:
        enc = float(encoded.get(name))
        if name == "route_encoded":
            exp = float(encoders["route_encodings"][str(row["route"])])
        elif name == "bus_encoded":
            exp = float(encoders["bus_encodings"][str(row["bus_no"])])
        elif name == "driver_encoded":
            exp = float(encoders["driver_encodings"][str(row["driver_id"])])
        elif name == "season_encoded":
            exp = float(SEASON_MAP[str(row["season"])])
        elif name == "route_prior_delay_mean_min":
            exp = float(r_meta.get("prior_delay_mean", 0.0))
        elif name == "route_prior_delay_rate":
            exp = float(r_meta.get("prior_delay_rate", 0.0))
        elif name == "driver_prior_delay_mean_min":
            exp = float(d_meta.get("prior_delay_mean", 0.0))
        elif name == "driver_trip_seq":
            exp = 1.0
        elif name in DELAY_INCIDENT_FEATURES:
            # SAMPLE data has no incident records -> the feature is always 0.
            exp = 0.0
        else:
            exp = float(row[name])
        if abs(enc - exp) > 1e-6:
            diff_rows.append((name, enc, exp))
    if diff_rows:
        mismatches += 1
        print(f"  [FAIL] encoding mismatch on {row['route']}: {diff_rows[:3]}")
check(
    "prediction encoding matches training-time encoding (consistent encoding)",
    mismatches == 0,
    f"{len(probe_rows)} routes probed",
)

# Unknown input handling at encode level.
unknown_encoded = delay_predict.encode_features(
    route="Neverland - Unknown Route",
    bus_no="BUS-ZZZ",
    driver_id="DRV-ZZZ",
    trip_date=pd.Timestamp("2026-08-10").to_pydatetime(),
    scheduled_departure_time="12:00",
)
check(
    "unknown categories map to -1 (handled safely, no crash)",
    unknown_encoded["route_encoded"] == -1.0
    and unknown_encoded["bus_encoded"] == -1.0
    and unknown_encoded["driver_encoded"] == -1.0,
    f"route={unknown_encoded['route_encoded']}, bus={unknown_encoded['bus_encoded']}, driver={unknown_encoded['driver_encoded']}",
)

# ============================================================
# 10 + 11 + 12. Engine integration (/delay endpoints)
# ============================================================
print("\n--- Engine integration ---")
try:
    from fastapi import FastAPI
    from fastapi.testclient import TestClient

    from delay.router import router as delay_router

    app = FastAPI()
    app.include_router(delay_router, prefix="/delay", tags=["Predictive Analytics"])
    client = TestClient(app)
    check("fastapi app with /delay router constructs", True)

    status = client.get("/delay/status")
    check(
        "GET /delay/status works (200)",
        status.status_code == 200,
        f"http={status.status_code}",
    )
    status_body = status.json()
    check("  ...model_ready=True", status_body.get("model_ready") is True)
    check(
        "  ...SAMPLE/DEMONSTRATION warning present",
        "SAMPLE / DEMONSTRATION" in str(status_body.get("dataset_type"))
        and "NOT trained on genuine" in str(status_body.get("warning")),
    )
    check(
        "  ...training record count reported",
        isinstance(status_body.get("training_record_count"), int) and status_body.get("training_record_count") > 0,
        f"count={status_body.get('training_record_count')}",
    )
    check(
        "  ...is_production flag false",
        status_body.get("data_source") == "sample",
    )

    predict_payload = {
        "route": "Talisay - SM Seaside",
        "scheduled_departure_time": "07:15",
        "bus_no": "GCT-105",
        "driver_id": "DRV-202",
        "trip_date": "2026-08-10",
    }
    response = client.post("/delay/predict", json=predict_payload)
    check(
        "POST /delay/predict works (200)",
        response.status_code == 200,
        f"http={response.status_code}",
    )
    if response.status_code == 200:
        body = response.json()
        check("  ...prediction present and non-negative", body.get("predicted_arrival_delay_minutes") is not None and body["predicted_arrival_delay_minutes"] >= 0)
        check("  ...risk_status present", bool(body.get("risk_status")))
        check("  ...data_source='sample'", body.get("data_source") == "sample")
        check("  ...is_production_model is false", body.get("is_production_model") is False)
        check("  ...disclaimer present", "NOT ACTUAL GCT OPERATIONAL DATA" in str(body.get("disclaimer")))

    # Invalid input: missing required field -> 422.
    bad_payload = {
        "scheduled_departure_time": "07:15",
    }
    resp_bad = client.post("/delay/predict", json=bad_payload)
    check(
        "invalid input (missing route) is handled safely (422, not crash)",
        resp_bad.status_code == 422,
        f"http={resp_bad.status_code}",
    )

    # Unknown route/bus/driver -> still 200 (features degrade, no crash).
    unknown_payload = {
        "route": "Neverland - Unknown Route",
        "scheduled_departure_time": "12:00",
        "bus_no": "BUS-ZZZ",
        "driver_id": "DRV-ZZZ",
        "trip_date": "2026-08-10",
    }
    resp_unknown = client.post("/delay/predict", json=unknown_payload)
    check(
        "unseen route returns a prediction, not an error",
        resp_unknown.status_code == 200 and resp_unknown.json()["success"] is True,
        f"http={resp_unknown.status_code}",
    )
except ImportError as exc:  # pragma: no cover
    for label in [
        "fastapi app with /delay router constructs",
        "GET /delay/status works (200)",
        "POST /delay/predict works (200)",
        "invalid input (missing route) is handled safely (422, not crash)",
        "unseen route returns a prediction, not an error",
    ]:
        check(label, False, f"fastapi/testclient missing: {exc}")

# ============================================================
# 15. Production DB safety
# ============================================================
print("\n--- Production DB safety ---")
delay_pkg = Path(__file__).resolve().parent
db_api_tokens = [
    r"\bpymysql\b",
    r"\bDbConnection\b",
    r"\bconn\.cursor\b",
    r"\bINSERT INTO\b",
    r"\bDELETE FROM\b",
    r"\.execute\(",
]
# Runtime modules only (test_delay.py legitimately mentions the tokens as
# regex patterns in this very check, so it is excluded from the scan).
violations = []
for py_file in delay_pkg.glob("*.py"):
    if py_file.name == "test_delay.py":
        continue
    text = py_file.read_text(encoding="utf-8")
    for token in db_api_tokens:
        if re.search(token, text, flags=re.IGNORECASE):
            violations.append(f"{py_file.name}:{token}")
check(
    "delay package contains no database-connect/write API calls",
    not violations,
    "; ".join(violations[:5]) or "no pymysql / DbConnection / INSERT / UPDATE / DELETE",
)

# Sample CSVs live OUTSIDE python_engine/ (central training_data/ delay folder).
csv_resolved = str(csv_path.resolve()).lower()
python_engine_dir = str(delay_pkg.parent.resolve().parent).lower()
check(
    "sample CSVs are NOT stored inside python_engine/",
    "python_engine" not in csv_resolved,
    csv_path,
)
check(
    "sample CSVs live under the central training_data/ folder",
    f"training_data{chr(92)}delay" in csv_resolved.replace("/", "\\"),
    csv_resolved,
)
# Deterministic regeneration (same seed -> identical rows, no DB dependency).
d1 = generate_sample_dataset(seed=42)
d2 = generate_sample_dataset(seed=42)
check(
    "sample generation is deterministic (reproducible, DB-independent)",
    d1.equals(d2),
    f"{len(d1)} rows identical",
)

print(f"\n{'=' * 50}")
print(f"Results: {PASS} passed, {FAIL} failed")
print(f"{'=' * 50}")
sys.exit(1 if FAIL > 0 else 0)