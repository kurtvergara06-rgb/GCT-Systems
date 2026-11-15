# Model #4 — Inventory Demand Forecasting — Development Prototype

**IMPORTANT DISCLAIMER**

This report describes a **DEVELOPMENT PROTOTYPE** trained on
**GENERATED SAMPLE DATA** (`training_data/inventory/`, project root). It does
**NOT** represent actual GCT operational inventory history. The live GCT
database currently contains ZERO genuine (`source='app'`) stock-movement
records, so Model #4 as a production-grade model is explicitly **NOT YET
READY**. Nothing in this prototype was written to the production database;
no part of it may be presented as production-ready.

---

## 1. Purpose

Demand-forecast weekly spare-part issuance (`quantity_issued` in the part's
unit) per (bus, part) so the warehouse team can anticipate next-week demand.
The ML forecast is kept rigorously separate from the plain business-rule
threshold detection (stock vs reorder level) and from the prescriptive
hint (never an automatic purchase order).

## 2. Data policy

| Item | Value |
| --- | --- |
| Training data | Generated SAMPLE (development only) |
| Location | `training_data/inventory/` (project root) |
| Rows | 49,368 raw weekly observations (22 buses x 34 parts x 66 weeks) |
| Buses | 22 (GCT-101 .. GCT-122) |
| Parts | 34 (P001 .. P034 across 10 categories) |
| Weekly periods | 2025-06-02 .. 2026-08-31 (66 Mondays) |
| Feature rows | 47,872 after the 8-week rolling warm-up drop |
| Production DB writes | NONE - this prototype never touches MySQL |
| `stock_movements` / `source='app'` | Untouched - there are still 0 genuine records |

The generator (`inventory/sample_data/generate_sample_data.py`) is deterministic
(`DEFAULT_SEED=20260916`) so sample data is reproducible. It writes its output
to `training_data/inventory/`.

## 3. Target and horizon

- **Target:** `quantity_issued` — the number of units of the part issued in
  the (bus, part) week.
- **Horizon:** `next_week` (the upcoming Monday-based week).
- **Granularity:** one forecast row per bus + part + week.

## 4. Features (19)

`bus_encoded, part_encoded, category_encoded, unit_encoded,
maintenance_type_lag1_encoded, on_hand, vehicle_mileage,
breakdown_count_lag1, reorder_level, supplier_lead_days, demand_lag1,
demand_lag2, rolling_demand_4w, rolling_demand_8w, days_since_last_issue,
week_of_year, month, quarter, elapsed_weeks`

Categorical fields are label-encoded with **sorted** maps persisted to
`models/inventory_demand_features.json` so prediction-time encodings exactly
match training encodings (unseen values code to `-1.0` and never crash the
service).

## 5. Leakage controls

- `quantity_issued` (the target) is **never** an input feature.
- `on_hand_after` (derived `before - issued`) is rejected at build time and
  is **not even written** to the exported feature matrix.
- Current-period `maintenance_type` and `breakdown_count` are **lagged one
  week** before entering the model.
