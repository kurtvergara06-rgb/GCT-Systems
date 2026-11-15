# Model #4 — Inventory Demand Forecasting Readiness

## Current status

**PIPELINE IMPLEMENTED — PRODUCTION MODEL BECOMES READY ONLY AFTER THE GENUINE DATA GATE PASSES.**

Model #4 no longer depends on a fabricated bus identifier for genuine
training. The authoritative warehouse ledger does not identify a bus on every
issuance, so the production model now forecasts **fleet-level weekly demand by
spare part**.

Production data source:

```text
stock_movements
WHERE source = 'app'
```

Generated/demo rows remain development-only and are blocked by the shared ML
runtime policy.

## Genuine training unit

Each training observation is:

```text
(part, week) -> genuine quantity issued during that week
```

The target `quantity_issued` is calculated only from genuine **Stock Out**
ledger movements. Weeks between the first and latest real movement for a part
are retained with zero demand, so the model learns quiet weeks as well as issue
weeks.

The pseudo scope value `FLEET` is an explicit aggregation label, **not a bus
number**. No vehicle identity is invented.

## Data captured from the live warehouse ledger

The genuine pipeline uses:

- inventory item / part id
- item code and name
- category
- unit of measure
- movement timestamp
- movement type
- signed quantity change
- previous stock
- new stock
- reorder level
- reference number

Unavailable bus-specific fields are kept neutral and do not create synthetic
vehicle history.

## Leakage controls

- `quantity_issued` is target-only and never a model input.
- Demand lags and rolling demand use strictly earlier observations.
- `on_hand_after` is not exported as a model feature.
- Train/test validation is chronological; no random shuffle is used.
- Production reads only `source='app'` ledger rows.
- Sample/generated artifacts cannot serve production forecasts.

## Genuine readiness gate

Defaults are environment-overridable, but production currently requires:

- at least **13 distinct weeks** of genuine history;
- at least **20 distinct parts** represented;
- at least **260 weekly part observations**;
- at least **500 genuine Stock Out events**.

These requirements prevent a model from being marked production-ready merely
because a few warehouse transactions exist.

## Historical audit

The last recorded audit in this repository was performed on **2026-09-16**.
At that time there were **0** genuine `source='app'` stock movements; the 99
existing movements were demo/seeder data. That historical count must not be
mistaken for the current live-database count.

The new pipeline is capable of consuming genuine rows as they accumulate. If
current production history is still below the gate, `/inventory/status`
correctly returns **MODEL NOT READY** rather than using the sample model.

## Commands

Prepare the active dataset:

```bash
INVENTORY_DATA_SOURCE=genuine python -m inventory.prepare_training_data
```

Train after the readiness gate passes:

```bash
INVENTORY_DATA_SOURCE=genuine python -m inventory.train_model
```

Regression check for genuine aggregation:

```bash
python -m inventory.test_genuine_pipeline
```

## Production behavior

When a genuine artifact exists and passes runtime policy, the API reports:

```text
data_source: genuine
is_production_model: true
forecast_scope: fleet_part
```

If genuine history is insufficient:

```text
MODEL NOT READY
```

There is no silent fallback to generated inventory demand.
