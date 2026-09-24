"""CLI: Train Delay Model #3 from sample, frontend-demo, or genuine data."""

import logging
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from delay.config import data_source, model_paths, training_data_paths  # noqa: E402
from delay.model import save_delay_model, save_state, train_delay_model  # noqa: E402
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
    source = data_source()
    paths = model_paths()
    features_path = training_data_paths()["features_csv"]

    if not features_path.exists():
        print(f"Delay feature CSV not found: {features_path}")
        print("Run `python -m delay.prepare_training_data` first in the same "
              "DELAY_DATA_SOURCE mode.")
        return 1

    df = pd.read_csv(features_path)
    df["trip_date"] = pd.to_datetime(df["trip_date"], errors="coerce")

    if source in {"genuine", "demo"}:
        report = readiness_report(df)
        if not report["ready"]:
            print("\n" + "=" * 68)
            print(f"DELAY MODEL NOT READY FOR {source.upper()} TRAINING")
            print("=" * 68)
            for field, detail in report["thresholds"].items():
                status = "PASS" if detail["passed"] else "FAIL"
                print(
                    f"  threshold {field:<12}: {detail['actual']} / "
                    f"{detail['threshold']} ({status})"
                )
            return 2

    # Demo records are synthetic by policy. Persist them as a SAMPLE model so
    # the shared runtime policy can never mistake the client-demo artifact for
    # a production/genuine model. Demo mode uses separate artifact filenames.
    artifact_source = "sample" if source == "demo" else source
    result = train_delay_model(df, source=artifact_source)

    if result.trained:
        encoders = build_encoders(df)
        route_metadata = build_route_metadata(df)
        driver_metadata = build_driver_metadata(df)
        bus_metadata = build_bus_metadata(df)
        save_delay_model(
            result,
            paths,
            encoders,
            route_metadata,
            driver_metadata,
            bus_metadata,
        )
    else:
        save_delay_model(result, paths)
    save_state(result, paths)

    if source == "genuine":
        source_label = "GENUINE GCT OPERATIONAL DATA"
    elif source == "demo":
        source_label = "FRONTEND DEMO / SYNTHETIC DATA (saved as development model)"
    else:
        source_label = "SAMPLE / DEVELOPMENT DATA"

    print(f"\n=== Delay model training results ({source_label}) ===")
    print(f"Pipeline source: {source}")
    print(f"Artifact class:  {artifact_source}")
    print(f"Sample count:    {result.n_samples}")
    if not result.trained:
        print(f"NOT TRAINED:     {result.message}")
        return 1

    print(f"Train rows:      {result.n_train}")
    print(f"Test rows:       {result.n_test}")
    print(f"Train period:    {result.periods.get('train_start')} .. {result.periods.get('train_end')}")
    print(f"Test period:     {result.periods.get('test_start')} .. {result.periods.get('test_end')}")
    print("Metrics (chronological held-out test):")
    print(f"  MAE  = {result.metrics['mae']:.2f} min")
    print(f"  RMSE = {result.metrics['rmse']:.2f} min")
    print(f"  R2   = {result.metrics['r2']:.4f}")
    print(f"\nArtifacts written to: {paths['dir']}")
    if source == "demo":
        print("DEMO / SYNTHETIC ONLY - this artifact is not production eligible.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
