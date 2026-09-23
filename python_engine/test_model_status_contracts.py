"""Contract checks for the model-status data consumed by Laravel Analytics."""

from fastapi import FastAPI
from fastapi.testclient import TestClient

from eta.router import router as eta_router
from operation_ai.router import router as operation_ai_router


def check(name: str, condition: bool) -> None:
    if not condition:
        raise AssertionError(name)
    print(f"PASS: {name}")


def main() -> None:
    app = FastAPI()
    app.include_router(eta_router, prefix="/eta")
    app.include_router(
        operation_ai_router,
        prefix="/operation/auto-scheduling/ai",
    )
    client = TestClient(app)

    eta_response = client.get("/eta/status")
    check("ETA status reachable", eta_response.status_code == 200)
    eta = eta_response.json()
    check("ETA source is genuine", eta["data_source"] == "genuine")
    check("ETA dataset is genuine GPS", eta["dataset_type"] == "GENUINE GCT GPS RECORDS")
    check(
        "ETA production flag follows readiness",
        eta["is_production_model"] is eta["model_ready"],
    )

    scheduling_response = client.get(
        "/operation/auto-scheduling/ai/training/status"
    )
    check("Scheduling status reachable", scheduling_response.status_code == 200)
    scheduling = scheduling_response.json()
    check("Scheduling source is genuine", scheduling["data_source"] == "genuine")
    check("Scheduling bus source is genuine", scheduling["bus_data_source"] == "genuine")
    check("Scheduling driver source is genuine", scheduling["driver_data_source"] == "genuine")
    check(
        "Scheduling bus production flag is honest",
        scheduling["bus_is_production_model"]
        is (scheduling["bus_model_ready"] and scheduling["bus_source"] == "ml"),
    )
    check(
        "Scheduling driver production flag is honest",
        scheduling["driver_is_production_model"]
        is (scheduling["driver_model_ready"] and scheduling["driver_source"] == "ml"),
    )


if __name__ == "__main__":
    main()
