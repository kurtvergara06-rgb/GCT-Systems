# Model #3 — Delay Prediction (Trip Late/Early): Data Readiness Assessment

**Status: NOT READY — CONTINUE COLLECTING GENUINE DDR HISTORY**

This document is the Phase 3 **data sufficiency gate** outcome for Model #3.
It is a deliberate **OUTCOME 2 (insufficient data)** decision: no model, no
synthetic observations, no fabricated metrics, no API endpoint, and no
evaluation report were produced.

Audit executed against the live Laravel MySQL database
(`gct_system`, read-only) on **2026-09-16**, covering only genuine
operational history:

- `daily_driver_reports` (the delay source: driver-logged actual
  `departure_time` / `arrival_time` per trip).
- `trip_schedules` (the scheduled reference: `departure_time` /
  `estimated_arrival_time` per scheduled trip).
- `trip_assignments` (driver + bus bindings used to match a DDR to its
  scheduled trip).

---

## 1. Actual Genuine Record Counts

| Metric | Value |
|---|---|
| Daily Driver Report rows (`daily_driver_reports`) | **0** |
| Distinct drivers on DDR | 0 |
| Distinct buses on DDR | 0 |
| Distinct report dates on DDR | 0 |
| DDR date range | none (∅) |
| DDR matched to a scheduled trip (date + driver + bus) | **0** |
| Valid scheduled departure + actual departure (departure-delay label) | 0 |
| Valid scheduled arrival + actual arrival (arrival-delay label) | 0 |
| Usable delay observations for training | 0 |
| Duplicate `report_date` + `trip_ticket` pairs | 0 (no records exist) |
| DDR with missing actual departure time | 0 |
| DDR with missing actual arrival time | 0 |

### Cross-checks performed

- `trip_schedules`: **4 rows, all demo** (`TRIP-001` … `TRIP-004`), all on a
  single date `2026-09-17`, all one `shuttle_route_id`, all `status =
  'Scheduled'`, and all `assignment_status = 'Unassigned'`. Non-demo codes: **0**.
- `trip_assignments`: **4 rows = 4 schedules**, but **1 distinct driver** and
  **1 distinct bus** — no driver/bus variety even in the demo set.
- Master tables: `drivers` = **1**, `buses` = **1**, `shuttle_routes` = **1**,
  `driver_attendances` = **1**.
- `gps_trip_records` = **362** rows (ingested PDF GPS reports). These describe
  trip performance for the Operation AI / ETA models; they are **not** DDR
  delay labels and cannot be substituted for matched scheduled-vs-actual
  departure/arrival observations.
- No code paths were executed for matching/training; only SELECT audits ran.

## 2. Why the Dataset Is NOT Sufficient

1. **Zero genuine DDR observations.** No driver-logged actual departure /
   arrival times exist to compare against a schedule.
2. **Zero matched trips.** Even with demo schedules present, there are no DDR
   rows to join (`ddr_matched_to_schedule = 0`), so no departure- or
   arrival-delay label can be computed.
3. **No training or test period.** Delay requires a scheduled reference and an
   actual result recorded over time; there is only the present.
4. **No meaningful variation.** One route, one driver, one bus, one date — a
   model trained here could only memorize a single point.
5. **Fabrication is explicitly prohibited** by the project rules: synthetic
   DDR records, invented schedules, or backfilled actual times would violate
   the audit-first requirement and are **not** created.

## 3. Exact Additional Data Needed

Genuine operational usage of the two existing workflows:

| Workflow | What it produces | Reference |
|---|---|---|
| Daily Driver Report (DDR) encoding at `/operation/daily-driver-reports` | Driver-logged actual `departure_time` + `arrival_time`, `report_date`, `trip_ticket`, `from_location` / `to_location`, `passengers` | unique `DDR-{Y}-{NNNN}` |
| Trip scheduling + assignment (any round-trip route that produces `trip_schedules` rows bound via `trip_assignments` to a driver + bus) | Scheduled `departure_time` + `estimated_arrival_time` per date, with real driver/bus assignment | `trip_code` |

To compute a delay label, a report must match a scheduled trip on
`report_date` = `trip_date` **and** the same driver **and** the same bus
(mirroring `DailyDriverReportScheduleMatchService`). Delays are then defined
as:

- `departure_delay_minutes` = actual departure − scheduled departure
  (overnight wrap handled; negative allowed → "early").
- `arrival_delay_minutes` = actual arrival − scheduled arrival estimate
  (overnight wrap handled; negative allowed → "early").

## 4. Recommended Minimum History (for future re-evaluation)

Proposed thresholds (env-overridable, mirroring the ETA / Fuel convention):

- **Absolute gate:** at least **50 valid matched DDR trips** (ETA-style
  `DELAY_MIN_RECORDS=50`); **prefer ≥ 300** for a stable model.
- **Route coverage:** ≥ **3 distinct shuttle routes** (`DELAY_MIN_ROUTES=3`).
- **Population floor:** ≥ **5 distinct buses** and ≥ **5 distinct drivers**
  so per-bus / per-driver effects are estimable (well above the current 1/1).
- **Calendar span:** ≥ **4 weeks of elapsed history** with valid scheduled +
  actual departure and arrival times (`DELAY_MIN_WEEKS=4`).
- **Data quality:** both timestamps present; impossible values rejected
  (e.g., inconsistent overnight handling, arrival long before departure);
  duplicate `report_date` + `trip_ticket` rows excluded.
- **Target:** `arrival_delay_minutes` primarily (`departure_delay_minutes`
  kept as a separate target — never mixed).
- **Split:** earlier periods train, most recent 20–30% held out
  chronologically. No random shuffles; no leakage of post-trip information
  into features.

Reaching the absolute gate is expected to take **several weeks** of genuine
operations: the DDR workflow went live only on **2026-09-16** and currently
holds zero genuine rows.

## 5. Monitoring & Re-check

- Re-run the same read-only audit (`daily_driver_reports` totals, matched-trip
  join, valid timestamp counts, distinct routes/buses/drivers, date span/weeks)
  before attempting any training.
- The gate will be re-evaluated; once thresholds pass, proceed to the training
  pipeline (RandomForest convention: `n_estimators=200`, `max_depth=None`,
  `min_samples_leaf=2`, `max_features="sqrt"`, `random_state=42`, `n_jobs=-1`),
  configured with `python_engine/delay/config.py` (paths → central
  `training_data/delay/`), and evaluated only on held-out chronological test
  data with real MAE / RMSE / R².

## 6. Recommendation

No Model #3 training occurs now. No fake model, no synthetic DDR, no invented
metrics, no `/delay` API endpoint. The correct next step is operational:
continue recording genuine Daily Driver Reports for every completed trip
(whenever a matching schedule exists) and periodically re-run this readiness
gate.

---

**Decision (Phase 3 Data Sufficiency Gate):**
**MODEL #3 NOT READY — CONTINUE COLLECTING GENUINE DDR HISTORY**