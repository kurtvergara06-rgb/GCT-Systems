"""Model artifact version and compatibility guard.

Ensures that scikit-learn model persistence is safe and reproducible.
In production runtime, if a saved model's scikit-learn version is inconsistent
with the active runtime version, the model must not silently serve predictions.
"""

from __future__ import annotations

import datetime
import logging
from pathlib import Path
from typing import Any, Dict, Optional, Tuple
import warnings

import joblib
import sklearn
from sklearn.exceptions import InconsistentVersionWarning

from ml_runtime_policy import is_production_runtime

logger = logging.getLogger(__name__)

CURRENT_SKLEARN_VERSION = sklearn.__version__


def get_model_metadata(
    model_name: str,
    training_source: str,
    model_version: str = "1.0.0",
    feature_schema_version: str = "1.0",
    trained_at: Optional[str] = None,
) -> Dict[str, Any]:
    """Generate standardized metadata for model persistence."""
    return {
        "model_name": model_name,
        "model_version": model_version,
        "sklearn_version": CURRENT_SKLEARN_VERSION,
        "training_source": training_source,
        "trained_at": trained_at or datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "feature_schema_version": feature_schema_version,
    }


def validate_model_version(
    model_name: str,
    state: Optional[Dict[str, Any]] = None,
    captured_warnings: Optional[list] = None,
) -> Tuple[bool, str]:
    """Check if model's serialized sklearn version is compatible with runtime.

    Returns:
        (is_valid, reason)
    """
    saved_version = None
    if state and isinstance(state, dict):
        saved_version = state.get("sklearn_version")

    has_inconsistent_warning = False
    warning_message = ""
    if captured_warnings:
        for w in captured_warnings:
            if issubclass(w.category, InconsistentVersionWarning):
                has_inconsistent_warning = True
                warning_message = str(w.message)
                break

    # Determine if there is a mismatch
    mismatch_detected = False
    mismatch_detail = ""

    if saved_version and saved_version != CURRENT_SKLEARN_VERSION:
        mismatch_detected = True
        mismatch_detail = (
            f"Model artifact '{model_name}' was saved with scikit-learn {saved_version}, "
            f"but current runtime is using {CURRENT_SKLEARN_VERSION}."
        )
    elif has_inconsistent_warning:
        mismatch_detected = True
        mismatch_detail = (
            f"scikit-learn InconsistentVersionWarning detected for '{model_name}': {warning_message}"
        )

    if mismatch_detected:
        if is_production_runtime():
            error_msg = (
                f"MODEL NOT READY: {mismatch_detail} "
                "In production, predictions cannot be served from incompatible model artifacts."
            )
            logger.error(error_msg)
            return False, error_msg

        logger.warning(
            "Model version mismatch allowed in non-production runtime: %s",
            mismatch_detail,
        )
        return True, f"Development warning: {mismatch_detail}"

    return True, "Version compatible."


def safe_load_model(
    model_path: Path | str,
    model_name: str,
    state: Optional[Dict[str, Any]] = None,
) -> Tuple[Optional[Any], str]:
    """Safely load a joblib model artifact with version validation.

    Returns:
        (model_or_none, status_reason)
    """
    path = Path(model_path)
    if not path.exists():
        return None, f"Model file not found at {path}"

    try:
        with warnings.catch_warnings(record=True) as captured:
            warnings.simplefilter("always")
            model = joblib.load(path)

        is_valid, reason = validate_model_version(
            model_name=model_name,
            state=state,
            captured_warnings=captured,
        )

        if not is_valid:
            return None, reason

        return model, reason

    except Exception as exc:  # noqa: BLE001
        logger.exception("Failed to load model %s: %s", path, exc)
        return None, f"Failed to deserialize model: {exc}"
