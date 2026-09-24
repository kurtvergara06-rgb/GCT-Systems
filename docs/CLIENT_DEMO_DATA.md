# Client Demo Dataset (Models #3 and #4)

This dataset is for **local/client demonstration only**. It creates realistic operational records inside the same database tables used by the frontend so the demo can be viewed normally in Operation, Incidents, Maintenance, Warehouse, Purchase, and Analytics-related screens.

## Run it

```bash
php artisan db:seed --class=ClientDemoDataSeeder
```

The seeder is intentionally **non-destructive**. It does not truncate application tables. Re-running it replaces only its own `DEMO-*` / `TRIP-DEMO-*` fact rows and preserves genuine operational rows.

## What it creates

- 8 demo buses and 8 demo drivers
- 5 CALABARZON demo shuttle routes
- 460 historical demo trip schedules and trip assignments
- 460 matching Daily Driver Reports with realistic early/on-time/delayed outcomes
- 40+ linked demo incidents (traffic, breakdown, accident patterns)
- 24 realistic bus spare-part inventory items
- 700+ stock movement rows across several months
- 12 connected Job Orders
- 12 connected Purchase Requests
- 9 connected Purchase Orders

The records are created in the normal application tables, so they are visible from the existing frontend pages.

## ML safety / provenance

The generated records are realistic but **not genuine GCT history**.

### Delay Model #3

Demo schedule codes use the `TRIP-DEMO-*` prefix. The genuine Delay exporter excludes the configured `TRIP-*` demo prefix, so these rows do not qualify as genuine DDR training history.

### Inventory Model #4

Demo stock movements use:

```text
source = demo
```

Genuine Inventory Model #4 training reads only:

```text
source = app
```

Therefore the client demo data remains visible in the Warehouse frontend without contaminating genuine production-model training.

## Demo references

Records can be recognized by prefixes such as:

```text
TRIP-DEMO-...
DEMO-DDR-...
DEMO-INC-...
DEMO-JO-...
DEMO-PR-...
DEMO-PO-...
DEMO-PART-...
DEMO-BUS-...
DEMO-DRV-...
```

Do not present these records or any model trained from synthetic/sample data as genuine operational history.
