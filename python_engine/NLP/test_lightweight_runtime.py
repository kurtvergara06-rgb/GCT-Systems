"""Low-memory NLP regression tests.

Run from python_engine:
    python -m unittest NLP.test_lightweight_runtime
"""
import os
import sys
import unittest
from unittest.mock import patch

from NLP.ner_extractor import extract_entities
from NLP.router import TextAnalysisRequest, analyze_text


class LightweightNlpTests(unittest.TestCase):
    def test_rule_extraction_without_spacy(self):
        with patch.dict(os.environ, {"GCT_ENABLE_SPACY_NLP": "false"}):
            result = extract_entities("BUS-015 encountered traffic and engine failure.")
        self.assertEqual(result["source"], "domain_rules")
        self.assertIn("BUS-015", result["bus"])
        self.assertIn("TRAFFIC_EVENT", {e["label"] for e in result["events"]})

    def test_text_endpoint_preserves_rule_provenance(self):
        with patch.dict(os.environ, {"GCT_ENABLE_SPACY_NLP": "false"}):
            result = analyze_text(TextAnalysisRequest(text="BUS-015 was delayed by traffic."))
        self.assertTrue(result["success"])
        self.assertFalse(result["trained_model"])
        self.assertEqual(result["source"], "operational_rules")
        self.assertIn("BUS-015", result["entities"]["bus"])

    def test_no_spacy_import_in_default_mode(self):
        with patch.dict(os.environ, {"GCT_ENABLE_SPACY_NLP": "false"}):
            sys.modules.pop("spacy", None)
            extract_entities("BUS-015 traffic")
            self.assertNotIn("spacy", sys.modules)


if __name__ == "__main__":
    unittest.main()
