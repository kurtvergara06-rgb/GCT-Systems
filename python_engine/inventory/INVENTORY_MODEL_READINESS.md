# Model #4 — Inventory Demand Forecasting: Data Readiness Assessment

**Status: NOT READY TO TRAIN — CONTINUE COLLECTING GENUINE INVENTORY HISTORY**

This document is the Phase 3 **data sufficiency gate** outcome for Model #4.
It is a deliberate **OUTCOME 2 (insufficient data)** decision: no model, no
synthetic observations, no fabricated metrics were produced.

Audit executed against the live Laravel MySQL database
(`gct_system`, read-only) on **2026-09-16**, covering only
`stock_movements.source = 'app'`.

---

## 1. Actual Genuine Record Counts (source = 'app')

| Metric | Value |
|---|---|
| Genuine stock movements (`source = 'app'`) | **0** |
| Genuine Stock In | 0 |
| Genuine Stock Out | 0 |
| Genuine Adjustment | 0 |
| Distinct inventory items with genuine history | 0 |
| Stock Out observations per item | none (∅) |
| Genuine date range | none (∅) |
| Observations per week | none (∅) |
| Observations per month | none (∅) |
| Unique reference numbers on genuine Stock Out | 0 |
| Issuance documents (`inventory_issuances`) | 0 |
| Issuance line items (`inventory_issuance_items`) | 0 |
| Duplicate genuine transactions | 0 (no records exist) |

### Cross-checks performed

- Total `stock_movements` rows in DB: **99**, all flagged `source = 'demo'`
  (verified seeder-fabricated in the earlier audit; backfilled to `demo` by
  migration `2026_09_16_000001`). None are usable as ML observations.
- `source = NULL`: **0** rows.
- `inventory_items` master list: **53 items**, and on **all 53**,
  `on_hand = quantity_available` (ledger sync intact).
- 6 `purchase_orders` carry an `inventory_posted_at` timestamp, but the
  pre-refactor receipt routine wrote stock directly **without** writing
  `stock_movements`. Reconstructing those past receipts as observations
  would require backfilling/fabrication — **intentionally not done**.
- Genuine movement units: none to report (units `bottle, box, can, kg,
  liter, pack, pc, roll, set` exist **only** on `demo` rows).

## 2. Why the Dataset Is NOT Sufficient

1. **Zero genuine observations.** There is no `source = 'app'` demand history
   of any kind — not a single real Stock Out, Stock In, or Adjustment.
2. **No training period.** Time-aware validation is impossible with no
   historical sequence.
3. **No test period.** Chronological train/test splitting requires at least
   two time blocks; there is only "now".
4. **No meaningful variation.** There is no real demand signal to model, so any
   metric (MAE/RMSE/R²) would be manufactured rather than measured.
5. **Fabrication is explicitly prohibited** by the project rules, so the
   missing history cannot be invented or backfilled.

## 3. Exact Additional Data Needed

The application now records genuine ledger data through `InventoryLedgerService`
from four operational entry points, all written as `source = 'app'`:

| Workflow | Movement type | Reference |
|---|---|---|
| Purchase Order receipt to inventory | Stock In | PO number |
| Warehouse direct issue (new Issue-Stock action) | Stock Out | unique ISS number (`ISS-YYYY-NNNN`) |
| Part Request issue (warehouse) | Stock Out | PR number |
| Inventory item create / update / import (stock change) | Adjustment | n/a (item-level) |

To reach a forecastable dataset we need **real operational usage** of these
workflows for a sustained period, producing:

- Genuine **Stock Out** records with timestamp + quantity + item + unit + PR/ISS reference
  (this is the demand/target table).
- Genuine **Stock In** records (replenishment timing/volume → features).
- Genuine **Adjustment** records (corrections → data-quality flags).
- Item master metadata already available: `item_code`, `item_name`,
  `category`, `unit_of_measurement`, `reorder_level`, `on_hand`,
  `quantity_available` (all live today).

## 4. Recommended Minimum History (for future re-evaluation)

Proposed thresholds (env-overridable, mirroring the Fuel convention):

- **Absolute gate (train/test):** at least **13 consecutive weeks** (one
  quarter) of elapsed genuine history **per item subset**, OR at least
  **12 months** if monthly granularity is preferred.
- **Per-item demand depth:** ≥ **8 weekly observations** with ≥ **3 genuine
  Stock Out events**, and ≥ **3 distinct non-zero demand weeks** so a trend and
  validation period exist.
- **Population floor:** ≥ **20 items** with sufficient per-item history
  (from the 53-item master list).
- **Cross-section floor:** ≥ **500 genuine Stock Out records** in total before
  training a pooled model with item-category features.
- **Data split:** earlier periods train, **most recent 20–30%** held out as a
  chronological test window. No random shuffles.

Reaching the absolute gate is expected to take **weeks-to-months of live
warehouse usage** because the workflows were only just made genuine.

## 5. Monitoring & Re-check

- Run the same audit query (`stock_movements WHERE source = 'app'`) regularly.
- Re-run this gate before attempting training; the gate script will report
  current counts, date span, per-item Stock Out distribution, and per-month
  observations.
- Once thresholds are met, proceed to Phase 4+ (target = weekly/monthly
  **demand / Stock Out quantity** per item, time-aware split, no leakage) and
  produce `INVENTORY_MODEL_REPORT.md` from real metrics.

## 6. Recommendation

No Model #4 training occurs now. No fake model, no synthetic demand, no
invented metrics. The correct next step is operational: continue using the
four genuine workflows above and periodically re-run this readiness gate.

---

**Decision (Phase 3 Data Sufficiency Gate):**
**MODEL #4 NOT YET READY — CONTINUE COLLECTING GENUINE INVENTORY HISTORY**