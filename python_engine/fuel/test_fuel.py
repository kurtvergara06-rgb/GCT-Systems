"""End-to-end tests for the fuel consumption prediction model.

Runs against the REAL available data:
    1. Model trains successfully (RandomForestRegressor).
    2. Model saves and reloads successfully.
    3. A new valid trip produces a prediction.
    4. Prediction is numeric and within the observed training range.
    5. MAE / RMSE / R2 are generated.
    6. No target leakage exists (the label is never an input feature).
    7. Feature encodings used at prediction match the training encodings exact.
"""

import logging
import sys
from pathlib import Path

logging.basicConfig(level=logging.WARNING)

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import joblib  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402

from fuel.config import model_paths, training_data_paths  # noqa: E402
from fuel.model import save_fuel_model, save_state, train_fuel_model  # noqa: E402
from fuel.training_data import (  # noqa: E402
    FUEL_FEATURE_COLUMNS,
    FUEL_TARGET,
    build_bus_encodings,
    build_route_metadata,
)
from fuel import predict as fuel_predict  # noqa: E402

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


csv_path = training_data_paths()["csv"]
paths = model_paths()

print("\n=== Fuel model tests ===")

if not csv_path.exists():
    print("  [FAIL] training CSV not found:", csv_path)
    print("  Run `python -m fuel.prepare_training_data` first.")
    sys.exit(1)

df = pd.read_csv(csv_path)
check("training CSV exists", csv_path.exists(), str(csv_path))
check(
    "training CSV has all feature columns + target",
    all(c in df.columns for c in FUEL_FEATURE_COLUMNS + [FUEL_TARGET]),
    f"{len(df)} rows",
)
check("enough real samples (>= 50)", len(df) >= 50, f"{len(df)} rows")


# ============================================================
# 6. Target-leakage checks
# ============================================================
print("\n--- Target leakage ---")
LABEL_DERIVED_OR_FORBIDDEN_FIELDS = [
    "km_per_liter",   # distance / fuel -> CONTAINS THE LABEL.
    "fuel_liters",    # the label itself (belt and braces).
    "total_minutes",  # duplicate of duration_minutes.
    "mileage_km",     # duplicate of fuel_reports.distance_km.
    "severity",       # derived from idling/motion thresholds.
    "report_date",    # temporal identity.
]
check(
    "label never appears in feature columns",
    FUEL_TARGET not in FUEL_FEATURE_COLUMNS,
    f"features={FUEL_FEATURE_COLUMNS}",
)
check(
    "no label-derived or forbidden field is used as a feature",
    all(field not in FUEL_FEATURE_COLUMNS for field in LABEL_DERIVED_OR_FORBIDDEN_FIELDS),
    "km_per_liter/total_minutes/mileage_km/severity/report_date excluded",
)
check(
    "target has real variance (trainable signal)",
    float(df[FUEL_TARGET].std()) > 0.2,
    f"std={df[FUEL_TARGET].std():.2f}",
)


# ============================================================
# 1 + 5. Train and metrics
# ============================================================
print("\n--- Train ---")
result = train_fuel_model(df)
check("model trains successfully", result.trained, result.message)
check("MAE generated", "mae" in result.metrics and isinstance(result.metrics["mae"], float))
check(
    "MAE is a finite positive value",
    result.trained and bool(np.isfinite(result.metrics["mae"])) and result.metrics["mae"] > 0,
    f"MAE={result.metrics.get('mae'):.2f} liters",
)
check(
    "RMSE generated",
    "rmse" in result.metrics and isinstance(result.metrics["rmse"], float),
)
check(
    "R2 generated",
    "r2" in result.metrics and -1.0 <= result.metrics["r2"] <= 1.0,
    f"R2={result.metrics.get('r2'):.4f}",
)
check(
    "train/test split produced held-out rows",
    result.n_train > 0 and result.n_test > 0,
    f"train={result.n_train}, test={result.n_test}",
)


