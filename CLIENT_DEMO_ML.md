# Client Demo ML Workflow — Models #3 and #4

This workflow trains Models #3 and #4 from the **same synthetic records visible in the normal GCT frontend**.

The records remain explicitly DEMO / SYNTHETIC and are never treated as genuine GCT operational history.

## 1. Seed frontend-visible demo records

```powershell
php artisan db:seed --class=ClientDemoDataSeeder
```

This creates demo trips, DDRs, incidents, inventory movements, Job Orders, Purchase Requests, and Purchase Orders without truncating genuine rows.

## 2. Export Delay Model #3 demo history

```powershell
php artisan delay:export-demo
```

Output:

```text
training_data/delay/demo_delay_training.csv
```

The export accepts only `TRIP-DEMO-*` records.

## 3. Train Delay Model #3 demo artifact

From the project root:

```powershell
cd python_engine
$env:ML_RUNTIME_MODE="development"
$env:DELAY_DATA_SOURCE="demo"
python -m delay.prepare_training_data
python -m delay.train_model
```

Demo artifacts are isolated from the canonical production artifact:

```text
python_engine/delay/models/demo_delay_arrival_rf.pkl
python_engine/delay/models/demo_delay_arrival_features.json
python_engine/delay/models/demo_delay_arrival_state.json
```

The trained artifact is persisted with synthetic/development provenance, so the production runtime policy cannot accept it as a genuine model.

## 4. Train Inventory Model #4 demo artifact

Keep the same PowerShell window:

```powershell
$env:INVENTORY_DATA_SOURCE="demo"
python -m inventory.prepare_training_data
python -m inventory.train_model
```

Inventory demo mode reads only:

```text
stock_movements.source = 'demo'
reference_no LIKE 'DEMO-%'
```

Demo artifacts are isolated:

```text
python_engine/inventory/models/demo_inventory_demand_rf.pkl
python_engine/inventory/models/demo_inventory_demand_features.json
python_engine/inventory/models/demo_inventory_demand_state.json
```

## 5. Run the Python engine for the local client demo

```powershell
$env:ML_RUNTIME_MODE="development"
$env:DELAY_DATA_SOURCE="demo"
$env:INVENTORY_DATA_SOURCE="demo"
uvicorn main:app --host 127.0.0.1 --port 8000 --reload
```

The demo artifacts can serve predictions only in development/demo runtime. Render/production continues to require genuine models.

## Safety Rules

- Never change demo rows to `source='app'`.
- Never rename `TRIP-DEMO-*` rows to look genuine.
- Never present demo evaluation metrics as historical GCT performance.
- Do not commit locally trained demo `.pkl` artifacts unless intentionally requested.
- Genuine Delay #3 still requires real matched DDR/schedule history.
- Genuine Inventory #4 still reads only application-written `source='app'` warehouse movements.
