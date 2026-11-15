"""Regression tests for ETA Model #1 production safety.

Run from python_engine:
    python -m eta.test_production_readiness
"""

from __future__ import annotations

import os

import pandas as pd
from fastapi import FastAPI
from fastapi.testclient import TestClient

from eta import predict as eta_predict
from eta.router import router as eta_router
from eta.training_data import ETA_FEATURE_COLUMNS, fetch_trip_outcomes


def check(label: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{label}: {detail}")
    print(f"PASS: {label}" + (f" ({detail})" if detail else ""))


class FakeDb:
    def __init__(self) -> None:
        self.sql = ""

    def query_df(self, sql, params=None):
        self.sql = sql
        return pd.DataFrame()


def client() -> TestClient:
    app = FastAPI()
    app.include_router(eta_router, prefix="/eta")
    return TestClient(app)


def main() -> None:
    original_mode = os.environ.get("ML_RUNTIME_MODE")
    original_render = os.environ.get("RENDER")
    try:
        # Training extraction must fail closed on provenance.
        fake = FakeDb()
        fetch_trip_outcomes(fake)
        normalized_sql = " ".join(fake.sql.lower().split())
        check("ETA SQL joins batch provenance", "inner join batch_uploads bu" in normalized_sql)
        check(
            "ETA SQL only admits genuine batches",
            "bu.data_origin" in normalized_sql and "= 'genuine'" in normalized_sql,
        )

        # Production blocks the tracked development/demo artifact.
        os.environ["ML_RUNTIME_MODE"] = "production"
        os.environ.pop("RENDER", None)
        eta_predict.reset_cache()
        production = client()
        status = production.get("/eta/status")
        body = status.json()
        check("production ETA status reachable", status.status_code == 200)
        check("tracked ETA artifact is explicitly synthetic", body["data_source"] == "synthetic")
        check("synthetic ETA blocked in production", body["model_ready"] is False)
        check("synthetic ETA production flag false", body["is_production_model"] is False)
        rejected = production.post(
            "/eta/predict",
            json={
                "route": "Talisay - SM Seaside",
                "departure_at": "2026-09-29T08:00:00",
                "shift": "Morning",
                "bus_no": "GCT-101",
            },
        )
        check("production prediction rejected", rejected.status_code == 503)

        # Development may exercise the clearly labeled demo artifact.
        os.environ["ML_RUNTIME_MODE"] = "development"
        eta_predict.reset_cache()
        development = client()
        dev_status = development.get("/eta/status").json()
        check("demo artifact usable only in development", dev_status["model_ready"] is True)
        check("development artifact not production", dev_status["is_production_model"] is False)
        check("development dataset is labeled demo", "DEMO" in dev_status["dataset_type"])

        known = development.post(
            "/eta/predict",
            json={
                "route": "Talisay - SM Seaside",
                "departure_at": "2026-09-29T08:00:00",
                "shift": "Morning",
                "bus_no": "GCT-101",
            },
        )
        check("known demo route can be exercised in development", known.status_code == 200)
        if known.status_code == 200:
            feature_inputs = known.json()["feature_inputs"]
            check(
                "inference uses exact persisted feature order/schema",
                list(feature_inputs.keys()) == ETA_FEATURE_COLUMNS,
                str(list(feature_inputs.keys())),
            )

        unseen = development.post(
            "/eta/predict",
            json={
                "route": "Unseen Route - No Training History",
                "departure_at": "2026-09-29T08:00:00",
                "shift": "Morning",
            },
        )
        check("unseen route rejected instead of extrapolated", unseen.status_code == 422)

        print("ETA production readiness PASS")
    finally:
        if original_mode is None:
            os.environ.pop("ML_RUNTIME_MODE", None)
        else:
            os.environ["ML_RUNTIME_MODE"] = original_mode
        if original_render is None:
            os.environ.pop("RENDER", None)
        else:
            os.environ["RENDER"] = original_render
        eta_predict.reset_cache()


if __name__ == "__main__":
    main()
