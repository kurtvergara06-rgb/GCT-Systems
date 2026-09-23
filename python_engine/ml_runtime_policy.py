"""Shared runtime policy for ML data provenance.

Production must never serve predictions from generated/synthetic/sample models.
Generated data remains valid for explicit development/demo use only.

Runtime mode resolution:
1. ``ML_RUNTIME_MODE`` when explicitly set (``production`` / ``development``).
2. Render automatically counts as production when ``RENDER=true``.
3. ``APP_ENV=production`` also counts as production.
4. Everything else defaults to development.
"""

from __future__ import annotations

import os
from dataclasses import dataclass

_TRUE_VALUES = {"1", "true", "yes", "on"}
_PRODUCTION_VALUES = {"production", "prod", "live"}
_DEVELOPMENT_VALUES = {"development", "dev", "demo", "testing", "test", "local"}
_GENUINE_SOURCES = {"genuine", "real", "operational", "production"}
_SYNTHETIC_SOURCES = {"sample", "synthetic", "generated", "demo", "development"}


@dataclass(frozen=True)
class RuntimePolicyDecision:
    allowed: bool
    runtime_mode: str
    data_source: str
    model_ready_message: str
    reason: str


def runtime_mode() -> str:
    """Return ``production`` or ``development`` for ML serving decisions."""
    explicit = os.environ.get("ML_RUNTIME_MODE", "").strip().lower()
    if explicit in _PRODUCTION_VALUES:
        return "production"
    if explicit in _DEVELOPMENT_VALUES:
        return "development"

    if os.environ.get("RENDER", "").strip().lower() in _TRUE_VALUES:
        return "production"

    if os.environ.get("APP_ENV", "").strip().lower() in _PRODUCTION_VALUES:
        return "production"

    return "development"


def is_production_runtime() -> bool:
    return runtime_mode() == "production"


def normalize_data_source(source: str | None) -> str:
    value = str(source or "unknown").strip().lower()
    if value in _GENUINE_SOURCES:
        return "genuine"
    if value in _SYNTHETIC_SOURCES:
        return "synthetic"
    return value or "unknown"


def synthetic_models_allowed() -> bool:
    """Synthetic models are only allowed outside production."""
    return not is_production_runtime()


def evaluate_model_source(model_name: str, source: str | None) -> RuntimePolicyDecision:
    """Decide whether a model source may serve predictions in this runtime."""
    mode = runtime_mode()
    normalized = normalize_data_source(source)

    if mode == "production" and normalized != "genuine":
        return RuntimePolicyDecision(
            allowed=False,
            runtime_mode=mode,
            data_source=normalized,
            model_ready_message="MODEL NOT READY",
            reason=(
                f"{model_name} cannot serve {normalized} data in production. "
                "Production requires a model trained on sufficient genuine GCT "
                "operational data; generated/synthetic data is development-only."
            ),
        )

    return RuntimePolicyDecision(
        allowed=True,
        runtime_mode=mode,
        data_source=normalized,
        model_ready_message="MODEL READY",
        reason=(
            f"{model_name} source '{normalized}' is allowed in {mode} runtime."
        ),
    )
