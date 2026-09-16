"""CLI: Extract real historical ETA training data from the Laravel database.

Usage:
    python -m eta.prepare_training_data

Queries the Laravel MySQL database (read-only), builds the leakage-safe
feature matrix labeled with REAL trip duration, and writes:
    training_data/eta_trip_duration_training.csv

No fake or invented samples are every generated.
"""

import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from eta.training_data import ETA_FEATURE_COLUMNS, ETA_TARGET, write_training_csv  # noqa: E402
from operation_ai.ml.database import DbConnection  # noqa: E402

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_eta_data")


def main() -> int:
    db = DbConnection()
    try:
        logger.info("Querying database for real completed GPS trips...")
        summary = write_training_csv(db)

        dataset = summary["dataset"]
        print("\n=== ETA training data summary ===")
        print(f"Rows:                {summary['rows']}")
        print(f"Distinct routes:     {summary['distinct_routes']}")
        print(f"Distinct buses:      {summary['distinct_buses']}")
        print(f"Target range (min):  {summary['target_min']:.1f} - {summary['target_max']:.1f}"
              f" (mean {summary['target_mean']:.1f})")
        print(f"Features:            {', '.join(ETA_FEATURE_COLUMNS)}")
        print(f"Target:              {ETA_TARGET}")
        print(f"Output CSV:          {summary['csv_path']}")

        if dataset.empty:
            print("\nERROR: no usable training rows were produced.")
            return 1
        print("\nDone.")
        return 0
    finally:
        db.close()


if __name__ == "__main__":
    sys.exit(main())