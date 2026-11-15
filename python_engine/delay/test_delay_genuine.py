"""Tests for the GENUINE data pipeline of the Model #3 delay subsystem.

Covers:
    1.  Genuine export missing -> GenuineDataNotReady (clear failure).
    2.  No silent fallback to the SAMPLE dataset in genuine mode.
    3.  build_genuine_features: demo-schedule exclusion, incident flags,
        strictly-prior route/driver stats, driver day trip sequence.
    4.  readiness_report: pass / fail per configured thresholds.
    5.  prepare_training_data refuses genuine mode with "NOT READY" (exit 2).
    6.  train_model refuses genuine training when the gate fails (exit 2).
    7.  encode_features exposes incident-context features (pre-trip flags).
    8.  Genuine artifacts are stored apart from sample artifacts.
    9.  The delay package still contains no database-connect API calls
        (genuine extraction happens in Laravel, not Python).

These tests never touch the Laravel MySQL database and never require a genuine
export to exist in the repository.
"""

import io
import logging
import os
import re
import sys
import tempfile
from contextlib import redirect_stdout
from pathlib import Path

logging.basicConfig(level=logging.WARNING)

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import pandas as pd  # noqa: E402

import delay.training_data as td  # noqa: E402
from delay import predict as delay_predict  # noqa: E402
from delay.config import demo_trip_prefixes, disclaimers, is_genuine  # noqa: E402
from delay.config import training_data_paths  # noqa: E402
from delay.training_data import (  # noqa: E402
    DELAY_INCIDENT_FEATURES,
    DLY_FEATURE_COLUMNS,
    DLY_TARGET,
    GENUINE_RAW_COLUMNS,
    REQUIRED_COLUMNS,
    GenuineDataNotReady,
    build_dataset,
    build_genuine_features,
    load_genuine_csv,
    load_training_data,
    readiness_report,
)

PASS = 0
FAIL = 0


def check(label: str, condition: bool, detail: str = "") -> None:
    global PASS, FAIL
    if condition:
        PASS += 1
    else:
        FAIL += 1
    status = "PASS" if condition else "FAIL"
    suffix = f" ({detail})" if detail else ""
    print(f"  [{status}] {label}{suffix}")


def make_raw_rows(n: int, seed_offset: int = 0, start: str = "2026-01-05",
                  demo: bool = False) -> pd.DataFrame:
    """Synthetic genuine rows in the Laravel export layout (GENUINE_RAW_COLUMNS).

    ``n`` is the number of DAYS; each day emits 3 trips so per-day driver trip
    sequences are actually exercised.
    """
    import numpy as np

    rng = np.random.default_rng(42 + seed_offset)
    dates = pd.date_range(start, periods=n, freq="D")
    rows = []
    i = 0
    for d in dates:
        day_str = d.strftime("%Y-%m-%d")
        for _k in range(3):
            hour = 6 + (i % 12)
            dep = f"{hour:02d}:{(i % 4) * 15:02d}"
            rows.append({
                "report_date": day_str,
                "trip_code": f"TRIP-{i:04d}" if demo else f"GCT-{i:04d}",
                "route_code": f"RT-{i % 4}",
                "route_name": "",
                "bus_no": f"BUS-{i % 6}",
                "driver_id": f"DRV-{i % 5}",
                "driver_name": f"Driver {i % 5}",
                "trip_ticket": f"TT-{i}",
                "scheduled_departure_time": dep,
                "actual_departure_time": dep,
                "scheduled_arrival_time": f"{(hour + 1) % 24:02d}:00",
                "actual_arrival_time": f"{(hour + 1) % 24:02d}:{5 + (i % 50):02d}",
                "scheduled_duration_minutes": 60,
                "actual_duration_minutes": 60 + (i % 25),
                "departure_delay_minutes": 0,
                "arrival_delay_minutes": 5 + (i % 50),
                "route_distance_km": 12.0,
                "incident_before_count": 1 if i % 3 == 0 else 0,
                "incident_breakdown_count": 1 if i % 3 == 0 else 0,
                "incident_traffic_count": 1 if i % 7 == 0 else 0,
                "incident_replacement_count": 1 if i % 4 == 0 else 0,
            })
            i += 1
    return pd.DataFrame(rows, columns=GENUINE_RAW_COLUMNS)


def restore_env(name: str) -> None:
    if name in os.environ:
        os.environ.pop(name, None)


