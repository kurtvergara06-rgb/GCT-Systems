"""Regression checks for 512 MB scheduling inference.

Run from python_engine:
    python -m unittest operation_ai.test_low_memory_inference
"""
import unittest
from unittest.mock import Mock, patch

import numpy as np

from operation_ai.ml import predict
from operation_ai.schemas import BusData, DriverData, TripData


def trip():
    return TripData(
        id=1, trip_code="TEST-1", trip_date="2026-11-15",
        shift="Morning", departure_time="08:00", arrival_time="09:00",
    )


def buses():
    return [
        BusData(id=i, bus_no=f"BUS-{i}", status="Active")
        for i in (1, 2, 3)
    ]


class LowMemoryInferenceTests(unittest.TestCase):
    def test_insufficient_driver_history_does_not_load_model(self):
        with patch.object(predict, "_component_state", return_value={"ready": True, "sample_count": 6}), \
             patch.object(predict, "_load_driver_model") as loader:
            result = predict.driver_readiness()
        self.assertFalse(result.ml_ready)
        self.assertEqual(result.source, "data_fallback")
        loader.assert_not_called()

    def test_buses_are_scored_in_one_batch(self):
        model = Mock()
        model.predict.return_value = np.array([0.2, 0.5, 0.9])
        ready_bus = predict.ComponentReadiness(ml_ready=True, source="ml", sample_count=367)
        ready_driver = predict.ComponentReadiness(ml_ready=False, source="data_fallback")
        with patch.object(predict, "bus_readiness", return_value=ready_bus), \
             patch.object(predict, "driver_readiness", return_value=ready_driver), \
             patch.object(predict, "_load_bus_model", return_value=model):
            result = predict.evaluate_candidates(trip(), [], buses())
        model.predict.assert_called_once()
        self.assertEqual(model.predict.call_args.args[0].shape, (3, len(predict.BUS_FEATURE_NAMES)))
        self.assertEqual(result.bus_scores, {1: 0.2, 2: 0.5, 3: 0.9})

    def test_bad_batch_falls_back_without_partial_ml(self):
        model = Mock()
        model.predict.return_value = np.array([0.5, float("nan"), 0.8])
        ready_bus = predict.ComponentReadiness(ml_ready=True, source="ml", sample_count=367)
        ready_driver = predict.ComponentReadiness(ml_ready=False, source="data_fallback")
        with patch.object(predict, "bus_readiness", return_value=ready_bus), \
             patch.object(predict, "driver_readiness", return_value=ready_driver), \
             patch.object(predict, "_load_bus_model", return_value=model):
            result = predict.evaluate_candidates(trip(), [], buses())
        self.assertFalse(result.bus_readiness.ml_ready)
        self.assertEqual(result.bus_readiness.source, "rule_fallback")
        self.assertEqual(result.bus_scores, {})


if __name__ == "__main__":
    unittest.main()
