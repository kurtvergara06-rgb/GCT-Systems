"""Lightweight GPS enrichment contract tests (no trained model dependencies)."""

import os
import unittest
from unittest.mock import patch

from fastapi import HTTPException
from NLP.router import GpsEnrichmentBatch, GpsEnrichmentItem, enrich_gps_batch


class GpsNlpEnrichmentTests(unittest.TestCase):
    def test_requires_shared_token(self):
        payload = GpsEnrichmentBatch(records=[GpsEnrichmentItem(id=1)])
        with patch.dict(os.environ, {"GCT_NLP_ENRICH_TOKEN": "secret"}):
            with self.assertRaises(HTTPException) as error:
                enrich_gps_batch(payload, x_gct_nlp_token="wrong")
        self.assertEqual(error.exception.status_code, 403)

    def test_rule_based_enrichment_does_not_claim_trained_model(self):
        payload = GpsEnrichmentBatch(records=[GpsEnrichmentItem(
            id=7, description="Bus delayed by traffic with engine idling",
            bus_no="BUS002", total_minutes=60, idling_minutes=55,
        )])
        with patch.dict(os.environ, {"GCT_NLP_ENRICH_TOKEN": "secret", "GCT_ENABLE_SPACY_NLP": "true"}):
            with patch("NLP.ner_extractor._get_spacy_model", side_effect=AssertionError("spaCy must not load")):
                response = enrich_gps_batch(payload, x_gct_nlp_token="secret")
        self.assertTrue(response["success"])
        self.assertEqual(response["records"][0]["id"], 7)
        self.assertFalse(response["trained_model"])
        self.assertEqual(response["source"], "operational_rules")
        self.assertIn("entities", response["records"][0])
        self.assertIn("anomaly", response["records"][0])

    def test_request_is_capped_at_twenty_five(self):
        from pydantic import ValidationError
        with self.assertRaises(ValidationError):
            GpsEnrichmentBatch(records=[GpsEnrichmentItem(id=i + 1) for i in range(26)])


if __name__ == "__main__":
    unittest.main()
