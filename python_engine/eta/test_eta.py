"""Manual integration test for a prepared genuine ETA training CSV.

This test intentionally does not overwrite repository model artifacts. First run:
    python -m eta.prepare_training_data
Then:
    python -m eta.test_eta
"""

from __future__ import annotations

import json
import sys
from pathlib import Path
from tempfile import TemporaryDirectory

import numpy as np
import pandas as pd

from eta.config import data_thresholds, training_data_paths
from eta.model import save_eta_model, save_state, train_eta_model
from eta.training_data import (
    ETA_FEATURE_COLUMNS,
    ETA_TARGET,
    build_bus_encodings,
    build_route_encodings,
    build_route_metadata,
)


def check(label: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{label}: {detail}")
    print(f"PASS: {label}" + (f" ({detail})" if detail else ""))


def main() -> int:
    csv_path = training_data_paths()["csv"]
    if not csv_path.exists():
        print(f"MODEL NOT READY: ETA training CSV not found: {csv_path}")
        print("Run `python -m eta.prepare_training_data` against the verified GCT database first.")
        return 1

    df = pd.read_csv(csv_path)
    thresholds = data_thresholds()
    check("training CSV has explicit provenance", "data_origin" in df.columns)
    check("all prepared rows are genuine", set(df["data_origin"].astype(str).str.lower()) == {"genuine"})
    check("feature schema + target present", all(c in df.columns for c in ETA_FEATURE_COLUMNS + [ETA_TARGET]))
    check("minimum record threshold met", len(df) >= thresholds["min_records"], str(len(df)))
    check("minimum route threshold met", df["route"].nunique() >= thresholds["min_routes"], str(df["route"].nunique()))
    check("target has variance", float(df[ETA_TARGET].std()) > 0)

    result = train_eta_model(df)
    check("model trains", result.trained, result.message)
    check("training source remains genuine", result.data_source == "genuine", result.data_source)
    check("chronological holdout exists", result.n_train > 0 and result.n_test > 0)
    check("MAE finite", np.isfinite(result.metrics["mae"]))
    check("RMSE finite", np.isfinite(result.metrics["rmse"]))
    check("R2 finite", np.isfinite(result.metrics["r2"]))
    check("operator baseline measured", np.isfinite(result.metrics["operator_baseline_mae"]))
    check(
        "production quality gate passed",
        result.quality_ready,
        (
            f"model MAE={result.metrics['mae']:.2f}, "
            f"baseline MAE={result.metrics['operator_baseline_mae']:.2f}"
        ),
    )

    with TemporaryDirectory() as tmp:
        directory = Path(tmp)
        paths = {
            "dir": directory,
            "model": directory / "eta.pkl",
            "features": directory / "features.json",
            "report": directory / "report.txt",
            "state": directory / "state.json",
        }
        route_encodings = build_route_encodings(df)
        save_eta_model(
            result,
            paths,
            route_metadata=build_route_metadata(df),
            route_encodings=route_encodings,
            bus_encodings=build_bus_encodings(df),
        )
        save_state(result, paths)

        state = json.loads(paths["state"].read_text(encoding="utf-8"))
        features = json.loads(paths["features"].read_text(encoding="utf-8"))
        check("saved state is genuine", state["training_source"] == "genuine")
        check("saved state quality ready", state["quality_ready"] is True)
        check("feature order is exact", features["features"] == ETA_FEATURE_COLUMNS)
        check("route encodings persisted", features["route_encodings"] == route_encodings)

    print("ETA genuine-data integration PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
