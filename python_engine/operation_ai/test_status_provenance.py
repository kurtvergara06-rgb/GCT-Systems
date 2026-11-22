"""Read-only status contract checks; never load/retrain/write artifacts or query a database."""
import unittest
from unittest.mock import patch

from operation_ai.ml.predict import ComponentReadiness
from operation_ai.router import ml_training_status


class StatusProvenanceTests(unittest.TestCase):
    def test_loaded_model_and_saved_counts_do_not_imply_audited_provenance(self):
        bus = ComponentReadiness(ml_ready=True, source="ml", sample_count=367)
        driver = ComponentReadiness(ml_ready=False, source="data_fallback", sample_count=6)
        with patch("operation_ai.router.bus_readiness", return_value=bus), \
             patch("operation_ai.router.driver_readiness", return_value=driver):
            status = ml_training_status()
        self.assertTrue(status.bus_model_ready)
        self.assertFalse(status.driver_model_ready)
        self.assertEqual(status.bus_sample_count, 367)
        self.assertEqual(status.driver_sample_count, 6)
        self.assertEqual(status.data_source, "unverified")
        self.assertFalse(status.provenance_verified)
        self.assertFalse(status.bus_is_production_model)
        self.assertFalse(status.driver_is_production_model)
        self.assertFalse(status.policy_enforced)
        self.assertEqual(status.provenance_status, "verification_pending")
        self.assertNotIn("sample_count", status.model_dump())


if __name__ == "__main__":
    unittest.main()
