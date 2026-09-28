"""Read-only audit for the five GCT machine-learning capabilities.

The audit checks live status contracts, data provenance, production flags,
demo-model metrics, and Auto Scheduling component readiness. It never trains a
model, modifies the database, or deletes artifacts.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


ENGINE_ROOT = Path(__file__).resolve().parent
SYNTHETIC_SOURCES = {"sample", "synthetic", "demo", "generated", "development"}
DEVELOPMENT_MODES = {"development", "dev", "demo", "local", "testing", "test"}

ENDPOINTS = {
    "eta": ("ETA Model", "/eta/status"),
    "fuel": ("Fuel Model", "/fuel/status"),
    "delay": ("Delay Model", "/delay/status"),
    "inventory": ("Inventory Model", "/inventory/status"),
    "scheduling": (
        "Auto Scheduling",
        "/operation/auto-scheduling/ai/training/status",
    ),
}

METRIC_FILES = {
    "delay": ENGINE_ROOT / "delay" / "models" / "demo_delay_arrival_state.json",
    "inventory": (
        ENGINE_ROOT
        / "inventory"
        / "models"
        / "demo_inventory_demand_state.json"
    ),
}


def fetch_json(url: str, timeout: float) -> dict[str, Any]:
    request = Request(url, headers={"Accept": "application/json"})
    with urlopen(request, timeout=timeout) as response:  # noqa: S310 - local/configured URL
        payload = json.loads(response.read().decode("utf-8"))
    if not isinstance(payload, dict):
        raise ValueError("response is not a JSON object")
    return payload


def source_name(payload: dict[str, Any]) -> str:
    return str(
        payload.get("data_source")
        or payload.get("model_source")
        or payload.get("source")
        or "unknown"
    ).strip().lower()


def audit_standard(key: str, name: str, payload: dict[str, Any]) -> dict[str, Any]:
    reported_ready = payload.get("model_ready") is True
    source = source_name(payload)
    production = payload.get("is_production_model") is True
    runtime = str(payload.get("runtime_mode") or "unknown").strip().lower()

    if reported_ready and source == "genuine" and production:
        state = "Ready"
    elif reported_ready and source in SYNTHETIC_SOURCES and runtime in DEVELOPMENT_MODES:
        state = "Simulation Ready"
    elif reported_ready and source in SYNTHETIC_SOURCES:
        state = "Development Only"
    else:
        state = "MODEL NOT READY"

    return {
        "key": key,
        "name": name,
        "state": state,
        "source": source,
        "records": int(payload.get("sample_count") or payload.get("training_record_count") or 0),
        "production": production,
        "runtime_mode": runtime,
        "reason": str(payload.get("reason") or payload.get("message") or ""),
    }


def audit_scheduling(name: str, payload: dict[str, Any]) -> dict[str, Any]:
    bus_ready = payload.get("bus_model_ready") is True
    driver_ready = payload.get("driver_model_ready") is True
    state = "Ready" if bus_ready and driver_ready else "Partial ML" if bus_ready or driver_ready else "MODEL NOT READY"
    return {
        "key": "scheduling",
        "name": name,
        "state": state,
        "source": source_name(payload),
        "records": int(payload.get("bus_sample_count") or 0) + int(payload.get("driver_sample_count") or 0),
        "production": bus_ready and driver_ready,
        "runtime_mode": "n/a",
        "bus_ready": bus_ready,
        "driver_ready": driver_ready,
        "bus_records": int(payload.get("bus_sample_count") or 0),
        "driver_records": int(payload.get("driver_sample_count") or 0),
        "reason": " ".join(
            filter(
                None,
                [
                    str(payload.get("bus_reason") or ""),
                    str(payload.get("driver_reason") or ""),
                ],
            )
        ),
    }


def load_demo_metrics(key: str) -> dict[str, Any] | None:
    path = METRIC_FILES.get(key)
    if path is None or not path.is_file():
        return None
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError, UnicodeError):
        return None
    metrics = payload.get("metrics")
    return metrics if isinstance(metrics, dict) else None


def findings_for(models: list[dict[str, Any]], strict_production: bool) -> list[dict[str, str]]:
    findings: list[dict[str, str]] = []

    for model in models:
        if model["source"] in SYNTHETIC_SOURCES and model["production"]:
            findings.append({
                "severity": "HIGH",
                "model": model["name"],
                "message": "Synthetic/sample provenance is incorrectly marked as production.",
            })

        if strict_production and model["state"] != "Ready":
            findings.append({
                "severity": "HIGH",
                "model": model["name"],
                "message": f"Strict production audit requires Ready; current state is {model['state']}.",
            })

        if model["key"] in METRIC_FILES and model["source"] in SYNTHETIC_SOURCES:
            metrics = load_demo_metrics(model["key"])
            if metrics:
                findings.append({
                    "severity": "INFO",
                    "model": model["name"],
                    "message": (
                        "Demo-only held-out metrics: "
                        f"MAE={float(metrics.get('mae', 0)):.3f}, "
                        f"RMSE={float(metrics.get('rmse', 0)):.3f}, "
                        f"R2={float(metrics.get('r2', 0)):.4f}. "
                        "Revalidate after genuine-data retraining."
                    ),
                })

    scheduling = next((model for model in models if model["key"] == "scheduling"), None)
    if scheduling and not scheduling.get("driver_ready", False):
        findings.append({
            "severity": "MEDIUM",
            "model": scheduling["name"],
            "message": (
                f"Driver model is not ready ({scheduling.get('driver_records', 0)} distinct drivers reported). "
                "Keep the reliability fallback until at least 10 genuine drivers have sufficient history."
            ),
        })

    return findings


def render_table(models: list[dict[str, Any]]) -> None:
    headers = ("Model", "State", "Source", "Records", "Production")
    rows = [
        (
            model["name"],
            model["state"],
            model["source"],
            str(model["records"]),
            "yes" if model["production"] else "no",
        )
        for model in models
    ]
    widths = [max(len(headers[i]), *(len(row[i]) for row in rows)) for i in range(len(headers))]
    line = "  ".join(headers[i].ljust(widths[i]) for i in range(len(headers)))
    print(line)
    print("  ".join("-" * width for width in widths))
    for row in rows:
        print("  ".join(row[i].ljust(widths[i]) for i in range(len(headers))))


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:8000")
    parser.add_argument("--timeout", type=float, default=5.0)
    parser.add_argument("--json", action="store_true", dest="as_json")
    parser.add_argument("--strict-production", action="store_true")
    args = parser.parse_args()

    base_url = args.base_url.rstrip("/")
    models: list[dict[str, Any]] = []
    endpoint_errors: list[dict[str, str]] = []

    for key, (name, path) in ENDPOINTS.items():
        try:
            payload = fetch_json(base_url + path, args.timeout)
            if payload.get("success") is not True:
                raise ValueError("success=true was not reported")
            models.append(
                audit_scheduling(name, payload)
                if key == "scheduling"
                else audit_standard(key, name, payload)
            )
        except (HTTPError, URLError, TimeoutError, ValueError, json.JSONDecodeError) as error:
            endpoint_errors.append({"model": name, "error": str(error)})

    findings = findings_for(models, args.strict_production)
    result = {"models": models, "findings": findings, "endpoint_errors": endpoint_errors}

    if args.as_json:
        print(json.dumps(result, indent=2))
    else:
        if models:
            render_table(models)
        if findings:
            print("\nFindings:")
            for finding in findings:
                print(f"[{finding['severity']}] {finding['model']}: {finding['message']}")
        if endpoint_errors:
            print("\nEndpoint errors:", file=sys.stderr)
            for error in endpoint_errors:
                print(f"- {error['model']}: {error['error']}", file=sys.stderr)

    if endpoint_errors:
        return 2
    if any(finding["severity"] == "HIGH" for finding in findings):
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
