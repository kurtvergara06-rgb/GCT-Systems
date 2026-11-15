"""Required NLP capability package for the GCT Python engine.

Importing :mod:`NLP` verifies that the annotation modules shipped with the
engine are present. Runtime record-level errors are still handled gracefully,
but a checkout missing one of these required modules is no longer considered
a complete deployment.
"""

from . import anomaly_detector as anomaly_detector
from . import ingestion as ingestion
from . import ner_extractor as ner_extractor
from . import severity_ner_predictor as severity_ner_predictor
from . import severity_predictor as severity_predictor
from .readiness import assert_required_modules, required_module_status

__all__ = [
    "anomaly_detector",
    "ingestion",
    "ner_extractor",
    "severity_ner_predictor",
    "severity_predictor",
    "assert_required_modules",
    "required_module_status",
]
