# Model #2 — Fuel Consumption Prediction Report

## 1. Phase 1: Data Audit

**Source tables (Laravel MySQL, read-only):**
- `fuel_reports` — 238 rows, 11 distinct buses, `2026-05-20` → `2026-09-04`. Real measured values:
  - `fuel_liters` — **fuel consumed, unit = liters** (this is the prediction target). None null, none zero, range **0.73 – 4.57 L**, mean 2.47.
  - `distance_km` — trip distance (GPS-sourced). None null/zero, range 3.15 – 13.50 km.
  - `km_per_liter` — pre-computed `distance / fuel` column (verified consistent on every row ⇒ this field is **derived from the label** and rejected).
- `gps_trip_records` — 362 records, 11 buses; per-trip `duration_minutes`, `in_motion_minutes`, `idling_minutes`, `engine_hours`, `mileage_km`, `grouping` (route), `beginning_at`.

**Joinability:** `fuel_reports.gps_trip_record_id` is a real FK to `gps_trip_records` (nullable, nullOnDelete).
- **156 / 238** fuel reports are linked to a GPS trip with fuel > 0 and distance > 0.
- All 156 linked rows have **100% coverage** of every operational feature needed.
- **82** fuel reports have no GPS link ⇒ no features, therefore **excluded** (never synthesized).

**Consistency / quality checks:**
- Duplicate cartesian (bus+date+liters+distance) groups: **0**.
- `in_motion + idling == duration` on all 156 linked rows (dataset is coherent).
- No two features duplicate: `total_minutes == duration_minutes`, `mileage_km == distance_km` (verified; those dupes excluded).
- `engine_hours` is a **per-trip** value (0.28 – 1.53, avg 0.75) — legitimate feature.
- Route spread (linked rows): SM City Cebu – Mactan Airport (39), Ayala Center – IT Park (31), Talisay – SM Seaside (31), Parkmall – Mactan Newtown (29), Fuente Osmeña – IT Park (26) ⇒ **5 distinct routes**.
- Bus spread: 11 distinct buses (8 – 20 rows each), all 7 weekday classes represented.
- Signal sanity: **Pearson(distance, fuel) = 0.9466** — strong real relationship.

**Sufficiency verdict:** 156 real samples ≥ threshold (min 50 records, 3 routes). **Training proceeds on real data only.**

## 2. Model Definition

- **Prediction target:** `fuel_reports.fuel_liters` — **fuel consumed in liters**.
- **Model:** `scikit-learn` `RandomForestRegressor`, project-wide RF convention
  (`n_estimators=200, max_depth=None, min_samples_leaf=2, max_features="sqrt", random_state=42, n_jobs=-1`).
- **Features (11, all real operational measurements, none label-derived):**

| Feature | Meaning |
|---|---|
| `route_encoded` | Route category from GPS grouping |
| `bus_no_encoded` | Bus identity |
| `distance_km` | Real GPS distance |
| `trip_duration_minutes` | Trip clock duration |
| `in_motion_minutes` | Time actually moving |
| `idling_minutes` | Time idling |
| `engine_on_hours` | Engine-hours for the trip |
| `average_speed_kmh` | `distance / (duration/60)` |
| `departure_hour` | Hour of departure (0–23) |
| `day_of_week` | 0 (Mon) – 6 (Sun) |
| `is_weekend` | 1 on Sat/Sun |

- **Rejected fields and reason:**
  - `km_per_liter` — **derived directly from the label** (`distance / fuel`); would be full target leakage.
  - `total_minutes`, `mileage_km` — exact duplicates of `duration_minutes` / `distance_km`.
  - `severity` — GPS classification built from idling/motion thresholds (proxy of idling inputs).
  - `shift`, route schedule distance/time — **unavailable**: the gps → trip_assignment → trip_schedule → shuttle_route join had **zero coverage** on every fuel row.
  - `status`, `distance_source` — constant across all real rows.
  - `driver_name`, `remarks`, `manual_distance_reason` — free text / exception notes.
  - `report_date` — temporal identity; would let the model memorize dates.

## 3. Data Preparation

Extraction (`fuel/training_data.py` → `training_data/fuel_consumption_training.csv`):

```
INNER JOIN gps_trip_records g ON g.id = f.gps_trip_record_id
WHERE fuel_liters IS NOT NULL AND fuel_liters > 0
  AND distance_km   IS NOT NULL AND distance_km   > 0
```

