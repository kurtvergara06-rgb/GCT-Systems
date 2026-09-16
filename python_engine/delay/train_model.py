"""CLI: Train the delay-prediction Random Forest model (Model #3).

Usage:
    python -m delay.train_model

Reads the SAMPLE feature CSV produced by prepare_training_data, trains a
RandomForestRegressor (project-wide RF convention), evaluates it on a
chronologically held-out test split, and saves:
    delay/models/delay_arrival_rf.pkl
    delay/models/delay_arrival_features.json
    delay/models/delay_arrival_report.txt
    delay/models/delay_arrival_state.json

SAMPLE / DEMONSTRATION DATA ONLY - the model is NEVER trained on genuine GCT
historical delay records, and this script never touches the MySQL database.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from delay.config import model_paths, training_data_paths  # noqa: E402
from delay.model import (  # noqa: E402
    DelayModelResult,
    save_delay_model,
    save_state,
    train_delay_model,
)
from delay.training_data import DLY_TARGET  # noqa: E402
from delay.training_data import (  # noqa: E402
    build_bus_metadata,
    build_driver_metadata,
    build_encoders,
    build_route_metadata,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("train_delay_model")


def main() -> int:
    paths = model_paths()
    features_path = training_data_paths()["features_csv"]

    if not features_path.exists():
        print(f"Delay feature CSV not found: {features_path}")
        print("Run `python -m delay.prepare_training_data` first.")
        result = DelayModelResult(message="No training data found.", n_samples=0)
        save_delay_model(result, paths)
        save_state(result, paths)
        return 1

    df = pd.read_csv(features_path)
    df["trip_date"] = pd.to_datetime(df["trip_date"], errors="coerce")

    result = train_delay_model(df)
    if result.trained:
        # Encoders/metadata are rebuilt from the very same CSV the model was
        # trained on, so prediction encodings always match training encodings.
        encoders = build_encoders(df)
        route_metadata = build_route_metadata(df)
        driver_metadata = build_driver_metadata(df)
        bus_metadata = build_bus_metadata(df)
        save_delay_model(result, paths, encoders, route_metadata, driver_metadata, bus_metadata)
    else:
        save_delay_model(result, paths)
    save_state(result, paths)

    print("\n=== Delay model training results (SAMPLE / DEMONSTRATION DATA) ===")
    print(f"Sample count:  {result.n_samples}")
    if not result.trained:
        print(f"  NOT TRAINED: {result.message}")
        return 1
    print(f"Train rows:    {result.n_train}")
    print(f"Test rows:     {result.n_test}")
    print(f"Train period:  {result.periods.get('train_start')} .. {result.periods.get('train_end')}")
    print(f"Test period:   {result.periods.get('test_start')} .. {result.periods.get('test_end')}")
    print("Metrics (chronological held-out test):")
    print(f"  MAE  = {result.metrics['mae']:.2f} min")
    print(f"  RMSE = {result.metrics['rmse']:.2f} min")
    print(f"  R2   = {result.metrics['r2']:.4f}")
    print("Top feature importances:")
    for name, imp in sorted(result.feature_importances.items(), key=lambda kv: -kv[1])[:5]:
        print(f"  {name:<36} {imp:.4f}")
    print(f"\nArtifacts written to: {paths['dir']}")
    print("State file: " + str(paths["state"]))
    return 0


if __name__ == "__main__":
    sys.exit(main())