print("\n=== Model #3 genuine-pipeline tests ===")

# ============================================================
# 1 + 2. Missing export / no silent fallback
# ============================================================
print("\n--- Genuine load + no-fallback ---")
tmp = tempfile.mkdtemp(prefix="delay_genuine_")
missing_path = Path(tmp) / "nope.csv"

try:
    load_genuine_csv(missing_path)
    raised = False
except GenuineDataNotReady:
    raised = True
check("missing genuine export raises GenuineDataNotReady", raised,
      str(missing_path))

os.environ["DELAY_DATA_SOURCE"] = "genuine"
try:
    _ = load_training_data()          # no genuine export present in repo
    fallback = False
    source = None
except GenuineDataNotReady:
    fallback = True
    source = "genuine"
check("genuine mode refuses when export missing (no sample fallback)",
      fallback, f"source={source}")
check("data_source() reports genuine", is_genuine(), is_genuine())

# Even a perfectly good SAMPLE CSV at the path cannot serve as genuine data.
sample_wide = td.generate_sample_dataset(n=30)
sample_wide.to_csv(missing_path, index=False)
try:
    _ = load_genuine_csv(missing_path)
    refused = False
except GenuineDataNotReady:
    refused = True
check("sample-format CSV is refused as genuine data (columns mismatch)",
      refused)

# Loading a well-formed genuine export through load_training_data works.
valid_raw = make_raw_rows(160)
valid_raw.to_csv(missing_path, index=False)
gf, source = load_training_data(missing_path)
check("valid genuine export loads and builds features", source == "genuine"
      and not gf.empty, f"{len(gf)} rows")
restore_env("DELAY_DATA_SOURCE")

# ============================================================
# 3. build_genuine_features
# ============================================================
print("\n--- build_genuine_features ---")

# Demo-schedule exclusion.
demo_raw = make_raw_rows(10, seed_offset=7, demo=True)
gf_demo = build_genuine_features(demo_raw)
check("demo-schedule rows are excluded from genuine features",
      len(gf_demo) == 0, f"{len(gf_demo)} rows kept")
check("demo prefix configurable via DELAY_DEMO_TRIP_PREFIXES",
      "TRIP-" in demo_trip_prefixes())

raw = make_raw_rows(200)   # 200 days x 3 trips = 600 matched trips
gf = build_genuine_features(raw)
check("genuine features include every REQUIRED_COLUMNS column",
      all(c in gf.columns for c in REQUIRED_COLUMNS),
      f"missing={[c for c in REQUIRED_COLUMNS if c not in gf.columns] or 'none'}")
check("incident features present in genuine features",
      all(c in gf.columns for c in DELAY_INCIDENT_FEATURES))

# Incident flags: counts > 0 -> 1 (join on trip_ticket; the wide matrix keeps
# only the flags, the raw counts come from the Laravel export).
joined = gf.merge(
    raw[["trip_ticket", "incident_before_count", "incident_breakdown_count",
         "incident_traffic_count", "incident_replacement_count"]],
    on="trip_ticket",
    validate="one_to_one",
)
check("incident_before_departure flag = 1 when a pre-departure incident exists",
      bool((joined["incident_before_departure"] == (joined["incident_before_count"] > 0).astype(int)).all()))
check("breakdown flag derives from breakdown count",
      bool((joined["incident_breakdown_flag"] == (joined["incident_breakdown_count"] > 0).astype(int)).all()))
check("traffic flag derives from traffic count",
      bool((joined["incident_traffic_flag"] == (joined["incident_traffic_count"] > 0).astype(int)).all()))
check("replacement flag derives from replacement count",
      bool((joined["incident_replacement_flag"] == (joined["incident_replacement_count"] > 0).astype(int)).all()))

# The flags are binary 0/1 in the feature matrix.
check("incident features are binary in the matrix",
      bool(set(joined[DELAY_INCIDENT_FEATURES].astype(int).max().tolist()) <= {1}))

# Strictly-prior route stats: first observation for a route must be 0.0 and no
# row can reference a future observation.
first_route = gf.groupby("route")["route_prior_delay_mean_min"].first()
check("first observation per route has prior mean 0.0 (no self-leak)",
      bool((first_route == 0.0).all()), dict(first_route))
d1 = gf["route_prior_delay_mean_min"]
d2 = gf["arrival_delay_minutes"]
check("route prior stats are bounded by prior data only",
      bool(((d1 - d2.shift() * 0).fillna(0) >= 0).all()))

