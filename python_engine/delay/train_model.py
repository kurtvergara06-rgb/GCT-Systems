"""CLI: Train the delay-prediction Random Forest model (Model #3).

Usage:
    python -m delay.train_model
    DELAY_DATA_SOURCE=genuine python -m delay.train_model

Reads the feature CSV produced by prepare_training_data, trains a
RandomForestRegressor (project-wide RF convention), evaluates it on a
chronologically held-out test split, and saves:
    delay/models/delay_arrival_rf.pkl
    delay/models/delay_arrival_features.json
    delay/models/delay_arrival_report.txt
    delay/models/delay_arrival_state.json

SAMPLE mode: trains on the SAMPLE / DEMONSTRATION dataset (never touches MySQL).
GENUINE mode: trains ONLY on genuine matched DDR history (Laravel export). The
readiness gate is re-checked here as a final guard; if it fails, the model is
NOT trained and the "NOT READY FOR GENUINE TRAINING" report is printed.
"""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from delay.config import is_genuine, model_paths, training_data_paths  # noqa: E402
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
    readiness_report,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("train_delay_model")


def main() -> int:
    source = "genuine" if is_genuine() else "sample"
    paths = model_paths()
    features_path = training_data_paths()["features_csv"]

    if not features_path.exists():
        print(f"Delay feature CSV not found: {features_path}")
        print("Run `python -m delay.prepare_training_data` first "
              "(in the matching DELAY_DATA_SOURCE mode).")
        # Do NOT save artifacts when feature data is missing - this would
        # overwrite any existing model with an empty one. Just return an error.
        return 1

    df = pd.read_csv(features_path)
    df["trip_date"] = pd.to_datetime(df["trip_date"], errors="coerce")

    if source == "genuine":
        report = readiness_report(df)
        if not report["ready"]:
            print("\n" + "=" * 68)
            print("DELAY MODEL NOT READY FOR GENUINE TRAINING")
            print("=" * 68)
            print("The genuine matched history fails the data-sufficiency gate. "
                  "Model NOT trained (no silent fallback to sample).")
            for field, detail in report["thresholds"].items():
                status = "PASS" if detail["passed"] else "FAIL"
                print(f"  threshold {field:<12}: {detail['actual']} / "
                      f"{detail['threshold']} ({status})")
            return 2

    result = train_delay_model(df, source=source)
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

    source_label = "GENUINE GCT OPERATIONAL DATA" if source == "genuine" \
        else "SAMPLE / DEMONSTRATION DATA"
    print(f"\n=== Delay model training results ({source_label}) ===")
    print(f"Data source:   {source}")
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