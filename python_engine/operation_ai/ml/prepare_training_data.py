"""CLI: Extract real historical training data from the Laravel database.

Usage:
    python -m operation_ai.ml.prepare_training_data

Queries the Laravel MySQL database (read-only) and writes two CSV datasets:
    python_engine/training_data/scheduling_bus_training.csv
    python_engine/training_data/scheduling_driver_training.csv

Labels are derived ONLY from real database fields (GPS trip outcomes and
driver attendance reliability). No fake or rule-derived labels are used.
"""

import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from operation_ai.ml.config import training_data_paths  # noqa: E402
from operation_ai.ml.database import DbConnection, data_availability  # noqa: E402
from operation_ai.ml.training_data import build_datasets  # noqa: E402

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_training_data")


def main() -> int:
    db = DbConnection()
    try:
        logger.info("Querying database for real historical data...")
        availability = data_availability(db)
        logger.info("Data availability: %s", availability)

        out_dir = training_data_paths()["dir"]
        datasets = build_datasets(db, out_dir)

        bus_df = datasets.get("bus", __import__("pandas").DataFrame())
        driver_df = datasets.get("driver", __import__("pandas").DataFrame())

        print("\n=== Training data summary ===")
        print(f"Bus samples:     {len(bus_df)}")
        print(f"Driver samples:  {len(driver_df)}")
        print(f"Output dir:      {out_dir}")
        print("\nDone.")
        return 0
    finally:
        db.close()


if __name__ == "__main__":
    sys.exit(main())
