"""Legacy neural-network scheduling scorer.

This checkpoint is trained from generated/synthetic JSONL data and is retained
for development/testing only. Production must never use it as an operational
ML signal. The active Operation AI Random Forest pipeline under
``operation_ai/ml/`` is trained from real GCT GPS/attendance records.
"""

import logging
from pathlib import Path
from typing import Optional

from ml_runtime_policy import synthetic_models_allowed
from .schemas import BusData, DriverData, TripData

logger = logging.getLogger(__name__)

MODEL_DIR = Path(__file__).parent / "models"
MODEL_PATH = MODEL_DIR / "scheduling_model.pt"

_model = None
_model_loaded = False


def _load_model():
    global _model, _model_loaded

    if not synthetic_models_allowed():
        logger.warning(
            "Legacy generated-data scheduling model is blocked in production."
        )
        return None

    if _model_loaded:
        return _model

    _model_loaded = True

    if not MODEL_PATH.exists():
        logger.info("No scheduling model found at %s — synthetic ML scoring disabled.", MODEL_PATH)
        return None

    try:
        import torch
        from .ml_model import SchedulingScorer

        model = SchedulingScorer(trip_dim=5, driver_dim=5, bus_dim=5)
        state_dict = torch.load(MODEL_PATH, map_location="cpu", weights_only=True)
        model.load_state_dict(state_dict)
        model.eval()

        _model = model
        logger.info("Development-only scheduling model loaded from %s", MODEL_PATH)
        return _model

    except ImportError:
        logger.warning("torch not installed — synthetic ML scoring disabled.")
        return None
    except Exception as exc:
        logger.warning("Failed to load scheduling model: %s", exc)
        return None


def ml_score(
    trip: TripData,
    driver: DriverData,
    bus: BusData,
) -> Optional[float]:
    """Return development-only synthetic ML score, or None when disallowed."""
    model = _load_model()
    if model is None:
        return None

    try:
        import torch
        from .ml_features import encode_all

        trip_t, driver_t, bus_t = encode_all(trip, driver, bus)

        with torch.no_grad():
            score = model(
                trip_t.unsqueeze(0),
                driver_t.unsqueeze(0),
                bus_t.unsqueeze(0),
            )

        return round(score.item(), 1)

    except Exception as exc:
        logger.warning("Synthetic scheduling ML scoring failed: %s", exc)
        return None
