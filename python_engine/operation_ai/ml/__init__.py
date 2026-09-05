"""Machine-learning ranking subsystem for Operation AI.

This package builds and serves the Random Forest models that rank valid
buses (and drivers) after hard eligibility filtering. Hard constraints live
in operation_ai.ranking; this package only ranks candidates that have already
passed those constraints.

The BUS model is trained on real GPS trip outcomes. The DRIVER model is
trained on real driver attendance history; when too few drivers exist it falls
back to a transparent data-derived reliability value instead of an
under-trained model.
"""

from .config import data_thresholds, model_paths, training_data_paths
from .predict import (
    ComponentReadiness,
    PredictionOutcome,
    bus_readiness,
    data_derived_driver_score,
    driver_readiness,
    evaluate_candidates,
    predict_bus_suitability,
    predict_driver_suitability,
)

__all__ = [
    "data_thresholds",
    "model_paths",
    "training_data_paths",
    "ComponentReadiness",
    "PredictionOutcome",
    "bus_readiness",
    "driver_readiness",
    "data_derived_driver_score",
    "evaluate_candidates",
    "predict_bus_suitability",
    "predict_driver_suitability",
]
