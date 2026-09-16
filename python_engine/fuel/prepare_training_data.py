"""CLI: Extract real historical fuel training data from the Laravel database.

Usage:
    python -m fuel.prepare_training_data

Queries the Laravel MySQL database (read-only), builds the leakage-safe
feature matrix labeled with REAL fuel consumed (liters), and writes:
    training_data/fuel_consumption_training.csv

No fake or invented samples are ever generated.
"""

import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from fuel.training_data import FUEL_FEATURE_COLUMNS, FUEL_TARGET, write_training_csv  # noqa: E402
from operation_ai.ml.database import DbConnection  # noqa: E402

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_fuel_data")


def main() -> int:
    db = DbConnection()
    try:
        logger.info("Querying database for real fuel reports linked to GPS trips...")
        summary = write_training_csv(db)

        dataset = summary["dataset"]
        print("\n=== Fuel training data summary ===")
        print(f"Rows:                {summary['rows']}")
        print(f"Distinct routes:     {summary['distinct_routes']}")
        print(f"Distinct buses:      {summary['distinct_buses']}")
        print(f"Target range (liters): {summary['target_min']:.2f} - {summary['target_max']:.2f}"
              f" (mean {summary['target_mean']:.2f})")
        print(f"Features:            {', '.join(FUEL_FEATURE_COLUMNS)}")
        print(f"Target:              {FUEL_TARGET} (liters)")
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