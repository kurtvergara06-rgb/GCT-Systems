"""Bounded 10,000-record NLP smoke benchmark.

Run from python_engine: python tests/benchmark_gps_nlp_10k.py
Runs in-process (not network): validates chunk bounds and reports time and peak
resident process memory. Repeat against live Render separately.
"""
from __future__ import annotations

import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from NLP.router import GpsEnrichmentBatch, GpsEnrichmentItem, enrich_gps_batch


def resident_peak_mb() -> float | None:
    try:
        import resource
        peak = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss
        # Linux ru_maxrss is KiB; macOS returns bytes.
        return round(peak / (1024 * 1024 if sys.platform == "darwin" else 1024), 1)
    except (ImportError, OSError):
        return None


def run(total: int = 10_000, chunk_size: int = 25) -> None:
    assert total == 10_000 and 1 <= chunk_size <= 25
    os.environ["GCT_NLP_ENRICH_TOKEN"] = "benchmark-only-local"
    os.environ["GCT_ENABLE_SPACY_NLP"] = "false"
    began = time.perf_counter()
    analyzed = 0
    requests = 0

    for start in range(0, total, chunk_size):
        end = min(total, start + chunk_size)
        payload = GpsEnrichmentBatch(records=[
            GpsEnrichmentItem(
                id=record_id + 1,
                description="Engine activity interval; idling reported",
                bus_no=f"BUS{record_id % 50:04}",
                grouping="Route A",
                total_minutes=60,
                in_motion_minutes=45,
                idling_minutes=15,
                mileage_km=20.0,
                engine_hours=1.0,
            ) for record_id in range(start, end)
        ])
        result = enrich_gps_batch(payload, x_gct_nlp_token="benchmark-only-local")
        rows = result["records"]
        assert result["success"] and len(rows) == end - start
        assert all(row["success"] and row["trained_model"] is False for row in rows)
        analyzed += len(rows)
        requests += 1

    assert analyzed == total
    elapsed = time.perf_counter() - began
    print(f"records={analyzed} chunks={requests} seconds={elapsed:.2f}")
    print(f"records_per_second={analyzed / max(elapsed, 1e-9):.2f}")
    print(f"peak_resident_mb={resident_peak_mb()} (includes this Python process only)")
    print("NOTE: This is an in-process smoke benchmark, not a Render RAM guarantee.")


if __name__ == "__main__":
    run()
