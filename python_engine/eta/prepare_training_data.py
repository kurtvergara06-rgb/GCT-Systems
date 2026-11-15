"""CLI: extract verified genuine ETA training data from the Laravel database.

Only GPS rows linked to batch uploads explicitly classified as ``genuine`` are
written to the ETA training CSV. Unclassified historical rows and demo/sample
rows are intentionally excluded until their provenance is verified.
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
        logger.info("Querying database for verified genuine completed GPS trips...")
        summary = write_training_csv(db)
        dataset = summary["dataset"]

        print("\n=== ETA training data summary ===")
        print(f"Rows:                {summary['rows']}")
        print(f"Distinct routes:     {summary['distinct_routes']}")
        print(f"Distinct buses:      {summary['distinct_buses']}")
        print(f"Data origins:        {', '.join(summary['data_origins']) or 'none'}")
        print(
            f"Target range (min):  {summary['target_min']:.1f} - {summary['target_max']:.1f} "
            f"(mean {summary['target_mean']:.1f})"
        )
        print(f"Features:            {', '.join(ETA_FEATURE_COLUMNS)}")
        print(f"Target:              {ETA_TARGET}")
        print(f"Output CSV:          {summary['csv_path']}")

        if dataset.empty:
            print("\nMODEL NOT READY: no verified genuine ETA training rows were produced.")
            return 1
        print("\nDone.")
        return 0
    finally:
        db.close()


if __name__ == "__main__":
    sys.exit(main())
