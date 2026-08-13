# GCT Systems

GCT Systems is a Laravel-based fleet and operations management system for Maintenance, Warehouse, Purchase, Operation, and Administration workflows.

## Stack

- Laravel 13
- PHP 8.3+ / PHP 8.4 Docker runtime
- MySQL
- Blade templates
- Vanilla JavaScript and CSS
- Vite
- Laravel Reverb / Echo
- Docker / Render

## Main Modules

### Maintenance
Job Orders, PMS Scheduling, Mechanic Availability, Fuel Reports, and Purchase Requests.

### Warehouse
Inventory, Part Requests, Stock Movements, and Incoming Deliveries.

### Purchase
Maintenance Requests, Inventory Restock Requests, Purchase Orders, Scheduled Purchases, and Purchase History.

### Operation
Routes & Stops, Trip Scheduling, Driver & Bus Assignment, Auto Scheduling, Personnel Management, Driver/Mechanic Attendance, Bus Master List, and Trip Records.

### Admin
Account Management, Roles & Permissions, Activity Logs, Notifications, Data Management, Analytics, and Settings.

## Shared Frontend Architecture

Shared Blade components live under `resources/views/components`. Global styles and JavaScript are loaded through `resources/js/app.js`, while page-specific behavior should remain in its module folder.

AJAX-ready regions use `data-ajax-region` and the helper in `resources/js/Main-js/ajax-regions.js`. Realtime system events are delivered through Laravel Reverb/Echo and refresh AJAX-ready regions instead of forcing whole-page reloads.

## Activity Log Policy

Activity Logs are a controlled audit trail, not click history. The system records meaningful mutations such as create, update, delete/deactivate, approve/reject, status changes, assignments, completion, receiving/issuing, imports/uploads, account/security changes, permission changes, login, and logout.

Read-only navigation, searches, filters, pagination, topbar/read-state requests, route calculations, and Auto Scheduling previews are not audit events. A request that is explicitly logged by a controller is marked so the global activity middleware does not create a duplicate record.

Audit records are retained for 365 days by default and pruned daily in small batches. Configure this with:

```env
ACTIVITY_LOG_RETENTION_DAYS=365
ACTIVITY_LOG_PRUNE_BATCH_SIZE=1000
```

The retention value has a 30-day minimum. The pruning command can also be run manually:

```bash
php artisan activity-logs:prune
php artisan activity-logs:prune --days=180
```

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

For the configured development runner:

```bash
composer run dev

```

## Project Conventions

- Reuse shared Blade/UI components instead of duplicating permanent UI.
- Keep Personnel Management separate from daily Attendance.
- Keep page-specific CSS and JavaScript in the relevant module when possible.
- Use JavaScript for actual dynamic behavior rather than hiding obsolete permanent markup.
- Department/role restrictive middleware is intentionally deferred while the system remains under testing.
