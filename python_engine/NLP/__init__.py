"""Required NLP capability package.

Avoid importing heavyweight NLP dependencies just by importing this package.
The FastAPI lifespan still calls assert_required_modules() and checks each
required module before serving requests.
"""

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
