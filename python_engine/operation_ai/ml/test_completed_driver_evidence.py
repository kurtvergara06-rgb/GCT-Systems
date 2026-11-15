"""Regression checks for the read-only DDR completed-trip evidence pipeline."""

import unittest
from unittest.mock import Mock

import pandas as pd

from .database import fetch_completed_driver_trip_evidence


class CompletedDriverEvidenceTest(unittest.TestCase):
    def test_requires_completed_trip_and_matching_driver_assignment(self):
        db = Mock()
        db.query_df.return_value = pd.DataFrame()
        result = fetch_completed_driver_trip_evidence(db)
        self.assertTrue(result.empty)
        sql = db.query_df.call_args.args[0]
        self.assertIn("t.status = 'Completed'", sql)
        self.assertIn("a.driver_id = d.driver_id", sql)
        self.assertIn("a.trip_schedule_id = t.id", sql)
        self.assertIn("d.unscheduled_reason IS NULL", sql)
        self.assertIn("daily_driver_report_trip_entries", sql)
        self.assertIn("UNION ALL", sql)


if __name__ == "__main__":
    unittest.main()
