"""Inventory Demand Forecasting — Model #4 Development Prototype.

Development-pipeline implementation for forecasting weekly spare-part demand
(``quantity_issued``) per bus + part combination for the GCT bus fleet.

IMPORTANT
---------
This module is trained exclusively on GENERATED SAMPLE / DEVELOPMENT data
(``training_data/inventory/sample_inventory_training.csv`` in the project
root). It is a prototype only. It must NOT be presented as a model trained on
actual GCT operational inventory data. See ``INVENTORY_MODEL_READINESS.md``
for the genuine-data gate and ``INVENTORY_MODEL_REPORT.md`` for the full
development report.

The architecture mirrors Model #1 (python_engine/eta/) and Model #2
(python_engine/fuel/) while adapting the prediction task to a supervised,
time-series / panel demand-forecasting problem:
    - target   : quantity_issued (units) for one (bus, part) week
    - granularity : item (bus + part) x calendar week
    - validation: strictly chronological train/test split (no random shuffle)
"""

from __future__ import annotations