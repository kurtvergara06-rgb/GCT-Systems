"""CLI: Train the ETA / trip-duration Random Forest model.

Usage:
    python -m eta.train_model

Reads the real CSV produced by prepare_training_data, trains a
RandomForestRegressor (project-wide RF convention), evaluates it on a
held-out test split, and saves:
    eta/models/eta_duration_rf.pkl
    eta/models/eta_duration_features.json
    eta/models/eta_duration_report.txt
    eta/models/eta_duration_state.json

If there is insufficient real data the model is NOT saved and the state file
reports ETA_ML_NOT_READY; the prediction service then refuses to predict
rather than inventing numbers.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from eta.config import model_paths, training_data_paths  # noqa: E402
from eta.model import (  # noqa: E402
    EtaModelResult,
    save_eta_model,
    save_state,
    train_eta_model,
)
from eta.training_data import (  # noqa: E402
    ETA_TARGET,
    build_bus_encodings,
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
        result = EtaModelResult(message="No training data found.", n_samples=0)
        save_eta_model(result, paths)
        save_state(result, paths)
        return 1

    df = pd.read_csv(csv_path)

    result = train_eta_model(df)
    if result.trained:
        # Route/bus metadata is rebuilt from the same real CSV the model was
        # trained on, so prediction encodings always match training encodings.
        route_metadata = build_route_metadata(df)
        bus_encodings = build_bus_encodings(df)
        save_eta_model(result, paths, route_metadata, bus_encodings)
    else:
        save_eta_model(result, paths)
    save_state(result, paths)

    print("\n=== ETA model training results ===")
    print(f"Sample count:  {result.n_samples}")
    if not result.trained:
        print(f"  NOT TRAINED: {result.message}")
        return 1
    print(f"Train rows:    {result.n_train}")
    print(f"Test rows:     {result.n_test}")
    print("Metrics (held-out test):")
    print(f"  MAE  = {result.metrics['mae']:.2f} min")
    print(f"  RMSE = {result.metrics['rmse']:.2f} min")
    print(f"  R2   = {result.metrics['r2']:.4f}")
    print("Top feature importances:")
    for name, imp in sorted(result.feature_importances.items(), key=lambda kv: -kv[1])[:5]:
        print(f"  {name:<34} {imp:.4f}")
    print(f"\nArtifacts written to: {paths['dir']}")
    print("State file: " + str(paths["state"]))
    return 0


if __name__ == "__main__":
    sys.exit(main())