# ============================================================
# 2. Save + reload
# ============================================================
print("\n--- Save / reload ---")
save_fuel_model(
    result,
    paths,
    route_metadata=build_route_metadata(df),
    bus_encodings=build_bus_encodings(df),
)
save_state(result, paths)
check("model file saved", paths["model"].exists(), str(paths["model"]))
check("features file saved", paths["features"].exists())
check("report file saved", paths["report"].exists())
check("state file saved", paths["state"].exists())

reloaded = joblib.load(paths["model"])
check("model reloads from disk", reloaded is not None)
check(
    "reloaded model is a RandomForestRegressor",
    reloaded is not None and reloaded.__class__.__name__ == "RandomForestRegressor",
)

fuel_predict.reset_cache()
readiness = fuel_predict.fuel_readiness()
check("prediction service reports model ready", readiness.ml_ready, readiness.reason)
check(
    "state sample count matches training",
    readiness.sample_count == result.n_samples,
    f"state={readiness.sample_count}, trained={result.n_samples}",
)


# ============================================================
# 3 + 4. Prediction on a new trip
# ============================================================
print("\n--- Prediction ---")
target_min = float(result.target_range["min"])
target_max = float(result.target_range["max"])

samples = df.drop_duplicates(subset=["route"]).head(3)
for _, row in samples.iterrows():
    prediction = fuel_predict.predict_fuel_consumption(
        route=str(row["route"]),
        trip_started_at=pd.Timestamp(row["beginning_at"]).to_pydatetime(),
        bus_no=str(row["bus_no"]),
        distance_km=float(row["distance_km"]),
        trip_duration_minutes=float(row["trip_duration_minutes"]),
        in_motion_minutes=float(row["in_motion_minutes"]),
        idling_minutes=float(row["idling_minutes"]),
        engine_on_hours=float(row["engine_on_hours"]),
    )
    label = f"predict {row['route']}"
    if prediction is None:
        check(label, False, "prediction returned None")
        continue
    value = prediction.predicted_fuel_liters
    check(
        label,
        isinstance(value, (int, float)) and np.isfinite(value),
        f"value={value}",
    )
    check(
        "  ...within observed training range",
        target_min <= value <= target_max,
        f"{value} in [{target_min}, {target_max}]",
    )

# Determinism: same inputs -> same output.
p1 = fuel_predict.predict_fuel_consumption(
    route="SM City Cebu - Mactan Airport",
    trip_started_at=pd.Timestamp("2026-09-10 13:10:00").to_pydatetime(),
    bus_no="GCT-101",
    distance_km=12.5,
    trip_duration_minutes=46,
    in_motion_minutes=43,
    idling_minutes=3,
    engine_on_hours=0.8,
)
p2 = fuel_predict.predict_fuel_consumption(
    route="SM City Cebu - Mactan Airport",
    trip_started_at=pd.Timestamp("2026-09-10 13:10:00").to_pydatetime(),
    bus_no="GCT-101",
    distance_km=12.5,
    trip_duration_minutes=46,
    in_motion_minutes=43,
    idling_minutes=3,
    engine_on_hours=0.8,
)
check(
    "prediction is deterministic (same inputs -> same output)",
    p1 is not None
    and p2 is not None
    and p1.predicted_fuel_liters == p2.predicted_fuel_liters,
)

# Partial inputs -> still a prediction (missing values encoded -1), no crash.
partial = fuel_predict.predict_fuel_consumption(
    route="SM City Cebu - Mactan Airport",
    trip_started_at=pd.Timestamp("2026-09-10 13:10:00").to_pydatetime(),
)
check(
    "partial inputs still produce a numeric prediction (no invention)",
    partial is not None
    and isinstance(partial.predicted_fuel_liters, (int, float))
    and np.isfinite(partial.predicted_fuel_liters),
    f"value={partial.predicted_fuel_liters if partial else None}",
)


