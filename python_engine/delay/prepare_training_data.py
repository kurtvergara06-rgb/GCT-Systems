"""CLI: Build the delay training datasets (SAMPLE or GENUINE).

Usage:
    python -m delay.prepare_training_data
    DELAY_DATA_SOURCE=genuine python -m delay.prepare_training_data

SAMPLE mode (default):
    Regenerates the deterministic SAMPLE CSV, validates it (structural +
    consistency checks), feature-engineers the leakage-safe feature matrix and
    writes ``training_data/delay/sample_delay_training_features.csv``.

GENUINE mode (opt-in):
    Reads the Laravel-exported genuine matched DDR dataset
    (``php artisan delay:export-genuine`` -> genuine_delay_training.csv),
    derives the feature matrix, and applies the data-sufficiency readiness
    gate. When the gate fails, prints the "NOT READY FOR GENUINE TRAINING"
    report with per-threshold counts and exits with a non-zero code - it NEVER
    falls back to the sample dataset and no model artifacts are produced.
"""

import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from delay.config import (  # noqa: E402
    disclaimers,
    is_genuine,
    training_data_paths,
)
from delay.sample_generator import generate_sample_csv  # noqa: E402
from delay.training_data import (  # noqa: E402
    DLY_FEATURE_COLUMNS,
    DLY_TARGET,
    GenuineDataNotReady,
    build_dataset,
    check_readiness_thresholds,
    load_sample_csv,
    load_training_data,
    readiness_report,
    validate_dataset,
    write_features_csv,
)

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("prepare_delay_data")


def _print_divider(title: str) -> None:
    print("\n" + "=" * 68)
    print(title)
    print("=" * 68)


def _normalize_feature_frame(df):
    """Remove duplicate trace columns before persisting the model matrix."""
    return df.loc[:, ~df.columns.duplicated()].copy()


def main() -> int:
    source = "genuine" if is_genuine() else "sample"
    paths = training_data_paths()

    if source == "genuine":
        try:
            wide, _source = load_training_data(paths["csv"])
        except GenuineDataNotReady as exc:
            _print_divider("DELAY MODEL NOT READY FOR GENUINE TRAINING")
            print(exc.message)
            print(f"Export path: {exc.report.get('export_path', 'n/a')}")
            print(f"Matched trips available: {exc.report.get('matched_trips', 0)}")
            print("\nRun `php artisan delay:export-genuine` inside the Laravel "
                  "app, then re-run this command.")
            return 2

        report = readiness_report(wide)
        _print_divider("Delay GENUINE dataset report")
        print(f"DISCLAIMER: {disclaimers()['genuine']}")
        print(f"  matched_trips         : {report['matched_trips']}")
        print(f"  routes                : {report['routes']}")
        print(f"  buses                 : {report['buses']}")
        print(f"  drivers               : {report['drivers']}")
        print(f"  date span             : {report['date_min']} .. {report['date_max']}")
        print(f"  history weeks         : {report['weeks']}")
        for field, detail in report["thresholds"].items():
            status = "PASS" if detail["passed"] else "FAIL"
            print(
                f"  threshold {field:<12}: {detail['actual']} / {detail['threshold']} "
                f"({status})"
            )

        if not report["ready"]:
            _print_divider("DELAY MODEL NOT READY FOR GENUINE TRAINING")
            print("The matched genuine history is too small for a reliable model. "
                  "No feature matrix and NO model artifacts were produced.")
            print("\nMissing thresholds:")
            for field, detail in report["thresholds"].items():
                if not detail["passed"]:
                    print(f"  - {field}: {detail['actual']} < {detail['threshold']}")
            print("\nMinimum requirements (DELAY_MODEL_READINESS.md):")
            print("  50+ matched trips, 3+ routes, 5+ buses, 5+ drivers, 4+ weeks.")
            return 2

        df = _normalize_feature_frame(build_dataset(wide))
        write_features_csv(df, paths["features_csv"])
        print(f"\nFeature matrix written: {paths['features_csv']}")
        print(f"Feature rows:          {len(df)}")
        print(f"Features ({len(DLY_FEATURE_COLUMNS)}): {', '.join(DLY_FEATURE_COLUMNS)}")
        print(f"Target:                {DLY_TARGET} (never an input feature)")
        print("\nNext: `python -m delay.train_model`.")
        return 0

    # ---------------- SAMPLE / DEMONSTRATION mode (default) --------------
    # SAMPLE data is deterministic, so always regenerate it from the current
    # generator. This prevents a stale checked-in CSV from silently preserving
    # an older, poorly specified target distribution.
    print("Regenerating deterministic SAMPLE data...")
    generate_sample_csv(paths["csv"])

    df = load_sample_csv(paths["csv"])
    valid, errors, report = validate_dataset(df)

    _print_divider("Delay SAMPLE dataset report")
    print(f"DISCLAIMER: {disclaimers()['sample']}")
    for key in [
        "total_rows", "routes", "buses", "drivers", "date_min", "date_max",
        "distinct_dates", "span_days", "distinct_weeks",
        "arrival_delay_min", "arrival_delay_max", "arrival_delay_mean",
        "on_time_pct", "delay_distro",
    ]:
        print(f"  {key:<22}: {report.get(key)}")

    ok_thresholds, threshold_issues = check_readiness_thresholds(df)
    print(f"\nReadiness thresholds: {'PASS' if ok_thresholds else 'FAIL'}")
    for issue in threshold_issues:
        print(f"  - {issue}")

    if not valid:
        print("\nValidation FAILED:")
        for error in errors:
            print(f"  - {error}")
        print("\nSkipping feature matrix build.")
        return 1

    wide = _normalize_feature_frame(build_dataset(df))
    write_features_csv(wide, paths["features_csv"])
    print(f"\nFeature matrix written: {paths['features_csv']}")
    print(f"Feature rows:          {len(wide)}")
    print(f"Features ({len(DLY_FEATURE_COLUMNS)}): {', '.join(DLY_FEATURE_COLUMNS)}")
    print(f"Target:                {DLY_TARGET} (never an input feature)")
    print("\nDone.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
