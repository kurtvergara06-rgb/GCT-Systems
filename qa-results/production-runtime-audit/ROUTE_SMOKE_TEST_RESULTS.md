# Route Smoke Test Results

Audit date: 2026-10-16

## Route inventory

- Registered routes: 208
- GET-capable routes: 89
- Static GET routes: 78
- Parameterized GET routes: 11
- Unresolved conflict markers in relevant source: 0
- Missing/case-mismatched literal Vite sources used by pages: 0

Intentionally protected 403 responses are not classified as failures. Record-detail routes requiring a real model ID, exports, OSRM/location endpoints, broadcast authentication, and the framework storage route were not blindly invoked as anonymous generic GET requests; they are covered by focused feature tests where safe.

## Department page rendering

The complete Laravel suite ran with isolated test-database refreshes and role-appropriate users. The following application page smoke group passed with HTTP 200 assertions:

### Administration

Dashboard; Accounts; Roles & Permissions; Activity Logs; Notifications; Data Import Management; Import/Export; Data History; Analytics Overview; Descriptive; Diagnostic; Predictive; Prescriptive; General Settings; Notification Settings; Security Settings.

### Operation

Dashboard; Routes; Trip Schedule; Driver & Bus Assignment; Auto Scheduling; Driver Master List; Mechanic Master List; Driver Attendance; Mechanic Attendance; Bus Master List; Trip Records; Daily Driver Reports; Incidents.

### Maintenance

Dashboard; Maintenance Referrals; Job Orders; PMS Scheduling; Mechanic Availability; Fuel Reports; Purchase Requests.

### Warehouse

Dashboard; Inventory; Part Requests; Incoming Deliveries; Stock Movements.

### Purchase

Dashboard; Purchase Orders; Maintenance Requests; Inventory Restock; Purchase History; Scheduled Purchase.

Account profile/settings, onboarding, guest login, authenticated-login redirects, shared topbar endpoints, record/detail endpoints, AJAX partials, exports, authorization boundaries, and cross-module workflow endpoints are covered by the wider 613-test suite.

## Results

- `php artisan route:list --json`: PASS (208 routes serialized)
- Database migration status: PASS; all local migrations reported `Ran`
- Final full Laravel suite: PASS — 613 tests, 4,943 assertions, 168.688 seconds
- Hotfix regression suite: PASS — 3 tests, 278 assertions, 14.646 seconds
- Blade view compilation cycle: see final validation log in `PRODUCTION_RUNTIME_AUDIT.md`
- Authenticated compiled-mode browser render: PASS on Operation Dashboard
- Browser console warning/error check: PASS (empty result)

No additional HTTP 500 was observed in the tested route scope. No production URL or production database was exercised.