# ============================================================
# Encoding alignment: prediction encoding == training encoding
# ============================================================
print("\n--- Encoding alignment ---")
fuel_predict.reset_cache()
for _, row in samples.iterrows():
    encoded = fuel_predict._encode_features(
        route=str(row["route"]),
        trip_started_at=pd.Timestamp(row["beginning_at"]).to_pydatetime(),
        bus_no=str(row["bus_no"]),
        distance_km=float(row["distance_km"]),
        trip_duration_minutes=float(row["trip_duration_minutes"]),
        in_motion_minutes=float(row["in_motion_minutes"]),
        idling_minutes=float(row["idling_minutes"]),
        engine_on_hours=float(row["engine_on_hours"]),
    )
    diffs = [
        (name, encoded.get(name), row.get(name))
        for name in FUEL_FEATURE_COLUMNS
        if encoded.get(name) is not None
        and row.get(name) is not None
        and abs(float(encoded.get(name)) - float(row[name])) > 1e-6
    ]
    check(
        f"prediction encoding matches training row ({row['route']})",
        not diffs,
        "; ".join(f"{name}: enc={a} csv={b}" for name, a, b in diffs[:3]),
    )


# ============================================================
# Engine integration: /fuel endpoints end-to-end via the router
# ============================================================
print("\n--- Engine integration ---")
try:
    from fastapi import FastAPI
    from fastapi.testclient import TestClient

    from fuel.router import router as fuel_router

    app = FastAPI()
    app.include_router(fuel_router, prefix="/fuel", tags=["Predictive Analytics"])
    client = TestClient(app)
    check("fastapi app with /fuel router constructs", True)

    status = client.get("/fuel/status")
    check(
        "GET /fuel/status returns model_ready=True",
        status.status_code == 200 and status.json()["model_ready"] is True,
        f"http={status.status_code}, body={status.json()}",
    )

    predict_payload = {
        "route": "SM City Cebu - Mactan Airport",
        "trip_started_at": "2026-09-10T13:10:00",
        "bus_no": "GCT-101",
        "distance_km": 12.5,
        "trip_duration_minutes": 46,
        "in_motion_minutes": 43,
        "idling_minutes": 3,
        "engine_on_hours": 0.8,
    }
    response = client.post("/fuel/predict", json=predict_payload)
    ok = response.status_code == 200 and response.json()["success"] is True
    check(
        "GET /fuel/predict returns fuel for a known route",
        ok,
        f"http={response.status_code}",
    )
    if ok:
        volume = response.json()["predicted_fuel_liters"]
        check(
            "  ...in-range fuel volume in response",
            isinstance(volume, (int, float))
            and target_min <= float(volume) <= target_max,
            f"predicted={volume} liters",
        )

    # Unknown route -> still predicts (encoded as -1) without an error.
    unknown = {**predict_payload, "route": "Neverland - Unknown"}
    resp_unknown = client.post("/fuel/predict", json=unknown)
    check(
        "unseen route still returns a prediction (not an error)",
        resp_unknown.status_code == 200 and resp_unknown.json()["success"] is True,
        f"http={resp_unknown.status_code}",
    )
except ImportError as exc:
    # FastAPI is an optional runtime dependency for the test harness.
    check("fastapi app with /fuel router constructs", False, f"fastapi missing: {exc}")
    check("GET /fuel/status returns model_ready=True", False, "skipped: no fastapi")
    check("GET /fuel/predict returns fuel for a known route", False, "skipped: no fastapi")
    check("unseen route still returns a prediction (not an error)", False, "skipped: no fastapi")

# main.py itself cannot be imported in this checkout because it references
# NLP modules (severity_predictor, ner_extractor, anomaly_detector, ...) that are
# NOT present in the repo. That breakage pre-dates /eta and /fuel and is out of scope.
print("\n  [NOTE] python_engine/main.py import blocked by pre-existing missing")
print("         NLP modules (NLP/severity_predictor.py etc.); unrelated to /fuel.")
print("         /fuel is wired additively beside the analytics + operation_ai routers.")

print(f"\n{'=' * 50}")
print(f"Results: {PASS} passed, {FAIL} failed")
print(f"{'=' * 50}")
sys.exit(1 if FAIL > 0 else 0)