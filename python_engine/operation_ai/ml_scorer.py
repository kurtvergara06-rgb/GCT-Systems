"""Model loading and inference for the scheduling neural network.

Provides a thin wrapper around SchedulingScorer that lazy-loads the
trained model and exposes a simple ml_score() function for the router.

The model is optional — if the .pt file is missing or torch is not
installed, ml_score() returns None and the system falls back to
rule-based scoring.
"""

import logging
from pathlib import Path
from typing import Optional

from .schemas import BusData, DriverData, TripData

logger = logging.getLogger(__name__)

MODEL_DIR = Path(__file__).parent / "models"
MODEL_PATH = MODEL_DIR / "scheduling_model.pt"

_model = None
_model_loaded = False


def _load_model():
    global _model, _model_loaded

    if _model_loaded:
        return _model

    _model_loaded = True

    if not MODEL_PATH.exists():
        logger.info("No scheduling model found at %s — ML scoring disabled.", MODEL_PATH)
        return None

    try:
        import torch
        from .ml_model import SchedulingScorer

        model = SchedulingScorer(trip_dim=5, driver_dim=5, bus_dim=5)
        state_dict = torch.load(MODEL_PATH, map_location="cpu", weights_only=True)
        model.load_state_dict(state_dict)
        model.eval()

        _model = model
        logger.info("Scheduling model loaded from %s", MODEL_PATH)
        return _model

    except ImportError:
        logger.warning("torch not installed — ML scoring disabled.")
        return None
    except Exception as exc:
        logger.warning("Failed to load scheduling model: %s", exc)
        return None


def ml_score(
    trip: TripData,
    driver: DriverData,
    bus: BusData,
) -> Optional[float]:
    """Return ML prediction in [0, 100], or None if model unavailable."""
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
        logger.warning("ML scoring failed: %s", exc)
        return None
