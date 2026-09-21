"""Standalone FastAPI app for the delay-prediction service (Model #3).

DEV HARNESS ONLY - runs the delay router in isolation so the Model #3 service
can be started, tested, and demoed without the rest of the engine:

    python -m delay.main            # from the python_engine directory
    uvicorn delay.main:app --reload

The canonical GCT Python Engine (python_engine/main.py) mounts the same router
under the /delay prefix. Training-source awareness: with
``DELAY_DATA_SOURCE=genuine`` the harness labels itself as the genuine
(production) service; otherwise it is a SAMPLE / DEMONSTRATION prototype.
"""

import logging

from fastapi import FastAPI

from .config import is_genuine
from .predict import delay_readiness
from .router import router as delay_router

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")

genuine = is_genuine()

app = FastAPI(
    title=(
        "GCT Delay Prediction Service (Model #3 - GENUINE OPERATIONAL DATA)"
        if genuine
        else "GCT Delay Prediction Service (Model #3 - SAMPLE / DEMONSTRATION)"
    ),
    description=(
        "Production-quality delay model trained on genuine matched DDR history."
        if genuine
        else "Development prototype predicting expected arrival delay (minutes) "
             "from pre-trip features. Trained on a SEPARATE SAMPLE dataset - NOT "
             "genuine GCT operational data."
    ),
    version="genuine-1.0.0" if genuine else "sample-0.1.0",
    lifespan=None,
)

app.include_router(
    delay_router,
    prefix="/delay",
    tags=["Predictive Analytics"],
)


@app.get("/")
def home() -> dict:
    label = "GENUINE OPERATIONAL DATA" if genuine \
        else "SAMPLE / DEMONSTRATION"
    return {
        "message": f"GCT Delay Prediction Service ({label}) is running.",
    }


@app.get("/health")
def health() -> dict:
    readiness = delay_readiness()
    return {
        "status": "online",
        "service": "GCT Delay Prediction (Model #3)",
        "model_ready": readiness.ml_ready,
        "data_source": readiness.data_source,
        "is_production_model": readiness.is_production_model,
    }