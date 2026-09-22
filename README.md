# GCT Systems

Fleet and operations management system for GCT Transport Services covering Maintenance, Warehouse, Purchase, Operation, and Administration workflows.

**Production:** https://gct-systems.onrender.com/

## Architecture

GCT Systems uses a dual-engine architecture:

- **Laravel backend** — authentication, business workflows, database access, validation, audit activity, and realtime events.
- **Python FastAPI engine** — PDF/NLP extraction, analytics, delay/ETA/fuel/inventory models, and operation auto-scheduling.
- **Laravel Reverb + Echo** — realtime UI updates.
- **MySQL 8.4** — primary production database.

Laravel communicates with the Python engine through `NLP_API_URL` and `OPERATION_AI_BASE_URL`.

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+ (PHP 8.4 production Docker image) |
| Database | MySQL 8.4; SQLite supported for automated tests |
| Frontend | Blade, Vanilla JavaScript, Tailwind CSS 4 |
| Build | Vite 8 |
| Realtime | Laravel Reverb + Echo |
| Python Engine | FastAPI, PyMuPDF, spaCy, pandas, NumPy, scikit-learn, PyTorch |
| Deployment | Docker + Render |
| CI | GitHub Actions |

## Modules

### Maintenance
Job Orders, PMS scheduling, mechanic availability, fuel reports, and purchase requests.

### Warehouse
Inventory, part requests, stock movements, and incoming deliveries.

### Purchase
Maintenance requests, inventory restock, purchase orders, scheduled purchases, and purchase history.

### Operation
Buses, routes and stops, trip scheduling, driver/bus assignment, automated scheduling, driver/mechanic attendance, Daily Driver Reports (DDR), incidents, and replacement-bus dispatch.

### Administration
Accounts, role/permission configuration, activity logs, notifications, data management, analytics, and settings.

## Operational Data Relationships

The operation workflow stores durable trip relationships instead of relying only on heuristic matching:

```text
Trip Schedule
    |
    +--> Trip Assignment
    |       |
    |       +--> Daily Driver Report
    |
    +--> Incident
            |
            +--> Incident Replacement
```

`daily_driver_reports` stores nullable `trip_schedule_id` and `trip_assignment_id` when an exact match is available. Historical DDR records without these keys still use `DailyDriverReportScheduleMatchService` as a compatibility fallback.

When a breakdown replacement is dispatched:

- `incident_replacements.original_bus_id` preserves the bus involved in the incident.
- `incident_replacements.replacement_bus_id` preserves the dispatched replacement.
- `trip_assignments.original_bus_id` preserves the trip's initial bus.
- `trip_assignments.bus_id` represents the current/effective bus continuing the trip.

This lets analytics distinguish the originally assigned bus from the bus that actually continued the trip.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 22+
- Python 3.10+
- MySQL 8.4 for normal local/production use

## Local Setup

### Laravel

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

For the local development runner:

```bash
composer run dev
```

### Reverb

Run Reverb separately when testing realtime behavior:

```bash
php artisan reverb:start
```

### Python Engine

```bash
cd python_engine
pip install -r requirements.txt
uvicorn main:app --reload --host 127.0.0.1 --port 8000
```

The committed Python checkout includes the core PDF extraction plus the analytics, operation AI, ETA, fuel, inventory, and delay routers mounted by `main.py`.

Some severity/NER/anomaly/ingestion helpers are intentionally loaded as **optional modules** by `main.py`. They are not present in the current repository checkout, so the engine degrades gracefully instead of failing startup. Do not advertise or depend on an optional endpoint until its implementation exists in the repository.

### Docker Compose

`compose.yaml` is the Laravel Sail development stack for:

- Laravel
- MySQL

It is **not** a complete container definition for the separate FastAPI and Reverb services. Start those services separately using the commands above, or use their production Render services.

```bash
docker compose up
```

## Delay Model #3

The delay pipeline supports two explicit data sources:

```env
DELAY_DATA_SOURCE=sample
```

or:

```env
DELAY_DATA_SOURCE=genuine
```

`genuine` mode never silently falls back to sample data.

Export genuine matched DDR history with:

```bash
php artisan delay:export-genuine
```

New DDR records prefer their stored `trip_schedule_id` / `trip_assignment_id`. Historical records can still use schedule matching.

Incident features are tied to the exact trip whenever a direct trip relationship exists. Legacy fallback matching is only allowed for incidents that do not already belong to another explicit trip.

## Inventory Model #4

The inventory forecasting implementation currently uses generated/sample training data until sufficient genuine stock-movement history exists.

Sample-model output must be treated as development/demo output, not as a production genuine forecast.

Do not change the model to claim genuine readiness until real stock movements satisfy its readiness checks.

## Testing

### Laravel

```bash
php artisan test
```

or:

```bash
composer run test
```

The repository's test count changes as features are added, so this README intentionally does not hard-code a stale number.

### Frontend

```bash
npm ci
npm run build
```

### Python

Python model folders contain script-style validation tests where applicable. CI always compiles the Python tree to catch syntax/import-source errors without pretending that unavailable optional ML modules are present.

## Continuous Integration

`.github/workflows/ci.yml` runs on pull requests, `main`, and `fix/**` branches and checks:

- Laravel test suite on PHP 8.4 + SQLite
- Vite production build on Node 22
- Python source compilation on Python 3.12

The production verification workflow also runs a quality gate before its Render availability check.

> Render auto-deploy behavior is configured separately in Render. A GitHub workflow quality gate does not by itself guarantee that Render waits for GitHub checks unless the Render service is configured to do so.

## Realtime Updates

Events are broadcast through Laravel Reverb and received through Echo. AJAX-ready regions can refresh without requiring a complete page reload.

## Activity Log Policy

Activity logs are a controlled audit trail for meaningful changes, including:

- create/update/delete/deactivate
- approve/reject/status changes
- assignments and completions
- receiving/issuing/imports
- account/security/permission changes
- login/logout

Navigation, searches, filters, pagination, and preview-only actions are not intended to create audit noise.

Default retention:

```env
ACTIVITY_LOG_RETENTION_DAYS=365
ACTIVITY_LOG_PRUNE_BATCH_SIZE=1000
```

Manual pruning:

```bash
php artisan activity-logs:prune
php artisan activity-logs:prune --days=180
```

## Deployment Notes

The production Laravel Dockerfile uses a Node 22 build stage and PHP 8.4-FPM/Nginx/Supervisor runtime.

Build-time Vite/Reverb variables include:

- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`

The FastAPI engine is a separate service and should not be assumed to be private merely because Laravel is the normal caller. Restrict the service at the deployment/network layer before exposing production-only endpoints publicly.

## Known Development Decisions

- Department/role restrictive route middleware is intentionally deferred **during current testing** so one test account can move across Maintenance, Warehouse, Purchase, Operation, and Admin without multiple browser sessions.
- This testing convenience must be reviewed before production access is opened to real users.
- Genuine ML readiness is data-dependent and is not simulated with generated data.
- Optional NLP severity/NER/anomaly/ingestion modules are absent from the current checkout and therefore remain optional/degraded rather than being replaced with fake implementations.
