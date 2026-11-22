# GCT Systems

Fleet and operations management system for GCT Transport Services covering Maintenance, Warehouse, Purchase, Operation, and Administration workflows.

**Production:** https://gct-systems.onrender.com/

## Architecture

GCT Systems uses a dual-engine architecture:

- **Laravel backend** — authentication, business workflows, database access, validation, audit activity, and realtime events.
- **Python FastAPI engine** — lightweight, rule-based NLP, analytics, operation auto-scheduling assistance, and ETA/fuel/delay/inventory prediction APIs. Legacy Python PDF processing is opt-in.
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
| Python Engine | FastAPI, pandas, NumPy, scikit-learn, joblib; optional spaCy / PyMuPDF and development-only PyTorch |
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
- Python 3.12 recommended for parity with CI
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

The FastAPI service mounts analytics, Operation AI assistance, ETA, fuel, inventory, delay and NLP routes. It includes rule-based NER, severity classification, anomaly analysis, and reviewable ingestion modules; these NLP helpers are **present and required for startup readiness**. Their rules must not be presented as trained machine-learning models.

The normal, low-memory Python installation intentionally excludes PyTorch, spaCy, and PyMuPDF. ML models are loaded on demand rather than preloaded at startup; native numerical thread pools default to one thread to reduce memory/CPU pressure on constrained hosting.

For optional legacy Python PDF extraction and spaCy enrichment:

```bash
pip install -r requirements-nlp-extra.txt
```

Use `GCT_ENABLE_PYTHON_PDF=true` to enable `POST /nlp/extract-pdf` (disabled by default). For spaCy enrichment, install the `en_core_web_sm` model separately and set `GCT_ENABLE_SPACY_NLP=true`. Without these flags, rule-based NLP remains available. Legacy synthetic PyTorch scoring is development-only and can be installed through `requirements-legacy-ml.txt` when explicitly required.

**PDF ownership:** The Laravel GPS document-import workflow parses PDF text locally using `pdftotext` and its PHP rules parser. It does not depend on the optional Python PDF endpoint.

The lightweight text endpoint is `POST /nlp/analyze-text`; it accepts JSON with a nonempty `text` field (maximum 20,000 characters) and returns rule-based entity and severity results. It is available in FastAPI, but a Laravel integration for this endpoint should not be assumed.

For a quick status check, visit `GET /health` or `GET /nlp/status`. The separate Python process may use a different port on Render; use the URL configured in Laravel.

### Docker Compose

`compose.yaml` is the Laravel Sail development stack for:

- Laravel
- MySQL

It is **not** a complete container definition for the separate FastAPI and Reverb services. Start those services separately using the commands above, or use their production Render services.

```bash
docker compose up
```

## Predictive Analytics and Model Readiness

The Python engine provides ETA, fuel, delay, inventory, and operation scheduling assistance. Predictions must be interpreted according to their actual data source and model-readiness status. Rule-based results, historical-data fallbacks, and trained machine-learning predictions are distinct; none should be presented as trained or production-ready when they are not.

Delay forecasting uses matched Daily Driver Reports and trip/incident relationships where available. Inventory forecasting requires sufficient genuine stock-movement history. Incomplete history may limit forecasting availability or accuracy.

For a read-only readiness audit while FastAPI is available:

```bash
python_engine/.venv/Scripts/python.exe python_engine/run_comprehensive_ml_audit.py
```

Do not alter training data provenance, bypass readiness checks, or delete model artifacts solely to change how results are described in documentation. Review operational data and model readiness before enabling predictions in production.

## Testing

### Laravel

```bash
php artisan test
```

or:

```bash
composer run test
```

Run the test suite for each release; historical pass counts are not a substitute for current verification.

### Frontend

```bash
npm ci
npm run build
```

### Python

Python model folders contain script-style validation tests where applicable. CI compiles the Python source tree and runs selected readiness, model-policy, training-pipeline and API checks. The lightweight NLP modules are part of the repository; optional spaCy/PyMuPDF dependencies are not required for the default runtime.

## Continuous Integration

`.github/workflows/ci.yml` runs on pull requests and pushes to `main`, `fix/**`, `improve/**`, and `feat/**` branches. It checks:

- Laravel test suite on PHP 8.4 with MySQL 8.4
- Vite production build on Node 22
- Python 3.12 source compilation and selected ML/NLP validation scripts

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

## Repository Branches and History

- `main` is the integration branch for the current GCT application. Avoid rewriting `main` history or pushing unfinished features directly.
- Historical backup branches and tags are maintained separately from the production source. Do not rewrite protected history.

## Known Development Decisions

- Review effective department and role authorization before granting production access. Documentation does not substitute for an RBAC security audit.
- Genuine ML readiness is data-dependent and is not simulated with generated data.
- Rule-based severity, NER, anomaly and ingestion modules exist and are checked at FastAPI startup. spaCy and Python PDF extraction are optional enhancements, not default requirements.
