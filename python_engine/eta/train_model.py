"""CLI: benchmark ETA regressors and persist the best held-out candidate.

The input CSV must have been produced by ``eta.prepare_training_data`` so each
row carries explicit data provenance. Genuine production readiness is never
inferred from a filename or from the mere presence of a model artifact.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from eta.config import model_paths, training_data_paths  # noqa: E402
from eta.model import EtaModelResult, save_eta_model, save_state, train_eta_model  # noqa: E402
from eta.training_data import (  # noqa: E402
    build_bus_encodings,
    build_route_encodings,
    build_route_metadata,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("train_eta_model")


def main() -> int:
    paths = model_paths()
    csv_path = training_data_paths()["csv"]

    if not csv_path.exists():
        print(f"ETA training CSV not found: {csv_path}")
        print("Run `python -m eta.prepare_training_data` first.")
        result = EtaModelResult(message="No verified ETA training data found.")
        save_eta_model(result, paths)
        save_state(result, paths)
        return 1

    df = pd.read_csv(csv_path)
    result = train_eta_model(df)

    if result.trained:
        save_eta_model(
            result,
            paths,
            route_metadata=build_route_metadata(df),
            route_encodings=build_route_encodings(df),
            bus_encodings=build_bus_encodings(df),
        )
    else:
        save_eta_model(result, paths)
    save_state(result, paths)

    print("\n=== ETA model training results ===")
    print(f"Data source:    {result.data_source}")
    print(f"Sample count:   {result.n_samples}")
    print(f"Distinct routes:{result.distinct_routes}")
    if not result.trained:
        print(f"MODEL NOT READY: {result.message}")
        return 1

    print(f"Train rows:     {result.n_train}")
    print(f"Test rows:      {result.n_test}")
    print(f"Selected model: {result.selected_model_name}")
    print("Metrics (chronological held-out test):")
    print(f"  MAE  = {result.metrics['mae']:.2f} min")
    print(f"  RMSE = {result.metrics['rmse']:.2f} min")
    print(f"  R2   = {result.metrics['r2']:.4f}")
    print(f"  Operator baseline MAE = {result.metrics['operator_baseline_mae']:.2f} min")
    print(f"  MAE improvement       = {result.metrics['mae_improvement_percent']:.2f}%")
    print(f"Quality ready:  {'YES' if result.quality_ready else 'NO'}")
    print("Candidate comparison:")
    for candidate in result.candidate_models:
        metrics = candidate.get("metrics") or {}
        marker = " [SELECTED]" if candidate.get("selected") else ""
        if candidate.get("status") != "evaluated":
            print(f"  {candidate['name']}: {candidate.get('status')}")
            continue
        print(
            f"  {candidate['name']}{marker}: "
            f"MAE={metrics.get('mae', float('nan')):.2f}, "
            f"RMSE={metrics.get('rmse', float('nan')):.2f}, "
            f"R2={metrics.get('r2', float('nan')):.4f}"
        )
    print(f"Artifacts:      {paths['dir']}")

    # A model may be trained for analysis/development but still fail the
    # production quality gate. Return non-zero so deployment pipelines notice.
    return 0 if result.quality_ready else 1


if __name__ == "__main__":
    sys.exit(main())
