"""End-to-end tests for the ETA / trip-duration prediction model.

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

from eta.config import model_paths, training_data_paths  # noqa: E402
from eta.model import save_eta_model, save_state, train_eta_model  # noqa: E402
from eta.training_data import (  # noqa: E402
    ETA_FEATURE_COLUMNS,
    ETA_TARGET,
    SHIFT_MAP,
    build_bus_encodings,
    build_route_metadata,
)
from eta import predict as eta_predict  # noqa: E402

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

print("\n=== ETA model tests ===")

if not csv_path.exists():
    print("  [FAIL] training CSV not found:", csv_path)
    print("  Run `python -m eta.prepare_training_data` first.")
    sys.exit(1)

df = pd.read_csv(csv_path)
check("training CSV exists", csv_path.exists(), str(csv_path))
check(
    "training CSV has all feature columns + target",
    all(c in df.columns for c in ETA_FEATURE_COLUMNS + [ETA_TARGET]),
    f"{len(df)} rows",
)
check("enough real samples (>= 50)", len(df) >= 50, f"{len(df)} rows")


# ============================================================
# 6. Target-leakage checks
# ============================================================
print("\n--- Target leakage ---")
POST_TRIP_FIELDS = [
    "duration_minutes",
    "total_minutes",
    "in_motion_minutes",
    "idling_minutes",
    "mileage_km",
    "engine_hours",
    "severity",
    ETA_TARGET,
]
check(
    "label never appears in feature columns",
    ETA_TARGET not in ETA_FEATURE_COLUMNS,
    f"features={ETA_FEATURE_COLUMNS}",
)
check(
    "no post-trip-only field is used as a feature",
    all(field not in ETA_FEATURE_COLUMNS for field in POST_TRIP_FIELDS),
    "in_motion/idling/mileage/engine_hours/severity excluded",
)
check(
    "target has real variance (trainable signal)",
    float(df[ETA_TARGET].std()) > 1.0,
    f"std={df[ETA_TARGET].std():.2f}",
)


# ============================================================
# 1 + 5. Train and metrics
# ============================================================
print("\n--- Train ---")
result = train_eta_model(df)
check("model trains successfully", result.trained, result.message)
check("MAE generated", "mae" in result.metrics and isinstance(result.metrics["mae"], float))
check(
    "MAE is a finite positive value",
    result.trained and bool(np.isfinite(result.metrics["mae"])) and result.metrics["mae"] > 0,
    f"MAE={result.metrics.get('mae'):.2f} min",
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
save_eta_model(
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

eta_predict.reset_cache()
readiness = eta_predict.eta_readiness()
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
    prediction = eta_predict.predict_trip_duration(
        route=str(row["route"]),
        departure_at=pd.Timestamp(row["beginning_at"]).to_pydatetime(),
        shift=None,
        bus_no=str(row["bus_no"]),
    )
    label = f"predict {row['route']}"
    if prediction is None:
        check(label, False, "prediction returned None")
        continue
    value = prediction.predicted_duration_minutes
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
p1 = eta_predict.predict_trip_duration(route="Talisay - SM Seaside", departure_at=pd.Timestamp("2026-09-10 13:10:00").to_pydatetime(), shift="Afternoon")
p2 = eta_predict.predict_trip_duration(route="Talisay - SM Seaside", departure_at=pd.Timestamp("2026-09-10 13:10:00").to_pydatetime(), shift="Afternoon")
check(
    "prediction is deterministic (same inputs -> same output)",
    p1 is not None and p2 is not None and p1.predicted_duration_minutes == p2.predicted_duration_minutes,
)

# ETA = departure + predicted duration.
if p1 is not None:
    expected_arrival = p1.departure_at
    from datetime import timedelta

    expected_arrival = expected_arrival + timedelta(minutes=p1.predicted_duration_minutes)
    check(
        "estimated arrival = departure + predicted duration",
        p1.estimated_arrival_at == expected_arrival,
        f"arrival={p1.estimated_arrival_at}",
    )


# ============================================================
# Encoding alignment: prediction encoding == training encoding
# ============================================================
print("\n--- Encoding alignment ---")
shift_by_idx = {v: k for k, v in SHIFT_MAP.items()}
eta_predict.reset_cache()
for _, row in samples.iterrows():
    shift_raw = shift_by_idx.get(int(row["shift_encoded"]), None)
    encoded = eta_predict._encode_features(
        route=str(row["route"]),
        departure_at=pd.Timestamp(row["beginning_at"]).to_pydatetime(),
        shift=shift_raw,
        bus_no=str(row["bus_no"]),
        distance_km=None,
        route_estimated_time_minutes=None,
    )
    diffs = [
        (name, encoded.get(name), row.get(name))
        for name in ETA_FEATURE_COLUMNS
        if encoded.get(name) is not None and row.get(name) is not None and abs(float(encoded.get(name)) - float(row[name])) > 1e-9
    ]
    check(
        f"prediction encoding matches training row ({row['route']})",
        not diffs,
        "; ".join(f"{name}: enc={a} csv={b}" for name, a, b in diffs[:3]),
    )


# ============================================================
# Engine integration: /eta endpoints end-to-end via the router
# ============================================================
print("\n--- Engine integration ---")
try:
    from fastapi import FastAPI
    from fastapi.testclient import TestClient

    from eta.router import router as eta_router

    app = FastAPI()
    app.include_router(eta_router, prefix="/eta", tags=["Predictive Analytics"])
    client = TestClient(app)
    check("fastapi app with /eta router constructs", True)

    status = client.get("/eta/status")
    check(
        "GET /eta/status returns model_ready=True",
        status.status_code == 200 and status.json()["model_ready"] is True,
        f"http={status.status_code}, body={status.json()}",
    )

    predict_payload = {
        "route": "SM City Cebu - Mactan Airport",
        "departure_at": "2026-09-10T13:10:00",
        "shift": "Afternoon",
        "bus_no": None,
        "distance_km": 12.5,
        "route_estimated_time_minutes": 45,
    }
    response = client.post("/eta/predict", json=predict_payload)
    ok = response.status_code == 200 and response.json()["success"] is True
    check(
        "GET /eta/predict returns a duration for a known route",
        ok,
        f"http={response.status_code}",
    )
    if ok:
        duration = response.json()["predicted_duration_minutes"]
        check(
            "  ...in-range duration in response",
            isinstance(duration, (int, float))
            and target_min <= float(duration) <= target_max,
            f"predicted={duration} min",
        )

    # Unknown route -> still predicts (encoded as -1) without an error.
    unknown = {**predict_payload, "route": "Neverland - Unknown"}
    resp_unknown = client.post("/eta/predict", json=unknown)
    check(
        "unseen route still returns a prediction (not an error)",
        resp_unknown.status_code == 200 and resp_unknown.json()["success"] is True,
        f"http={resp_unknown.status_code}",
    )
except ImportError as exc:
    # FastAPI is an optional runtime dependency for the test harness.
    check("fastapi app with /eta router constructs", False, f"fastapi missing: {exc}")
    check("GET /eta/status returns model_ready=True", False, "skipped: no fastapi")
    check("GET /eta/predict returns a duration for a known route", False, "skipped: no fastapi")
    check("unseen route still returns a prediction (not an error)", False, "skipped: no fastapi")

# main.py itself cannot be imported in this checkout because it references
# NLP modules (severity_predictor, ner_extractor, anomaly_detector, ...) that are
# NOT present in the repo. That breakage pre-dates /eta and is out of scope.
print("\n  [NOTE] python_engine/main.py import blocked by pre-existing missing")
print("         NLP modules (NLP/severity_predictor.py etc.); unrelated to /eta.")
print("         /eta is wired additively beside the analytics + operation_ai routers.")

print(f"\n{'=' * 50}")
print(f"Results: {PASS} passed, {FAIL} failed")
print(f"{'=' * 50}")
sys.exit(1 if FAIL > 0 else 0)