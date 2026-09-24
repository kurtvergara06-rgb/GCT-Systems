"""CLI: Build Delay Model #3 training data for sample, demo, or genuine modes."""

import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from delay.config import data_source, disclaimers, training_data_paths  # noqa: E402
from delay.demo_data import build_demo_features, load_demo_csv  # noqa: E402
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
    return df.loc[:, ~df.columns.duplicated()].copy()


def _prepare_exported(source: str, paths) -> int:
    if source == "genuine":
        try:
            wide, _ = load_training_data(paths["csv"])
        except GenuineDataNotReady as exc:
            _print_divider("DELAY MODEL NOT READY FOR GENUINE TRAINING")
            print(exc.message)
            print(f"Export path: {exc.report.get('export_path', 'n/a')}")
            print(f"Matched trips available: {exc.report.get('matched_trips', 0)}")
            print("\nRun `php artisan delay:export-genuine`, then re-run this command.")
            return 2
        label = "GENUINE GCT dataset"
    else:
        try:
            wide = build_demo_features(load_demo_csv(paths["csv"]))
        except (FileNotFoundError, ValueError) as exc:
            _print_divider("DELAY DEMO DATA NOT READY")
            print(str(exc))
            print("\nRun `php artisan db:seed --class=ClientDemoDataSeeder` and "
                  "`php artisan delay:export-demo`, then re-run this command.")
            return 2
        label = "FRONTEND DEMO / SYNTHETIC dataset"

    report = readiness_report(wide)
    _print_divider(f"Delay {label} report")
    print(f"DISCLAIMER: {disclaimers()[source]}")
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
        _print_divider(f"DELAY {source.upper()} MODEL NOT READY")
        print("The exported history is too small for the configured readiness gate.")
        for field, detail in report["thresholds"].items():
            if not detail["passed"]:
                print(f"  - {field}: {detail['actual']} < {detail['threshold']}")
        return 2

    df = _normalize_feature_frame(build_dataset(wide))
    write_features_csv(df, paths["features_csv"])
    print(f"\nFeature matrix written: {paths['features_csv']}")
    print(f"Feature rows:          {len(df)}")
    print(f"Features ({len(DLY_FEATURE_COLUMNS)}): {', '.join(DLY_FEATURE_COLUMNS)}")
    print(f"Target:                {DLY_TARGET} (never an input feature)")
    print(f"Source:                {source}")
    print("\nNext: `python -m delay.train_model`.")
    return 0


def main() -> int:
    source = data_source()
    paths = training_data_paths()

    if source in {"genuine", "demo"}:
        return _prepare_exported(source, paths)

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
        return 1

    wide = _normalize_feature_frame(build_dataset(df))
    write_features_csv(wide, paths["features_csv"])
    print(f"\nFeature matrix written: {paths['features_csv']}")
    print(f"Feature rows:          {len(wide)}")
    print(f"Features ({len(DLY_FEATURE_COLUMNS)}): {', '.join(DLY_FEATURE_COLUMNS)}")
    print(f"Target:                {DLY_TARGET} (never an input feature)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
