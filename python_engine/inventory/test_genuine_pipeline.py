"""Regression checks for the genuine Inventory Model #4 data pipeline."""

import os
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

os.environ["INVENTORY_DATA_SOURCE"] = "genuine"
os.environ["INVENTORY_MIN_ROWS"] = "3"
os.environ["INVENTORY_MIN_PARTS"] = "1"
os.environ["INVENTORY_MIN_WEEKS"] = "3"
os.environ["INVENTORY_MIN_STOCK_OUT_EVENTS"] = "2"

from inventory.training_data import (  # noqa: E402
    GENUINE_SCOPE_ID,
    INVENTORY_FEATURE_COLUMNS,
    _panelize_genuine_movements,
    build_dataset,
    check_readiness_thresholds,
    validate_dataset,
)


def main() -> int:
    raw = pd.DataFrame(
        [
            {
                "movement_id": 1,
                "activity_at": "2026-06-01 08:00:00",
                "part_id": 10,
                "item_code": "BP-10",
                "part_name": "Brake Pad",
                "category": "Brakes",
                "unit": "pc",
                "movement_type": "Stock In",
                "quantity_change": 10,
                "previous_stock": 10,
                "new_stock": 20,
                "reorder_level": 5,
            },
            {
                "movement_id": 2,
                "activity_at": "2026-06-02 09:00:00",
                "part_id": 10,
                "item_code": "BP-10",
                "part_name": "Brake Pad",
                "category": "Brakes",
                "unit": "pc",
                "movement_type": "Stock Out",
                "quantity_change": -3,
                "previous_stock": 20,
                "new_stock": 17,
                "reorder_level": 5,
            },
            {
                "movement_id": 3,
                "activity_at": "2026-06-16 09:00:00",
                "part_id": 10,
                "item_code": "BP-10",
                "part_name": "Brake Pad",
                "category": "Brakes",
                "unit": "pc",
                "movement_type": "Stock Out",
                "quantity_change": -2,
                "previous_stock": 17,
                "new_stock": 15,
                "reorder_level": 5,
            },
            {
                "movement_id": 4,
                "activity_at": "2026-06-23 09:00:00",
                "part_id": 10,
                "item_code": "BP-10",
                "part_name": "Brake Pad",
                "category": "Brakes",
                "unit": "pc",
                "movement_type": "Stock Out",
                "quantity_change": -1,
                "previous_stock": 15,
                "new_stock": 14,
                "reorder_level": 5,
            },
        ]
    )

    panel = _panelize_genuine_movements(raw)
    assert len(panel) == 4, panel
    assert set(panel["bus_id"]) == {GENUINE_SCOPE_ID}
    assert panel["quantity_issued"].tolist() == [3.0, 0.0, 2.0, 1.0]
    assert int(panel["stock_out_events"].sum()) == 3
    assert set(panel["data_source"]) == {"genuine"}

    valid, errors, report = validate_dataset(panel)
    assert valid, errors
    assert report["stock_out_events"] == 3

    ready, issues = check_readiness_thresholds(panel)
    assert ready, issues

    features = build_dataset(panel)
    assert not features.empty
    assert set(INVENTORY_FEATURE_COLUMNS).issubset(features.columns)
    assert set(features["bus_id"]) == {GENUINE_SCOPE_ID}
    assert "on_hand_after" not in features.columns

    print("Inventory genuine-data pipeline: PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
