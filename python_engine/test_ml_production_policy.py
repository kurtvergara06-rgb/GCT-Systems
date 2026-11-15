"""Regression checks for the production genuine-data ML policy."""

from __future__ import annotations

import os

from fastapi import FastAPI
from fastapi.testclient import TestClient

from ml_runtime_policy import evaluate_model_source, runtime_mode, synthetic_models_allowed


def check(label: str, condition: bool) -> None:
    if not condition:
        raise AssertionError(label)
    print(f"PASS: {label}")


_original = {
    "ML_RUNTIME_MODE": os.environ.get("ML_RUNTIME_MODE"),
    "RENDER": os.environ.get("RENDER"),
    "APP_ENV": os.environ.get("APP_ENV"),
    "DELAY_DATA_SOURCE": os.environ.get("DELAY_DATA_SOURCE"),
    "INVENTORY_DATA_SOURCE": os.environ.get("INVENTORY_DATA_SOURCE"),
}

try:
    # Development/demo may explicitly use generated data.
    os.environ["ML_RUNTIME_MODE"] = "development"
    os.environ.pop("RENDER", None)
    os.environ["DELAY_DATA_SOURCE"] = "sample"
    os.environ["INVENTORY_DATA_SOURCE"] = "sample"

    check("development runtime detected", runtime_mode() == "development")
    check("synthetic models allowed in development", synthetic_models_allowed())
    check(
        "development sample source allowed",
        evaluate_model_source("test", "sample").allowed,
    )

    # Production blocks generated/synthetic models regardless of an explicit
    # per-model sample setting.
    os.environ["ML_RUNTIME_MODE"] = "production"
    check("production runtime detected", runtime_mode() == "production")
    check("synthetic models blocked in production", not synthetic_models_allowed())

    sample_policy = evaluate_model_source("test", "sample")
    check("production sample source rejected", not sample_policy.allowed)
    check("production rejection says MODEL NOT READY", sample_policy.model_ready_message == "MODEL NOT READY")
    check("production genuine source allowed", evaluate_model_source("test", "genuine").allowed)

    # Delay Model #3: tracked sample artifact may exist, but production API must
    # report NOT READY and reject prediction instead of serving it.
    from delay.predict import reset_cache as reset_delay_cache
    from delay.router import router as delay_router

    reset_delay_cache()
    delay_app = FastAPI()
    delay_app.include_router(delay_router, prefix="/delay")
    delay_client = TestClient(delay_app)

    delay_status = delay_client.get("/delay/status")
    check("delay status reachable", delay_status.status_code == 200)
    delay_body = delay_status.json()
    check("delay sample blocked in production", delay_body["model_ready"] is False)
    check("delay status MODEL NOT READY", delay_body["message"] == "MODEL NOT READY")
    check("delay synthetic_allowed false", delay_body["synthetic_allowed"] is False)

    delay_predict = delay_client.post(
        "/delay/predict",
        json={
            "route": "Talisay - SM Seaside",
            "scheduled_departure_time": "08:00",
            "bus_no": "GCT-101",
            "driver_id": "1",
            "trip_date": "2026-09-23",
        },
    )
    check("delay production sample prediction rejected", delay_predict.status_code == 503)

    # Inventory Model #4: same hard rule.
    from inventory.predict import reset_cache as reset_inventory_cache
    from inventory.router import router as inventory_router

    reset_inventory_cache()
    inventory_app = FastAPI()
    inventory_app.include_router(inventory_router, prefix="/inventory")
    inventory_client = TestClient(inventory_app)

    inventory_status = inventory_client.get("/inventory/status")
    check("inventory status reachable", inventory_status.status_code == 200)
    inventory_body = inventory_status.json()
    check("inventory sample blocked in production", inventory_body["model_ready"] is False)
    check("inventory status MODEL NOT READY", inventory_body["message"] == "MODEL NOT READY")
    check("inventory synthetic_allowed false", inventory_body["synthetic_allowed"] is False)

    # Legacy generated-data scheduling NN is development-only. In production it
    # must never load or contribute a synthetic score.
    from operation_ai import ml_scorer

    ml_scorer._model = None
    ml_scorer._model_loaded = False
    check("legacy synthetic scheduling model blocked", ml_scorer._load_model() is None)

    print("Production genuine-data ML policy PASS")
finally:
    for key, value in _original.items():
        if value is None:
            os.environ.pop(key, None)
        else:
            os.environ[key] = value
