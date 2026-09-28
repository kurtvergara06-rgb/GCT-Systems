"""Safety-filter tests for ETA Model #1 training extraction.

Run from python_engine:
    python -m eta.test_eta_safety_filters
"""

from __future__ import annotations

import pandas as pd

from eta.training_data import build_dataset, fetch_trip_outcomes


def check(label: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{label}: {detail}")
    print(f"PASS: {label}" + (f" ({detail})" if detail else ""))


class FakeDb:
    def __init__(self, rows):
        self.rows = rows
        self.sql = ""

    def query_df(self, sql, params=None):
        self.sql = sql
        return pd.DataFrame(self.rows)


def main() -> None:
    rows = [
        {
            "gps_record_id": 1,
            "bus_no": "GCT-101",
            "route": "Plant A - Plant B",
            "beginning_at": "2026-09-20 08:00:00",
            "duration_minutes": 46,
            "shift": "Morning",
            "departure_time": "08:00:00",
            "distance_km": 20.5,
            "route_estimated_time_minutes": 50,
            "data_origin": "genuine",
        },
        {
            "gps_record_id": 2,
            "bus_no": "GCT-101",
            "route": "Plant A - Plant B",
            "beginning_at": "2026-09-21 08:00:00",
            "duration_minutes": 840,
            "shift": "Morning",
            "departure_time": "08:00:00",
            "distance_km": 20.5,
            "route_estimated_time_minutes": 50,
            "data_origin": "genuine",
        },
        {
            "gps_record_id": 3,
            "bus_no": "GCT-102",
            "route": "Demo Route",
            "beginning_at": "2026-09-22 08:00:00",
            "duration_minutes": 40,
            "shift": "Morning",
            "departure_time": "08:00:00",
            "distance_km": 10.0,
            "route_estimated_time_minutes": 45,
            "data_origin": "demo",
        },
    ]
    fake = FakeDb(rows)
    fetch_trip_outcomes(fake)
    sql = " ".join(fake.sql.lower().split())

    check("training query is read-only", sql.startswith("select"))
    check("duration upper bound enforced", "g.duration_minutes <= 720" in sql)
    check("batch provenance joined", "inner join batch_uploads bu" in sql)
    check("only genuine batches admitted", "bu.data_origin" in sql and "= 'genuine'" in sql)
    check("training query never deletes GPS records", "delete" not in sql and "update" not in sql)

    # Emulate the SQL predicates to verify intended eligibility without needing
    # a live database in this unit test.
    raw = pd.DataFrame(rows)
    eligible = raw[
        raw["duration_minutes"].notna()
        & (raw["duration_minutes"] > 0)
        & (raw["duration_minutes"] <= 720)
        & (raw["data_origin"].str.lower() == "genuine")
    ]
    dataset = build_dataset(eligible)
    ids = set(dataset["gps_record_id"].astype(int))
    check("valid genuine trip retained", ids == {1}, str(ids))
    check("long activity-period row excluded", 2 not in ids)
    check("demo row excluded", 3 not in ids)
    check("retained row keeps genuine provenance", set(dataset["data_origin"]) == {"genuine"})

    print("ETA safety filters PASS")


if __name__ == "__main__":
    main()