- `days_since_last_issue` is recomputed from the strict prior issuance
  stream (never from the current row's target).
- Split is **strictly chronological** — the earliest 80% of weekly periods
  are the training window; the most recent 20% are held out. No random
  shuffle is performed anywhere.

## 6. Train / test division

| Set | Rows | Period |
| --- | --- | --- |
| Train | 38,148 | 2025-06-16 .. 2026-06-01 |
| Test (held-out) | 9,724 | 2026-06-08 .. 2026-08-31 |
| Test fraction | 0.203 | — |

## 7. Model configuration (project-wide Random Forest convention)

`n_estimators=200, max_depth=None, min_samples_leaf=2, max_features='sqrt',
random_state=42, n_jobs=-1`

## 8. Evaluation (chronological held-out test)

| Metric | Value |
| --- | --- |
| MAE  | 0.270 units/week |
| RMSE | 0.783 units/week |
| R²   | 0.095 |

**Interpretation (honest).** The sample demand is ~96% zero-issuance weeks
(intermittent spare-part demand), which is realistic for this domain but
harsh for a regression metric: predicting the average yields R² ≈ 0, so any
positive R² already beats the mean baseline. The model is useful as a
prioritization score, not as an exact unit-count oracle. Full per-part MAE
and predicted-vs-actual means are in
`models/inventory_demand_report.txt`.

## 9. Top feature importances

vehicle_mileage | bus_encoded | week_of_year | elapsed_weeks | on_hand |
days_since_last_issue | rolling_demand_8w | supplier_lead_days

## 10. Artifacts produced

| Artifact | Path |
| --- | --- |
| Trained model | `models/inventory_demand_rf.pkl` (joblib) |
| Encoders + metadata + features + periods | `models/inventory_demand_features.json` |
| Training report | `models/inventory_demand_report.txt` |
| Service state | `models/inventory_demand_state.json` |
| Feature matrix | `training_data/inventory/sample_inventory_training_features.csv` |

## 11. Retraining a new model

```
cd python_engine
.venv\Scripts\python.exe -m inventory.sample_data.generate_sample_data  # optional: regenerate sample
.venv\Scripts\python.exe -m inventory.prepare_training_data
.venv\Scripts\python.exe -m inventory.train_model
```

## 12. Running the tests

```
.venv\Scripts\python.exe -m inventory.test_inventory
```

Result: **60 PASS / 0 FAIL** (dataset integrity, per-series chronology,
readiness thresholds, feature-matrix column exactness, absence of the target
and of `on_hand_after`, lag-correctness of `demand_lag1`, chronological
split, train + metrics + artifacts, reload + deterministic non-negative
in-range prediction, part lookup by id and by name, FastAPI status/predict,
404 unknown part, 503 when the model is unavailable, business-rule layer,
honest-labelling).

## 13. API surface (development prototype)

| Endpoint | Method | Behaviour |
| --- | --- | --- |
| `/inventory/status` | GET | Model readiness + source + sample count + disclaimer |
| `/inventory/predict` | POST | Next-week demand forecast + risk assessment + suggestion |
| `/inventory/cache/reset` | POST | Drop lazy-loaded artifacts (ops/tests) |

- Request body: `bus_id`, `part_id` (id or name), optional
  `forecast_date`, `current_stock`, `vehicle_mileage`,
  `breakdown_count_lag1`, `maintenance_type_lag1`, `demand_lag1`,
  `demand_lag2`, `rolling_demand_4w`, `rolling_demand_8w`,
  `days_since_last_issue`. Omitted operational context falls back to the
  part/bus training-set means (documented, deterministic, never invented).
- Responses carry `disclaimer`, `risk_status` (`STOCKOUT_RISK` /
  `LOW_STOCK` / `ELEVATED_DEMAND` / `NORMAL`), `risk_flags`,
  `recommended_action`, `suggested_order_qty`, and `lead_note`. The rule
  layer is explicitly labelled "business-rule threshold detection (not an ML
  prediction)".
- Errors: `404` unknown part, `503` model not ready, `422` malformed body.

The router is registered in the root `python_engine/main.py`
(`/inventory` tag "Predictive Analytics").

## 14. Switch to genuine data later

When genuine records appear in `stock_movements` (`source='app'`), set:

```
# python_engine/.env
INVENTORY_DATA_SOURCE=genuine
```

`training_data.fetch_genuine_stock_movements()` and
`_panelize_genuine_movements()` currently raise `NotImplementedError` on
purpose — that extraction is **not implemented yet** because there is nothing
to extract. The inventory refactor (ledger + `source` column + issuance
tables) is already collecting that history in production.

## 15. What has NOT been done

- No model has been trained on actual GCT operational inventory data.
- No change to the ETA (#1), Fuel (#2) or delay (#3) models.
- No schema/migration/data change to `stock_movements` or the inventory
  ledger.
- No automatic ordering / procurement behaviour — the prescriptive layer is
  only a human-readable suggestion.

## 16. Files delivered

```
python_engine/inventory/
  __init__.py
  config.py
  INVENTORY_MODEL_READINESS.md            (data-sufficiency gate: NOT READY on genuine data)
  INVENTORY_MODEL_REPORT.md               (this file)
  predict.py                              (inference + business-rule layer)
  prepare_training_data.py                (CLI: validation -> feature matrix)
  router.py                               (FastAPI endpoints)
  test_inventory.py                       (60 self-contained PASS/FAIL checks)
  train_model.py                          (CLI: train + save artifacts)
  training_data.py                        (load/validate/encode/split)
  model.py                                (RandomForestRegressor + save/state)
  requirements.txt
  sample_data/__init__.py
  sample_data/generate_sample_data.py     (deterministic SAMPLE generator -> central training_data/)
../training_data/inventory/               (project root, central training folder)
  sample_inventory_training.csv
  sample_inventory_training_features.csv
  models/inventory_demand_rf.pkl
  models/inventory_demand_features.json
  models/inventory_demand_report.txt
  models/inventory_demand_state.json
python_engine/main.py                     (+ inventory router registration, additive)
```

## 17. Success criterion for this phase

- [x] SAMPLE/DEVELOPMENT dataset exists and is fully validated.
- [x] Data-sufficiency thresholds PASS on the sample pipeline.
- [x] Chronological train/test split, no leakage.
- [x] Model trains, evaluates, persists, reloads.
- [x] Prediction service is deterministic, non-negative, in-range.
- [x] FastAPI endpoints work (status/predict/404/503).
- [x] Test suite: 60 PASS / 0 FAIL.
- [x] No production inventory data touched; model is labelled SAMPLE/
      DEVELOPMENT everywhere (code, API payloads, and this report).
- [x] Next retraining step documented for the genuine-data future.