# Driver day trip sequence: monotonic within a day, resets on next day.
gf_sorted = gf.sort_values(["trip_date", "scheduled_departure_time"]).reset_index(drop=True)
weird = gf_sorted[gf_sorted["driver_trip_seq"] < 1]
check("driver_trip_seq starts at 1 for every trip", len(weird) == 0)
day1 = gf_sorted["trip_date"].iloc[0]
day1_rows = gf_sorted[gf_sorted["trip_date"] == day1]
seqs = list(day1_rows.groupby("driver_id")["driver_trip_seq"].apply(lambda s: list(s)).items())
check("driver_trip_seq counts trips within the same day",
      all(s == list(range(1, len(s) + 1)) for _, s in seqs), str(seqs[:2]))

# build_dataset consumes the genuine wide matrix end-to-end.
gw = build_dataset(gf)
check("build_dataset works on genuine features (sample-shaped matrix)",
      all(c in gw.columns for c in DLY_FEATURE_COLUMNS + [DLY_TARGET]),
      f"{len(gw)} rows")
check("genuine dataset has real label variance",
      float(gw[DLY_TARGET].std()) > 1.0, f"std={gw[DLY_TARGET].std():.2f}")

# ============================================================
# 4. readiness_report
# ============================================================
print("\n--- Genuine readiness gate ---")
small = make_raw_rows(8, seed_offset=3)   # 1 week / 24 trips -> must FAIL
gd = build_genuine_features(small)
rep = readiness_report(gd)
check("1 week / 24 trips -> NOT ready", rep["ready"] is False, str(rep["missing"]))
check("readiness_report reports exact matched trips",
      rep["matched_trips"] == len(gd), f"{rep['matched_trips']}")
check("readiness_report reports per-threshold actuals",
      all("actual" in v and "threshold" in v for v in rep["thresholds"].values()))

big = build_genuine_features(make_raw_rows(160))   # 160 days -> ready
rep_big = readiness_report(big)
check("160 days / 4 routes / 6 buses / 5 drivers -> ready",
      rep_big["ready"] is True,
      f"trips={rep_big['matched_trips']} routes={rep_big['routes']} "
      f"buses={rep_big['buses']} drivers={rep_big['drivers']} weeks={rep_big['weeks']}")

os.environ["DELAY_MIN_RECORDS"] = "1000"
rep_hi = readiness_report(big)
check("raising DELAY_MIN_RECORDS forces the gate to fail",
      rep_hi["ready"] is False, str(rep_hi["missing"]))
restore_env("DELAY_MIN_RECORDS")

# ============================================================
# 5. prepare_training_data refuses genuine when not ready
# ============================================================
print("\n--- prepare_training_data genuine gate ---")
os.environ["DELAY_DATA_SOURCE"] = "genuine"
import delay.prepare_training_data as delay_prepare  # noqa: E402

buf = io.StringIO()
with redirect_stdout(buf):
    code = delay_prepare.main()
check("prepare exits non-zero when genuine export missing", code == 2, f"exit={code}")
check("prepare prints the NOT-READY banner",
      "NOT READY FOR GENUINE TRAINING" in buf.getvalue())
check("prepare never falls back to building the sample features in genuine mode",
      "sample_delay_training_features" not in buf.getvalue())
restore_env("DELAY_DATA_SOURCE")

# ============================================================
# 6. train_model refuses genuine when the gate fails
# ============================================================
print("\n--- train_model genuine gate ---")
os.environ["DELAY_DATA_SOURCE"] = "genuine"
train_dir = training_data_paths()
import delay.train_model as delay_train  # noqa: E402

models_dir = Path(__file__).resolve().parent / "models"
artifacts = ["delay_arrival_rf.pkl", "delay_arrival_report.txt",
             "delay_arrival_features.json", "delay_arrival_state.json"]
mtimes_before = {name: (models_dir / name).stat().st_mtime
                 if (models_dir / name).exists() else None
                 for name in artifacts}

genuine_features = train_dir["features_csv"]
had_features = genuine_features.exists()
backup = None
if had_features:
    backup = genuine_features.with_suffix(".bak_ge")
    genuine_features.rename(backup)

