# Model #3 — Trip Delay Prediction (Arrival Delay): SAMPLE / DEMONSTRATION Report

Date: 2026-09-16
Scope: **Model #3 — Delay Prediction (Trip Late/Early)**

> This Model #3 implementation is a demonstration prototype trained on a
> separate sample dataset because the current GCT database does not contain
> sufficient genuine historical DDR/schedule records for delay-model training.
> The sample dataset is not presented as actual GCT operational data.

A read-only audit of the genuine database (2026-09-16, `python_engine/delay/DELAY_MODEL_READINESS.md`)
found the Model #3 data gate **NOT READY**: `daily_driver_reports` = 0 rows,
`trip_schedules` = 4 demo rows (one trial date, one route, all Assignment Status
`Unassigned`), `trip_assignments` = 4 rows (1 driver, 1 bus), and 0 DDR records
matched to a schedule. Genuine delay-history-driven training is therefore not
possible yet, and this prototype is trained on a **separate, explicitly
labelled SAMPLE dataset** (300–500 simulated completed trips). No sample row is
written to MySQL or any genuine GCT table, and no genuine record was modified
or used as a feature source for training.

## 1. Objective and target

- Predict **arrival delay in minutes** (`arrival_delay_minutes`) before a trip
  departs, using only **pre-trip** information.
- Risk banding is a separate business-rule layer (non-ML):
  - 0–5 min → **On Time**
  - 6–10 min → **Minor Delay**
  - 11–20 min → **Moderate Delay**
  - 21+ min → **High Delay**

## 2. Dataset (SAMPLE / DEMONSTRATION DATA)

- Location (central repo training data, outside the python engine):
  - `training_data/delay/sample_delay_training.csv` — 500 simulated completed trips,
    25 raw columns (trip date, route, bus, driver, trip ticket, scheduled/actual
    departure & arrival times, scheduled/actual duration, departure delay,
    arrival delay, and pre-trip predictor fields).
  - `training_data/delay/sample_delay_training_features.csv` — 500 rows, the 15-PERFORMANCE
    feature matrix plus trace columns and the target.
- Coverage: **6 routes, 12 buses, 16 drivers**, dates 2026-03-19 → 2026-08-31
  (166 distinct dates, 24 distinct weeks, 165-day span).
- Realistic variation: on-time = 135, minor = 250, moderate = 96, high = 19;
  arrival delay min 0.7 / mean 7.99 / max 29.1 minutes. No impossible values
  (e.g., actual duration never negative, delays within plausible bounds).

## 3. Features (all strictly pre-trip — no leakage)

The target `arrival_delay_minutes` and all post-trip fields
(`departure_delay_minutes`, `actual_departure_time`, `actual_arrival_time`,
`actual_duration_minutes`) are **never** features.

| Feature | Why it is pre-trip |
| --- | --- |
| `route_encoded` | Route is known before dispatch |
| `bus_encoded` | Assigned bus is known pre-trip |
| `driver_encoded` | Assigned driver is known pre-trip |
| `scheduled_departure_hour` | From the published schedule |
| `scheduled_departure_minute` | From the published schedule |
| `day_of_week` | Calendar, known in advance |
| `is_weekend` | Calendar, known in advance |
| `month` | Calendar, known in advance |
| `season_encoded` | Calendar (Wet/Dry/Cool), known in advance |
| `scheduled_duration_minutes` | Published schedule duration |
| `route_distance_km` | Static route definition |
| `route_prior_delay_mean_min` | Historical route stats computed **only** from prior-dated trips in the training window |
| `route_prior_delay_rate` | Same — prior training-period data only |
| `driver_prior_delay_mean_min` | Same — prior training-period data only |
| `driver_trip_seq` | Trip counter that increments over time; `1` for a new driver |

Route/driver prior statistics are computed chronologically (expanding window):
a trip on date D only ever uses aggregate statistics from trips strictly before
D, so there is no target leakage through the aggregates.

## 4. Model configuration

Scikit-learn Random Forest per the project's shared convention:

```
RandomForestRegressor(
    n_estimators=200,
    max_depth=None,
    min_samples_leaf=2,
    max_features="sqrt",
    random_state=42,
    n_jobs=-1,
)
```

Chronological (time-ordered) split on `trip_date`:
- **Training period:** 2026-03-19 → 2026-07-29 — **n_train = 401** rows
- **Test period:** 2026-07-30 → 2026-08-31 — **n_test = 99** rows

## 5. Performance metrics (chronological held-out test)

| Metric | Value |
| --- | --- |
| MAE | 3.34 min |
| RMSE | 4.41 min |
| R²  | -0.0626 |

Honest reading: the sample model beats always-predicting-the-mean in terms of
MAE/RMSE, but R² is slightly negative, meaning the synthetic features are weak
explanators on the held-out period. This is expected for a small synthetic
demonstration dataset and is reported without manipulation.

## 6. Verification checklist (all meet spec)

- Save/reload of model, encoders, metadata, and state artifacts works.
- Predictions are deterministic, non-negative and within a plausible range.
- Prediction-time encoding matches training-time encoding (verified by tests).
- No target leakage: label and all post-trip columns excluded from features.
- Chronological split verified (no date straddles the boundary).
- `/delay/status` + `/delay/predict` return the SAMPLE/DEMONSTRATION warning,
  `data_source=sample`, `is_production_model=false`, and record counts.
- Sample CSVs are verified to live in the central `training_data/` folder and
  are **never** written to the production database.

## 7. Artifacts

- Model: `python_engine/delay/models/delay_arrival_rf.pkl`
- Encoders + metadata + risk thresholds: `python_engine/delay/models/delay_arrival_features.json`
- Training report: `python_engine/delay/models/delay_arrival_report.txt`
- State: `python_engine/delay/models/delay_arrival_state.json`
- Dataset: `training_data/delay/sample_delay_training.csv`,
  `training_data/delay/sample_delay_training_features.csv`
- Module: `python_engine/delay/` (`config.py`, `training_data.py`,
  `prepare_training_data.py`, `model.py`, `train_model.py`, `predict.py`,
  `router.py`, `main.py`, `test_delay.py`, `requirements.txt`)

## 8. Predictions, sorting, and recommendations

The predictor `predict_arrival_delay(...)` takes only pre-trip inputs
(route, bus, driver, trip date, scheduled departure time), returns the predicted
arrival delay in minutes plus its risk band, and is surfaced via:

- `GET  /delay/status` — model readiness, source/version, SAMPLE/DEMONSTRATION
  warning, training record count.
- `POST /delay/predict` — predicted arrival delay, `risk_status`, threshold
  definition, and model/source metadata.

**Operational recommendation:** keep this module registered as a
SAMPLE/DEVELOPMENT prototype. As genuine DDR/schedule history accumulates,
retrain on real completed trips (reaching ≥ 300 genuine matched records with
≥ 5 routes / 10 buses / 15 drivers / 4 weeks) before presenting delay
predictions as actual GCT operational output.