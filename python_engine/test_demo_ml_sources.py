"""Regression checks for client-demo ML source isolation."""

from __future__ import annotations

import importlib
import os

os.environ["ML_RUNTIME_MODE"] = "development"
os.environ["DELAY_DATA_SOURCE"] = "demo"
os.environ["INVENTORY_DATA_SOURCE"] = "demo"

import delay.config as delay_config
import inventory.config as inventory_config
from ml_runtime_policy import evaluate_model_source, normalize_data_source

importlib.reload(delay_config)
importlib.reload(inventory_config)

assert delay_config.data_source() == "demo"
assert delay_config.training_data_paths()["csv"].name == "demo_delay_training.csv"
assert delay_config.training_data_paths()["features_csv"].name == "demo_delay_training_features.csv"
assert delay_config.model_paths()["model"].name == "demo_delay_arrival_rf.pkl"
assert delay_config.model_paths()["state"].name == "demo_delay_arrival_state.json"

assert inventory_config.data_source() == "demo"
assert inventory_config.training_data_paths()["csv"].name == "demo_inventory_training.csv"
assert inventory_config.training_data_paths()["features_csv"].name == "demo_inventory_training_features.csv"
assert inventory_config.model_paths()["model"].name == "demo_inventory_demand_rf.pkl"
assert inventory_config.model_paths()["state"].name == "demo_inventory_demand_state.json"

thresholds = inventory_config.data_thresholds()
assert thresholds["min_rows"] == 260
assert thresholds["min_parts"] == 20
assert thresholds["min_weeks"] == 13
assert thresholds["min_stock_out_events"] == 500

assert normalize_data_source("demo") == "synthetic"
policy = evaluate_model_source("demo model", "demo")
assert policy.allowed is True
assert policy.data_source == "synthetic"

os.environ["ML_RUNTIME_MODE"] = "production"
policy = evaluate_model_source("demo model", "demo")
assert policy.allowed is False
assert policy.model_ready_message == "MODEL NOT READY"

print("Demo ML source isolation checks passed.")