- Only **real linked** fuel reports (no synthetic rows, no heuristic bus/date matching).
- Missing operational values encoded as `-1` (unknown), not invented.
- Label `fuel_liters` clipped to `>= 0` (real values); never appears in the feature matrix.
- CSV: **156 rows, 5 routes, 11 buses**, target 0.73 – 4.57 L (mean 2.47).

## 4. Training & Evaluation

`python -m fuel.prepare_training_data` → `python -m fuel.train_model`

- 80/20 stratified random split **before** fitting (label never seen during split).
- **Hold-out test set (32 rows):**

| Metric | Value |
|---|---|
| **MAE** | **0.26 liters** |
| **RMSE** | **0.34 liters** |
| **R²** | **0.9072** |

- Top feature importances: `distance_km` 0.328, `route_encoded` 0.184, `trip_duration_minutes` 0.139, `engine_on_hours` 0.135, `in_motion_minutes` 0.103. Distance-dominated, which matches the raw 0.95 correlation.

## 5. Validation Checks

- **Leakage:** no label input; `km_per_liter` (label-derived) rejected; test asserts it. Verified by automated test.
- **Train vs test gap (overfitting):** train MAE 0.182 L vs test MAE 0.259 L — modest gap, no severe overfit given 156 samples and leaf-constrained RF.
- **Outliers:** min 0.73 L corresponds to real short trips (~3 km); max 4.57 L real long trip. No synthetic outliers.
- **Unrealistic predictions:** outputs clipped to observed training range [0.73, 4.57] liters.
- **Insufficient data policy:** thresholds `min_records=50`, `min_routes=3` (env FUEL_MIN_RECORDS / FUEL_MIN_ROUTES); below them the model is **not saved** and state reports `FUEL_ML_NOT_READY` — prediction refuses rather than inventing numbers.
- **Automated tests:** `python -m fuel.test_fuel` → **36 passed, 0 failed** (CSV schema, leakage, train metrics, save/reload, in-range predictions, determinism, partial inputs, encoding alignment vs training, FastAPI `/fuel/status` + `/fuel/predict`).

## 6. Deliverables / Files

```
python_engine/fuel/
  __init__.py               module docstring
  config.py                 paths + data thresholds (env-overridable)
  training_data.py          audit SQL, feature engineering, reject-list, write CSV
  prepare_training_data.py  CLI: extract real data -> training_data/fuel_consumption_training.csv
  model.py                  train_fuel_model / save_fuel_model / save_state (RF convention)
  train_model.py            CLI: train from CSV, write artifacts, threshold guard
  predict.py                fuel_readiness() / predict_fuel_consumption() (side-effect free)
  router.py                 FastAPI /status + /predict (standalone router)
  test_fuel.py              automated end-to-end tests
  models/
    fuel_liters_rf.pkl          trained RandomForestRegressor
    fuel_liters_features.json   features + target + route/bus metadata
    fuel_liters_report.txt      model report (metrics, importances, convention)
    fuel_liters_state.json      readiness state (FUEL_ML_READY, sample_count 156)
training_data/fuel_consumption_training.csv   real training dataset (gitignored, reproducible)
```

Model #1 (`python_engine/eta/`) and all operation_ai / NLP / Laravel files were **not modified**.

## 7. Sample Prediction

Real model call (direct service import):

```
readiness: ml_ready=True, source=ml, sample_count=156
Predicted Fuel Consumption: 3.53 liters
  model name  = fuel_liters_rf (RandomForestRegressor, v FUEL_ML_READY)
  value       = 3.53
  unit        = liters
  features    = route_encoded=4.0 (Talisay - SM Seaside), bus_no_encoded=0.0 (GCT-101),
                distance_km=20.5, trip_duration_minutes=50.0, in_motion_minutes=46.0,
                idling_minutes=4.0, engine_on_hours=0.83, average_speed_kmh=24.6,
                departure_hour=8.0, day_of_week=3.0, is_weekend=0.0
  sample count = 156 real recorded fuel reports
  metrics      = MAE 0.26 L, RMSE 0.34 L, R² 0.9072 (held-out)
```

```
Model #2 was created and validated independently. No frontend or Laravel analytics integration was performed.
```