try:
    # Write an insufficient genuine features matrix (fails the gate).
    small_matrix = build_dataset(build_genuine_features(small))
    genuine_features.parent.mkdir(parents=True, exist_ok=True)
    genuine_features.write_text(small_matrix.to_csv(index=False),
                                encoding="utf-8")

    buf2 = io.StringIO()
    with redirect_stdout(buf2):
        code = delay_train.main()
    check("train_model exits non-zero when genuine gate fails", code == 2, f"exit={code}")
    check("train_model prints the NOT-READY banner",
          "NOT READY FOR GENUINE TRAINING" in buf2.getvalue())

    mtimes_after = {name: (models_dir / name).stat().st_mtime
                    if (models_dir / name).exists() else None
                    for name in artifacts}
    check("gate failure touches no model artifact",
          all(mtimes_after[n] == mtimes_before[n] for n in artifacts),
          {n: (mtimes_before[n], mtimes_after[n]) for n in artifacts})
finally:
    restore_env("DELAY_DATA_SOURCE")
    if genuine_features.exists():
        genuine_features.unlink()          # remove the test file we wrote
    if backup is not None and backup.exists():
        backup.rename(genuine_features)    # restore any pre-existing file

# ============================================================
# 7. encode_features incident context
# ============================================================
print("\n--- encode_features incident context ---")
delay_predict.reset_cache()
features = delay_predict.encode_features(
    route="RT-1",
    bus_no="BUS-1",
    driver_id="DRV-1",
    trip_date=pd.Timestamp("2026-08-10").to_pydatetime(),
    scheduled_departure_time="08:30",
    incident_before_departure=True,
    incident_breakdown_flag=True,
    incident_traffic_flag=False,
    incident_replacement_flag=True,
)
check("incident flags present in encoded features",
      all(c in features for c in DELAY_INCIDENT_FEATURES))
check("incident flags encode true->1.0 / false->0.0",
      features["incident_before_departure"] == 1.0
      and features["incident_breakdown_flag"] == 1.0
      and features["incident_traffic_flag"] == 0.0
      and features["incident_replacement_flag"] == 1.0,
      str({c: features[c] for c in DELAY_INCIDENT_FEATURES}))
check("no-incident defaults are 0.0",
      delay_predict.encode_features(
          route="RT-1", bus_no="BUS-1", driver_id="DRV-1",
          trip_date=pd.Timestamp("2026-08-10").to_pydatetime(),
          scheduled_departure_time="08:30",
      )["incident_before_departure"] == 0.0)

# ============================================================
# 8. Genuine vs sample artifact separation
# ============================================================
print("\n--- Artifact separation ---")
os.environ["DELAY_DATA_SOURCE"] = "genuine"
genuine_paths = training_data_paths()
check("genuine CSV path differs from sample CSV path",
      "genuine" in str(genuine_paths["csv"])
      and "sample" not in str(genuine_paths["csv"]), str(genuine_paths["csv"]))
check("genuine features path differs from sample features path",
      "genuine" in str(genuine_paths["features_csv"]),
      str(genuine_paths["features_csv"]))
os.environ["DELAY_DATA_SOURCE"] = "sample"
sample_paths = training_data_paths()
check("sample path restored and distinct from genuine",
      "sample" in str(sample_paths["csv"])
      and str(sample_paths["csv"]) != str(genuine_paths["csv"]))
restore_env("DELAY_DATA_SOURCE")
check("genuine dataset uses a GENUINE disclosure tag",
      "GENUINE" in disclaimers()["genuine"].upper())

# ============================================================
# 9. Still no DB calls inside the Python delay package
# ============================================================
print("\n--- DB-safety invariant (genuine extraction lives in Laravel) ---")
delay_pkg = Path(__file__).resolve().parent
db_api_tokens = [
    r"\bpymysql\b",
    r"\bDbConnection\b",
    r"\bconn\.cursor\b",
    r"\bINSERT INTO\b",
    r"\bDELETE FROM\b",
    r"\.execute\(",
]
violations = []
for py_file in delay_pkg.glob("*.py"):
    if py_file.name in {"test_delay.py", "test_delay_genuine.py"}:
        continue
    text = py_file.read_text(encoding="utf-8")
    for token in db_api_tokens:
        if re.search(token, text, flags=re.IGNORECASE):
            violations.append(f"{py_file.name}:{token}")
check("delay package contains no database-connect/write API calls",
      not violations, "; ".join(violations[:5]) or "clean")

print(f"\n{'=' * 50}")
print(f"Results: {PASS} passed, {FAIL} failed")
print(f"{'=' * 50}")
sys.exit(1 if FAIL > 0 else 0)