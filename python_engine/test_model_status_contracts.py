"""Contract checks for model-status data consumed by Laravel Analytics."""

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
    app.include_router(operation_ai_router, prefix="/operation/auto-scheduling/ai")
    client = TestClient(app)

    eta_response = client.get("/eta/status")
    check("ETA status reachable", eta_response.status_code == 200)
    eta = eta_response.json()
    check("ETA reports explicit provenance", eta["data_source"] in {"genuine", "synthetic", "unknown"})
    check("ETA reports dataset type", bool(eta["dataset_type"]))
    check(
        "ETA production flag requires genuine source",
        not eta["is_production_model"] or eta["data_source"] == "genuine",
    )
    check(
        "ETA production flag also requires runtime readiness",
        not eta["is_production_model"] or eta["model_ready"] is True,
    )
    if eta["data_source"] != "genuine":
        check("non-genuine ETA is never production", eta["is_production_model"] is False)

    scheduling_response = client.get("/operation/auto-scheduling/ai/training/status")
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
