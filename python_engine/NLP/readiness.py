"""Central readiness checks for required NLP/annotation modules."""

from __future__ import annotations

from importlib import import_module
from typing import Any


_REQUIRED_MODULES: dict[str, str] = {
    "ner": "NLP.ner_extractor",
    "severity": "NLP.severity_predictor",
    "severity_ner": "NLP.severity_ner_predictor",
    "anomaly": "NLP.anomaly_detector",
    "ingestion": "NLP.ingestion",
}


def required_module_status() -> dict[str, Any]:
    modules: dict[str, Any] = {}
    all_ready = True

    for key, module_name in _REQUIRED_MODULES.items():
        try:
            module = import_module(module_name)
            readiness = getattr(module, "readiness", None)
            status = readiness() if callable(readiness) else {"ready": True}
            ready = bool(status.get("ready", False))
            modules[key] = {
                "module": module_name,
                **status,
            }
            all_ready = all_ready and ready
        except Exception as error:  # noqa: BLE001
            all_ready = False
            modules[key] = {
                "module": module_name,
                "ready": False,
                "required": True,
                "error": str(error),
            }

    return {
        "ready": all_ready,
        "required": True,
        "modules": modules,
    }


def assert_required_modules() -> dict[str, Any]:
    """Raise when any required module is missing or reports not ready."""
    status = required_module_status()
    if status["ready"]:
        return status

    failed = [
        name
        for name, module_status in status["modules"].items()
        if not module_status.get("ready")
    ]
    raise RuntimeError(
        "Required NLP modules are not ready: " + ", ".join(failed)
    )
