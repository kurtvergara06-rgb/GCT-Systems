"""Regression checks for the genuine-only inventory batch forecast contract.

Run from python_engine: python -m inventory.test_reorder_batch
"""

from types import SimpleNamespace
from unittest.mock import patch

from fastapi import HTTPException

from inventory.router import (
    InventoryBatchPredictionRequest,
    InventoryPredictionRequest,
    inventory_batch_predictions,
)


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def main() -> None:
    payload = InventoryBatchPredictionRequest(
        parts=[InventoryPredictionRequest(part_id="123", current_stock=8)]
    )

    # Never let development/sample forecasts become Warehouse reorder inputs.
    with (
        patch("inventory.predict.inventory_readiness", return_value=SimpleNamespace(ml_ready=True, source="sample")),
        patch("inventory.router.evaluate_model_source", return_value=SimpleNamespace(allowed=True)),
    ):
        try:
            inventory_batch_predictions(payload)
            raise AssertionError("Sample artifact unexpectedly passed the batch endpoint.")
        except HTTPException as exc:
            check(exc.status_code == 503, "Expected 503 for sample-backed model.")

    # A genuine-ready model provides per-item results through one request.
    with (
        patch("inventory.predict.inventory_readiness", return_value=SimpleNamespace(ml_ready=True, source="genuine")),
        patch("inventory.router.evaluate_model_source", return_value=SimpleNamespace(allowed=True)),
        patch("inventory.predict.predict_inventory_demand", return_value=object()),
        patch("inventory.predict.prediction_to_dict", return_value={
            "success": True,
            "part_id": "123",
            "data_source": "genuine",
            "is_production_model": True,
            "model_ready": True,
            "forecast_scope": "fleet_part",
            "forecast_period": "next_week",
            "unit": "pcs",
            "predicted_quantity_issued": 3,
        }),
    ):
        response = inventory_batch_predictions(payload)
        check(response["success"] is True, "Expected successful batch.")
        check(response["data_source"] == "genuine", "Expected genuine provenance.")
        check(len(response["results"]) == 1, "Expected one prediction.")

    print("Inventory genuine-only batch contract: PASS")


if __name__ == "__main__":
    main()
