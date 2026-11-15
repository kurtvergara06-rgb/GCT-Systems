"""Self-contained regression coverage for the ETA Model #1 trainer.

Run from python_engine:
    python -m eta.test_training_pipeline
"""

from __future__ import annotations

import json
from datetime import datetime, timedelta
from pathlib import Path
from tempfile import TemporaryDirectory

import pandas as pd

from eta.model import save_eta_model, save_state, train_eta_model
from eta.training_data import (
    ETA_FEATURE_COLUMNS,
    build_bus_encodings,
    build_dataset,
    build_route_encodings,
    build_route_metadata,
)


def check(label: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{label}: {detail}")
    print(f"PASS: {label}" + (f" ({detail})" if detail else ""))


def make_dataset(rows: int = 90, routes: int = 3, origin: str = "genuine") -> pd.DataFrame:
    route_defs = [
        ("Plant A - Plant B", 10.0, 30.0),
        ("Plant A - Plant C", 15.0, 45.0),
        ("Plant B - Plant C", 20.0, 60.0),
    ][:routes]
    start = datetime(2026, 1, 1, 6, 0)
    raw = []
    for index in range(rows):
        route, distance, actual = route_defs[index % len(route_defs)]
        raw.append(
            {
                "gps_record_id": index + 1,
                "bus_no": f"GCT-{101 + (index % 4)}",
                "route": route,
                "beginning_at": start + timedelta(hours=index * 4),
                "duration_minutes": actual + float(index % 3),
                "shift": "Morning" if index % 2 == 0 else "Afternoon",
                "distance_km": distance,
                # Deliberately worse than the learnable historical outcome so
                # the model must prove improvement over the operator baseline.
                "route_estimated_time_minutes": actual + 20.0,
                "data_origin": origin,
            }
        )
    return build_dataset(pd.DataFrame(raw))


def main() -> None:
    genuine = make_dataset()
    check("dataset keeps explicit genuine provenance", set(genuine["data_origin"]) == {"genuine"})
    check("feature schema is complete", all(name in genuine for name in ETA_FEATURE_COLUMNS))

    result = train_eta_model(genuine)
    check("trainer accepts sufficient genuine history", result.trained, result.message)
    check("trainer identifies genuine source", result.data_source == "genuine", result.data_source)
    check("configured 3-route threshold is met", result.distinct_routes == 3, str(result.distinct_routes))
    check("chronological split creates train rows", result.n_train == 72, str(result.n_train))
    check("chronological split creates newest holdout", result.n_test == 18, str(result.n_test))
    check("MAE is finite", result.metrics["mae"] >= 0, str(result.metrics["mae"]))
    check(
        "model beats operator ETA baseline",
        result.metrics["mae"] < result.metrics["operator_baseline_mae"],
        f"model={result.metrics['mae']:.2f}, baseline={result.metrics['operator_baseline_mae']:.2f}",
    )
    check("quality gate passes only with evidence", result.quality_ready, result.message)

    route_encodings = build_route_encodings(genuine)
    check("route encodings are persisted deterministically", len(route_encodings) == 3, str(route_encodings))

    with TemporaryDirectory() as tmp:
        directory = Path(tmp)
        paths = {
            "dir": directory,
            "model": directory / "eta.pkl",
            "features": directory / "features.json",
            "report": directory / "report.txt",
            "state": directory / "state.json",
        }
        save_eta_model(
            result,
            paths,
            route_metadata=build_route_metadata(genuine),
            route_encodings=route_encodings,
            bus_encodings=build_bus_encodings(genuine),
        )
        save_state(result, paths)
        state = json.loads(paths["state"].read_text(encoding="utf-8"))
        features = json.loads(paths["features"].read_text(encoding="utf-8"))
        check("saved state records genuine source", state["training_source"] == "genuine")
        check("saved state records quality gate", state["quality_ready"] is True)
        check("saved state records chronological split", state["split_strategy"] == "chronological_80_20")
        check("saved feature order matches runtime contract", features["features"] == ETA_FEATURE_COLUMNS)
        check("saved route encodings match trainer", features["route_encodings"] == route_encodings)

    insufficient = train_eta_model(make_dataset(rows=30))
    check("record threshold blocks small datasets", not insufficient.trained, insufficient.message)

    insufficient_routes = train_eta_model(make_dataset(rows=90, routes=2))
    check("route threshold blocks narrow datasets", not insufficient_routes.trained, insufficient_routes.message)

    synthetic = train_eta_model(make_dataset(origin="demo"))
    check("demo source remains explicitly synthetic", synthetic.data_source == "synthetic", synthetic.data_source)
    check("demo model may train for development", synthetic.trained, synthetic.message)

    print("ETA training pipeline PASS")


if __name__ == "__main__":
    main()
