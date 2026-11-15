"""Fast regression checks for required GCT NLP modules.

Run from ``python_engine`` with::

    python -m NLP.test_required_modules
"""

from __future__ import annotations

import os
import tempfile

from NLP.anomaly_detector import anomaly_details
from NLP.ingestion import approve_record, list_staged, promoted_records, stage_record
from NLP.ner_extractor import extract_entities
from NLP.readiness import assert_required_modules
from NLP.router import router as nlp_router
from NLP.severity_ner_predictor import predict_record as predict_ner_severity
from NLP.severity_predictor import predict_record as predict_severity


def check(name: str, condition: bool) -> None:
    if not condition:
        raise AssertionError(name)
    print(f"PASS: {name}")


def run() -> None:
    with tempfile.TemporaryDirectory() as temp_dir:
        previous = os.environ.get("NLP_INGESTION_DIR")
        os.environ["NLP_INGESTION_DIR"] = temp_dir
        try:
            status = assert_required_modules()
            check("all required modules report ready", status["ready"] is True)

            api_paths = {route.path for route in nlp_router.routes}
            expected_paths = {
                "/status",
                "/ingestion/staged",
                "/ingestion/promoted",
                "/ingestion/{staged_id}/approve",
                "/ingestion/{staged_id}/reject",
            }
            check(
                "required NLP readiness/review API routes are registered",
                expected_paths.issubset(api_paths),
            )

            text = (
                "BUS-015 was delayed by heavy traffic from Talisay to SM Seaside. "
                "The bus later suffered an engine failure and breakdown."
            )
            entities = extract_entities(text)
            event_labels = {
                event["label"]
                for event in entities["events"]
            }
            check("NER extracts BUS-015", "BUS-015" in entities["bus"])
            check("NER extracts delay event", "DELAY_EVENT" in event_labels)
            check("NER extracts traffic event", "TRAFFIC_EVENT" in event_labels)
            check("NER extracts engine issue", "ENGINE_ISSUE" in event_labels)
            check("NER extracts breakdown event", "BREAKDOWN_EVENT" in event_labels)

            record = {
                "bus_no": "BUS-015",
                "grouping": "Talisay - SM Seaside",
                "description": "Engine failure caused a breakdown and long delay in traffic.",
                "duration_minutes": 95,
                "total_minutes": 95,
                "idling_minutes": 45,
                "mileage_km": 28,
            }
            severity = predict_severity(record)
            check("base severity identifies high-impact record", severity["label"] == "High")
            check("base severity is truthfully labelled rule-based", severity["source"] == "operational_rules")

            ner_severity = predict_ner_severity(record)
            check("NER severity identifies high-impact record", ner_severity["label"] == "High")
            check("NER severity exposes extracted entities", isinstance(ner_severity["entities"], dict))

            anomaly = anomaly_details({
                "duration_minutes": 60,
                "total_minutes": 60,
                "in_motion_minutes": 30,
                "idling_minutes": 90,
                "mileage_km": 10,
                "engine_hours": 1,
            })
            check("anomaly detector catches impossible idle time", anomaly["is_anomaly"] is True)
            check("anomaly includes explanatory signal", "idling_time_exceeds_total" in anomaly["signals"])

            staged = stage_record(record, "GPS Report")
            check("ingestion stages a pending record", staged["status"] == "pending")
            pending = list_staged("pending")
            check("staged record is queryable", len(pending) == 1)

            approved = approve_record(
                staged["id"],
                labels={"severity": "High"},
                reviewer="ci-test",
            )
            check("review flow approves staged record", approved["status"] == "approved")
            check("approved record leaves pending queue", list_staged("pending") == [])
            check("approved record is promoted", len(promoted_records()) == 1)
        finally:
            if previous is None:
                os.environ.pop("NLP_INGESTION_DIR", None)
            else:
                os.environ["NLP_INGESTION_DIR"] = previous

    print("\nRequired NLP module validation PASS")


if __name__ == "__main__":
    run()
