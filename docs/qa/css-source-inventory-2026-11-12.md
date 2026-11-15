# CSS and view inventory — 2026-11-12

This is the complete static inventory, not a browser PASS list. CSS parsing includes every file under resources/css. References include Vite declarations, literal Blade/JS/PHP references and resolved relative imports. Dynamic construction, inherited layout assets, conditional permissions and print states still require review. A missing literal reference is not proof of unused CSS.

CSS files: **127**. Blade templates: **161**, including **84** page/layout/standalone documents (including authentication, print and email documents). JavaScript files: **88**.

| Aggregate source metric | Before | After |
| --- | ---: | ---: |
| bytes | 3574002 | 3566620 |
| rules | 23655 | 23548 |
| important | 4068 | 4037 |
| hardColors | 17727 | 17698 |

These are aggregate source counts, not bytes downloaded by each page. Repeated selector groups remain review candidates; many are purposeful state, responsive or cascade refinements. Only verified exact copies and specific obsolete overrides were removed. No stylesheet was deleted wholesale.

## Stylesheets

| File | Lines | Rules | Repeated selector groups | !important | Literal/resolved consumers |
| --- | ---: | ---: | ---: | ---: | --- |
| `resources/css/Account/account.css` | 1694 | 295 | 9 | 1 | `resources/views/Account/personalization.blade.php`<br>`resources/views/Account/preferences.blade.php`<br>`resources/views/Account/profile.blade.php`<br>`resources/views/Account/settings.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/admin-dashboard.css` | 738 | 135 | 0 | 0 | `resources/views/Admin/admin-dashboard.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/administration-standards.css` | 39 | 3 | 0 | 0 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/department-stage.css` | 96 | 39 | 0 | 2 | `resources/views/Admin/Analytics/department-stage.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/descriptive/all.css` | 291 | 93 | 0 | 4 | `vite.config.js` |
| `resources/css/Admin/Analytics/descriptive/bus-health.css` | 646 | 115 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/descriptive/descriptive-rebuild.css` | 771 | 126 | 0 | 6 | `resources/views/Admin/Analytics/descriptive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/descriptive/fleet-trip.css` | 437 | 101 | 0 | 2 | `vite.config.js` |
| `resources/css/Admin/Analytics/descriptive/fuel.css` | 5 | 1 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/descriptive/inventory.css` | 867 | 141 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/design-system.css` | 2203 | 274 | 0 | 16 | `resources/views/Admin/Analytics/department-stage.blade.php`<br>`resources/views/Admin/Analytics/descriptive/layout.blade.php`<br>`resources/views/Admin/Analytics/diagnostic/layout.blade.php`<br>`resources/views/Admin/Analytics/overview.blade.php`<br>`resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`resources/views/Admin/Analytics/prescriptive/layout.blade.php`<br>`resources/views/Admin/Analytics/stage.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/diagnostic/all.css` | 541 | 237 | 28 | 0 | `vite.config.js`<br>`resources/css/Admin/Analytics/diagnostic/bus-health.css`<br>`resources/css/Admin/Analytics/diagnostic/fleet-trip.css`<br>`resources/css/Admin/Analytics/diagnostic/fuel.css`<br>`resources/css/Admin/Analytics/diagnostic/inventory.css` |
| `resources/css/Admin/Analytics/diagnostic/bus-health.css` | 544 | 81 | 0 | 6 | `vite.config.js` |
| `resources/css/Admin/Analytics/diagnostic/diagnostic-rebuild.css` | 651 | 98 | 1 | 2 | `resources/views/Admin/Analytics/diagnostic/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/diagnostic/fleet-trip.css` | 8 | 5 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/diagnostic/fuel.css` | 19 | 15 | 0 | 3 | `vite.config.js` |
| `resources/css/Admin/Analytics/diagnostic/inventory.css` | 631 | 94 | 0 | 6 | `vite.config.js` |
| `resources/css/Admin/Analytics/model-status-stage.css` | 55 | 7 | 0 | 0 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`resources/views/Admin/Analytics/prescriptive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/overview/analytics-stage-hub.css` | 330 | 44 | 1 | 0 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`resources/views/Admin/Analytics/stage.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/overview/descriptive-overview.css` | 362 | 70 | 0 | 0 | `resources/css/Admin/Analytics/overview/analytics-stage-hub.css` |
| `resources/css/Admin/Analytics/overview/fleet-trip-rankings.css` | 650 | 99 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/overview/fleet-trip-redesign.css` | 710 | 144 | 0 | 1 | `vite.config.js` |
| `resources/css/Admin/Analytics/overview/fleet-trip.css` | 1813 | 189 | 0 | 1 | `vite.config.js` |
| `resources/css/Admin/Analytics/overview/live-data.css` | 616 | 85 | 0 | 0 | `resources/views/Admin/Analytics/overview.blade.php`<br>`resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/overview/overview.css` | 1491 | 158 | 0 | 5 | `resources/views/Admin/Analytics/overview.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/predictive/all.css` | 1464 | 204 | 0 | 8 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/predictive/bus-health.css` | 348 | 49 | 0 | 0 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/predictive/delay-model.css` | 61 | 10 | 0 | 0 | `resources/js/Admin/Analytics/predictive/fleet.js` |
| `resources/css/Admin/Analytics/predictive/fleet-trip.css` | 1237 | 189 | 1 | 9 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/predictive/fuel-model.css` | 71 | 11 | 0 | 0 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/predictive/fuel.css` | 518 | 83 | 6 | 0 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/predictive/inventory.css` | 1049 | 156 | 0 | 3 | `resources/views/Admin/Analytics/predictive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/all.css` | 1278 | 176 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/bus-health.css` | 10 | 1 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/fleet-trip.css` | 13 | 2 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/fuel.css` | 13 | 2 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/inventory.css` | 26 | 4 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/prescriptive-rebuild.css` | 792 | 102 | 0 | 2 | `resources/views/Admin/Analytics/prescriptive/layout.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Analytics/prescriptive/readiness.css` | 27 | 4 | 0 | 0 | `vite.config.js` |
| `resources/css/Admin/Data_Management/batch-file-processing.css` | 1939 | 325 | 5 | 3 | `resources/views/Admin/Data_Management/batch-file-processing.blade.php`<br>`resources/views/Admin/Data_Management/generic-batch-review.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Data_Management/data-activity-modal.css` | 263 | 37 | 0 | 0 | `resources/js/Admin/Data_Management/import-export.js` |
| `resources/css/Admin/Data_Management/data-history.css` | 949 | 94 | 0 | 0 | `resources/views/Admin/Data_Management/data-history.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Data_Management/generic-batch-review.css` | 312 | 43 | 0 | 0 | `resources/views/Admin/Data_Management/batch-file-processing.blade.php`<br>`resources/views/Admin/Data_Management/generic-batch-review.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Data_Management/uploading-data.css` | 1122 | 143 | 0 | 0 | `resources/views/Admin/Data_Management/uploading-data.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Settings/general-settings.css` | 1047 | 93 | 0 | 1 | `resources/views/Admin/Settings/general-settings.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Settings/notification-settings.css` | 1189 | 119 | 1 | 1 | `resources/views/Admin/Settings/notification-settings.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/Settings/security-settings.css` | 1338 | 120 | 0 | 1 | `resources/views/Admin/Settings/security-settings.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/System_Monitoring/activity-logs.css` | 527 | 90 | 1 | 1 | `resources/views/Admin/System_Monitoring/activity-logs.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/System_Monitoring/notifications-components.css` | 274 | 62 | 0 | 2 | `resources/views/Admin/System_Monitoring/notifications.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/System_Monitoring/notifications.css` | 896 | 92 | 3 | 5 | `resources/views/Admin/System_Monitoring/notifications.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/User_Management/permissions.css` | 1609 | 433 | 46 | 7 | `resources/views/Admin/User_Management/permissions.blade.php`<br>`vite.config.js` |
| `resources/css/Admin/User_Management/users.css` | 1391 | 281 | 23 | 30 | `resources/views/Admin/User_Management/users.blade.php`<br>`vite.config.js` |
| `resources/css/Login/login.css` | 603 | 116 | 0 | 7 | `resources/views/Login/email-otp-preview.blade.php`<br>`resources/views/Login/email-otp.blade.php`<br>`resources/views/Login/forgot-password.blade.php`<br>`resources/views/Login/google-link.blade.php`<br>`resources/views/Login/google-register.blade.php`<br>`resources/views/Login/login.blade.php`<br>`resources/views/Login/pending-approval.blade.php`<br>`resources/views/Login/register.blade.php`<br>`resources/views/Login/reset-password.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/account-preferences.css` | 326 | 165 | 0 | 10 | `resources/views/Account/personalization.blade.php`<br>`resources/views/Account/preferences.blade.php`<br>`vite.config.js`<br>`resources/js/app.js` |
| `resources/css/Main-styles/admin-records.css` | 479 | 68 | 0 | 22 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/analytics-chart-animations.css` | 153 | 50 | 0 | 1 | `resources/js/app.js` |
| `resources/css/Main-styles/analytics-chart-interactions.css` | 764 | 181 | 0 | 9 | `resources/js/app.js` |
| `resources/css/Main-styles/analytics-domain-panels.css` | 404 | 65 | 0 | 3 | `resources/js/app.js` |
| `resources/css/Main-styles/date-time-picker.css` | 416 | 64 | 0 | 9 | `resources/js/app.js` |
| `resources/css/Main-styles/form-components.css` | 1247 | 106 | 0 | 0 | `resources/css/Maintenance/pms-scheduling.css`<br>`resources/css/Maintenance/purchase-requests.css`<br>`resources/views/components/operation/trip-history-layout.blade.php`<br>`resources/views/Maintenance/item-requests.blade.php`<br>`resources/views/Maintenance/job-order.blade.php`<br>`resources/views/Maintenance/pms-scheduling.blade.php`<br>`resources/views/Maintenance/purchase-requests.blade.php`<br>`resources/views/Maintenance/referrals.blade.php`<br>`resources/views/Operation/Attendance/driver-attendance.blade.php`<br>`resources/views/Operation/Clients/index.blade.php`<br>`resources/views/Operation/Daily_Driver_Reports/index.blade.php`<br>`resources/views/Operation/Incidents/create.blade.php`<br>`resources/views/Operation/Incidents/index.blade.php`<br>`resources/views/Operation/Incidents/show.blade.php`<br>`resources/views/Operation/Personnel Management/driver_master_list.blade.php`<br>`resources/views/Operation/Personnel Management/mechanic_master_list.blade.php`<br>`resources/views/Operation/Routes/routes-stops.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/client-trip-times.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php`<br>`resources/views/Warehouse/inventory.blade.php`<br>`resources/views/Warehouse/item-requests.blade.php`<br>`resources/views/Warehouse/part-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/gct-scrollbar.css` | 262 | 33 | 0 | 4 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/identifier-text.css` | 81 | 3 | 0 | 3 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/main.css` | 2371 | 247 | 0 | 68 | `resources/views/Admin/admin-dashboard.blade.php`<br>`resources/views/Admin/Analytics/department-access.blade.php`<br>`resources/views/Admin/Analytics/department-stage.blade.php`<br>`resources/views/Admin/Settings/general-settings.blade.php`<br>`resources/views/Admin/Settings/notification-settings.blade.php`<br>`resources/views/Admin/Settings/security-settings.blade.php`<br>`resources/views/Admin/System_Monitoring/activity-logs.blade.php`<br>`resources/views/Admin/System_Monitoring/notifications.blade.php`<br>`resources/views/Admin/User_Management/permissions.blade.php`<br>`resources/views/Admin/User_Management/users.blade.php`<br>`resources/views/components/layout/app.blade.php`<br>`resources/views/components/operation/trip-history-layout.blade.php`<br>`resources/views/Maintenance/fuel-reports.blade.php`<br>`resources/views/Maintenance/item-requests.blade.php`<br>`resources/views/Maintenance/job-order.blade.php`<br>`resources/views/Maintenance/maintenance-dashboard.blade.php`<br>`resources/views/Maintenance/mechanic-list.blade.php`<br>`resources/views/Maintenance/pms-scheduling.blade.php`<br>`resources/views/Maintenance/purchase-requests.blade.php`<br>`resources/views/Maintenance/referrals.blade.php`<br>`resources/views/Operation/Attendance/driver-attendance.blade.php`<br>`resources/views/Operation/Attendance/mechanic-attendance.blade.php`<br>`resources/views/Operation/Daily_Driver_Reports/index.blade.php`<br>`resources/views/Operation/dashboard-operation.blade.php`<br>`resources/views/Operation/Incidents/create.blade.php`<br>`resources/views/Operation/Incidents/index.blade.php`<br>`resources/views/Operation/Incidents/show.blade.php`<br>`resources/views/Operation/Personnel Management/driver_master_list.blade.php`<br>`resources/views/Operation/Personnel Management/mechanic_master_list.blade.php`<br>`resources/views/Operation/Routes/routes-stops.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/client-trip-times.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php`<br>`resources/views/Purchase/dashboard-purchase.blade.php`<br>`resources/views/Purchase/purchase-history.blade.php`<br>`resources/views/Purchase/purchase-orders.blade.php`<br>`resources/views/Purchase/Requested_Purchase/inventory-restock.blade.php`<br>`resources/views/Purchase/Requested_Purchase/maintenance-requests.blade.php`<br>`resources/views/Warehouse/dashboard-warehouse.blade.php`<br>`resources/views/Warehouse/incoming-deliveries.blade.php`<br>`resources/views/Warehouse/inventory-movements.blade.php`<br>`resources/views/Warehouse/inventory.blade.php`<br>`resources/views/Warehouse/item-requests.blade.php`<br>`resources/views/Warehouse/part-requests.blade.php`<br>`resources/views/Warehouse/stock-movements.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/page-transitions.css` | 197 | 24 | 0 | 14 | `resources/js/app.js` |
| `resources/css/Main-styles/pr-details-modal.css` | 83 | 74 | 0 | 1 | `resources/views/Purchase/Requested_Purchase/maintenance-requests.blade.php`<br>`resources/views/Warehouse/part-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/record-action-buttons.css` | 380 | 40 | 0 | 93 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/scroll-record-lists.css` | 44 | 8 | 0 | 0 | `resources/js/app.js` |
| `resources/css/Main-styles/searchable-select.css` | 196 | 24 | 0 | 14 | `resources/js/app.js` |
| `resources/css/Main-styles/shared-ui-enhancements.css` | 727 | 71 | 0 | 135 | `resources/js/app.js` |
| `resources/css/Main-styles/sidebar-state.css` | 45 | 5 | 0 | 10 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/sidebar.css` | 1434 | 128 | 0 | 5 | `resources/views/Admin/admin-dashboard.blade.php`<br>`resources/views/Admin/Analytics/department-access.blade.php`<br>`resources/views/Admin/Analytics/department-stage.blade.php`<br>`resources/views/Admin/Settings/general-settings.blade.php`<br>`resources/views/Admin/Settings/notification-settings.blade.php`<br>`resources/views/Admin/Settings/security-settings.blade.php`<br>`resources/views/Admin/System_Monitoring/activity-logs.blade.php`<br>`resources/views/Admin/System_Monitoring/notifications.blade.php`<br>`resources/views/Admin/User_Management/permissions.blade.php`<br>`resources/views/Admin/User_Management/users.blade.php`<br>`resources/views/components/layout/app.blade.php`<br>`resources/views/components/operation/trip-history-layout.blade.php`<br>`resources/views/Maintenance/fuel-reports.blade.php`<br>`resources/views/Maintenance/item-requests.blade.php`<br>`resources/views/Maintenance/job-order.blade.php`<br>`resources/views/Maintenance/maintenance-dashboard.blade.php`<br>`resources/views/Maintenance/mechanic-list.blade.php`<br>`resources/views/Maintenance/pms-scheduling.blade.php`<br>`resources/views/Maintenance/purchase-requests.blade.php`<br>`resources/views/Maintenance/referrals.blade.php`<br>`resources/views/Operation/Attendance/driver-attendance.blade.php`<br>`resources/views/Operation/Attendance/mechanic-attendance.blade.php`<br>`resources/views/Operation/Daily_Driver_Reports/index.blade.php`<br>`resources/views/Operation/dashboard-operation.blade.php`<br>`resources/views/Operation/Incidents/create.blade.php`<br>`resources/views/Operation/Incidents/index.blade.php`<br>`resources/views/Operation/Incidents/show.blade.php`<br>`resources/views/Operation/Personnel Management/driver_master_list.blade.php`<br>`resources/views/Operation/Personnel Management/mechanic_master_list.blade.php`<br>`resources/views/Operation/Routes/routes-stops.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/client-trip-times.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php`<br>`resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php`<br>`resources/views/Purchase/dashboard-purchase.blade.php`<br>`resources/views/Purchase/purchase-history.blade.php`<br>`resources/views/Purchase/purchase-orders.blade.php`<br>`resources/views/Purchase/Requested_Purchase/inventory-restock.blade.php`<br>`resources/views/Purchase/Requested_Purchase/maintenance-requests.blade.php`<br>`resources/views/Warehouse/dashboard-warehouse.blade.php`<br>`resources/views/Warehouse/incoming-deliveries.blade.php`<br>`resources/views/Warehouse/inventory-movements.blade.php`<br>`resources/views/Warehouse/item-requests.blade.php`<br>`resources/views/Warehouse/part-requests.blade.php`<br>`resources/views/Warehouse/stock-movements.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/spinner.css` | 81 | 13 | 0 | 2 | `resources/js/app.js` |
| `resources/css/Main-styles/system-toast.css` | 117 | 15 | 0 | 0 | `resources/js/app.js` |
| `resources/css/Main-styles/table-toolbar.css` | 19 | 41 | 0 | 7 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/theme.css` | 765 | 69 | 1 | 36 | `resources/views/components/layout/app.blade.php`<br>`resources/views/Login/email-otp-preview.blade.php`<br>`resources/views/Login/email-otp.blade.php`<br>`resources/views/Login/forgot-password.blade.php`<br>`resources/views/Login/google-link.blade.php`<br>`resources/views/Login/google-register.blade.php`<br>`resources/views/Login/login.blade.php`<br>`resources/views/Login/pending-approval.blade.php`<br>`resources/views/Login/register.blade.php`<br>`resources/views/Login/reset-password.blade.php`<br>`vite.config.js` |
| `resources/css/Main-styles/toolbar-cleanup.css` | 57 | 6 | 0 | 3 | `resources/js/app.js` |
| `resources/css/Main-styles/topbar.css` | 24 | 43 | 0 | 0 | `resources/js/app.js` |
| `resources/css/Maintenance/fuel-reports-refinement.css` | 209 | 31 | 0 | 0 | `resources/js/Maintenance/fuel-reports-refinement.js` |
| `resources/css/Maintenance/fuel-reports.css` | 3716 | 556 | 57 | 41 | `resources/views/Maintenance/fuel-reports.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/item-requests.css` | 1384 | 740 | 72 | 98 | `resources/views/Maintenance/item-requests.blade.php`<br>`resources/views/Warehouse/item-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/job-order.css` | 7467 | 1822 | 192 | 459 | `resources/views/Maintenance/job-order.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/maintenance-dashboard.css` | 1375 | 226 | 0 | 5 | `resources/views/Maintenance/maintenance-dashboard.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/maintenance-ui-enhancements.css` | 235 | 31 | 0 | 2 | `resources/js/app.js` |
| `resources/css/Maintenance/mechanic-availability-enhancement.css` | 247 | 34 | 0 | 0 | `resources/views/Maintenance/mechanic-list.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/mechanic-list.css` | 892 | 70 | 0 | 4 | `resources/views/Maintenance/mechanic-list.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/pms-scheduling.css` | 2153 | 273 | 6 | 41 | `resources/views/Maintenance/pms-scheduling.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/purchase-request-modal-cleanup.css` | 77 | 13 | 0 | 10 | `resources/js/app.js` |
| `resources/css/Maintenance/purchase-requests.css` | 5885 | 1106 | 164 | 338 | `resources/views/Maintenance/purchase-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/referrals.css` | 335 | 86 | 0 | 0 | `resources/views/Maintenance/referrals.blade.php`<br>`vite.config.js` |
| `resources/css/Maintenance/request-modal-shared.css` | 320 | 43 | 0 | 46 | `resources/views/Maintenance/item-requests.blade.php`<br>`resources/views/Maintenance/purchase-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Attendance/available-mechanics.css` | 609 | 61 | 0 | 0 | `resources/views/Operation/Attendance/mechanic-attendance.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Attendance/batch-attendance.css` | 511 | 214 | 6 | 6 | `resources/views/Operation/Attendance/mechanic-attendance.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Attendance/driver-attendance.css` | 1737 | 205 | 10 | 5 | `resources/views/Operation/Attendance/driver-attendance.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Attendance/personnel-master.css` | 2669 | 561 | 49 | 32 | `resources/views/Operation/Personnel Management/driver_master_list.blade.php`<br>`resources/views/Operation/Personnel Management/mechanic_master_list.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Clients/client-master-list.css` | 501 | 149 | 2 | 4 | `resources/views/Operation/Clients/index.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Daily_Driver_Reports/daily-driver-reports.css` | 2602 | 439 | 28 | 8 | `resources/views/Operation/Daily_Driver_Reports/index.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/dashboard-operation.css` | 4087 | 618 | 93 | 17 | `resources/views/Operation/dashboard-operation.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Incidents/incidents.css` | 3924 | 774 | 19 | 26 | `resources/views/Operation/Incidents/create.blade.php`<br>`resources/views/Operation/Incidents/index.blade.php`<br>`resources/views/Operation/Incidents/show.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/operation-action-buttons.css` | 407 | 28 | 0 | 42 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/operation-dark-mode.css` | 1006 | 123 | 1 | 0 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Routes/route-pin-enhancements.css` | 141 | 18 | 1 | 0 | `resources/views/Operation/Routes/routes-stops.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Routes/routes-stops.css` | 3902 | 531 | 41 | 6 | `resources/views/Operation/Routes/routes-stops.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/assignment-calendar.css` | 57 | 56 | 0 | 2 | `resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-calendar.css` | 59 | 64 | 0 | 2 | `resources/views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 5561 | 1023 | 131 | 207 | `resources/views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/client-trip-times.css` | 2425 | 400 | 65 | 3 | `resources/views/Operation/Scheduling_And_Dispatch/client-trip-times.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css` | 2683 | 523 | 42 | 22 | `resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-calendar-status.css` | 78 | 17 | 0 | 0 | `resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 4145 | 648 | 39 | 22 | `resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-toolbar-alignment.css` | 54 | 10 | 0 | 0 | `resources/js/Operation/Scheduling_And_Dispatch/trip-toolbar-alignment.js` |
| `resources/css/Operation/Shuttle_Bus_Management/bus-availability.css` | 256 | 142 | 6 | 15 | `resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css` | 3017 | 606 | 28 | 72 | `resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php`<br>`resources/views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php`<br>`vite.config.js` |
| `resources/css/Operation/Trip_Records/trip-records.css` | 3004 | 467 | 21 | 13 | `resources/views/components/operation/trip-history-layout.blade.php`<br>`vite.config.js` |
| `resources/css/Purchase/dashboard-purchase.css` | 1790 | 219 | 2 | 74 | `resources/views/Purchase/dashboard-purchase.blade.php`<br>`vite.config.js` |
| `resources/css/Purchase/purchase-history.css` | 264 | 31 | 0 | 11 | `resources/views/Purchase/purchase-history.blade.php`<br>`vite.config.js` |
| `resources/css/Purchase/purchase-module-ui.css` | 1814 | 281 | 2 | 354 | `resources/views/Purchase/dashboard-purchase.blade.php`<br>`resources/views/Purchase/purchase-history.blade.php`<br>`resources/views/Purchase/purchase-orders.blade.php`<br>`resources/views/Purchase/Requested_Purchase/inventory-restock.blade.php`<br>`resources/views/Purchase/Requested_Purchase/maintenance-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Purchase/purchase-orders.css` | 1614 | 355 | 36 | 337 | `resources/views/Purchase/purchase-orders.blade.php`<br>`vite.config.js` |
| `resources/css/Purchase/Requested_Purchase/inventory-restock.css` | 1254 | 211 | 12 | 102 | `resources/views/Purchase/Requested_Purchase/inventory-restock.blade.php`<br>`vite.config.js` |
| `resources/css/Purchase/Requested_Purchase/maintenance-requests.css` | 1993 | 350 | 39 | 310 | `resources/views/Purchase/purchase-history.blade.php`<br>`resources/views/Purchase/Requested_Purchase/maintenance-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 3568 | 611 | 93 | 180 | `resources/views/Warehouse/dashboard-warehouse.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/incoming-deliveries.css` | 844 | 158 | 5 | 93 | `resources/views/Warehouse/incoming-deliveries.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/inventory.css` | 1036 | 265 | 24 | 135 | `resources/views/Warehouse/inventory.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/item-requests.css` | 814 | 405 | 52 | 177 | `resources/views/Warehouse/item-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/part-requests-cleanup.css` | 1660 | 368 | 12 | 90 | `resources/views/Warehouse/part-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/part-requests.css` | 845 | 268 | 5 | 13 | `resources/views/Warehouse/part-requests.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/stock-movements.css` | 767 | 79 | 1 | 15 | `resources/views/Warehouse/inventory-movements.blade.php`<br>`resources/views/Warehouse/stock-movements.blade.php`<br>`vite.config.js` |
| `resources/css/Warehouse/warehouse-status.css` | 116 | 6 | 0 | 14 | `resources/views/components/layout/app.blade.php`<br>`vite.config.js` |

## Templates and declared page assets

Shared layout assets are inherited and are not repeated below. Standalone documents are not necessarily navigable application pages. Legacy Analytics views are inventoried rather than assumed dead.

| Template | Classification | Declared CSS/JS assets | Canvas | Inline style attributes / style blocks |
| --- | --- | --- | --- | ---: |
| `resources/views/Account/personalization.blade.php` | page-layout-or-standalone-document | `resources/css/Account/account.css`<br>`resources/css/Main-styles/account-preferences.css` | No | 0 / 0 |
| `resources/views/Account/preferences.blade.php` | page-layout-or-standalone-document | `resources/css/Account/account.css`<br>`resources/css/Main-styles/account-preferences.css` | No | 0 / 0 |
| `resources/views/Account/profile.blade.php` | page-layout-or-standalone-document | `resources/css/Account/account.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Account/account.js` | No | 2 / 1 |
| `resources/views/Account/settings.blade.php` | page-layout-or-standalone-document | `resources/css/Account/account.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Account/account.js` | No | 1 / 0 |
| `resources/views/Admin/admin-dashboard.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/admin-dashboard.css`<br>`resources/js/Admin/admin-dashboard.js` | Yes | 0 / 0 |
| `resources/views/Admin/Analytics/department-access.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css` | No | 5 / 0 |
| `resources/views/Admin/Analytics/department-stage.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/Analytics/design-system.css`<br>`resources/css/Admin/Analytics/department-stage.css`<br>`resources/js/Admin/Analytics/department-stage.js` | Yes | 1 / 0 |
| `resources/views/Admin/Analytics/descriptive/all.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 4 / 0 |
| `resources/views/Admin/Analytics/descriptive/bus-health.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 4 / 0 |
| `resources/views/Admin/Analytics/descriptive/fleet-trip.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 5 / 0 |
| `resources/views/Admin/Analytics/descriptive/fuel.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 6 / 1 |
| `resources/views/Admin/Analytics/descriptive/inventory.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 4 / 0 |
| `resources/views/Admin/Analytics/descriptive/layout.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Analytics/descriptive/descriptive-rebuild.css`<br>`resources/css/Admin/Analytics/design-system.css`<br>`resources/js/Admin/Analytics/descriptive/descriptive-analytics.js` | Yes | 9 / 0 |
| `resources/views/Admin/Analytics/diagnostic/all.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 4 / 0 |
| `resources/views/Admin/Analytics/diagnostic/bus-health.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 3 / 0 |
| `resources/views/Admin/Analytics/diagnostic/fleet-trip.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 7 / 0 |
| `resources/views/Admin/Analytics/diagnostic/fuel.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 5 / 0 |
| `resources/views/Admin/Analytics/diagnostic/inventory.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 3 / 0 |
| `resources/views/Admin/Analytics/diagnostic/layout.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Analytics/diagnostic/diagnostic-rebuild.css`<br>`resources/css/Admin/Analytics/design-system.css`<br>`resources/js/Admin/Analytics/diagnostic/diagnostic-analytics.js` | Yes | 17 / 0 |
| `resources/views/Admin/Analytics/overview.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Analytics/overview/overview.css`<br>`resources/css/Admin/Analytics/overview/live-data.css`<br>`resources/css/Admin/Analytics/design-system.css` | No | 1 / 0 |
| `resources/views/Admin/Analytics/predictive/all.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 11 / 0 |
| `resources/views/Admin/Analytics/predictive/bus-health.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 11 / 0 |
| `resources/views/Admin/Analytics/predictive/fleet-trip.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 9 / 0 |
| `resources/views/Admin/Analytics/predictive/fuel.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 13 / 0 |
| `resources/views/Admin/Analytics/predictive/inventory-ml.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 12 / 0 |
| `resources/views/Admin/Analytics/predictive/inventory.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 12 / 0 |
| `resources/views/Admin/Analytics/predictive/layout.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Analytics/predictive/all.css`<br>`resources/css/Admin/Analytics/predictive/fleet-trip.css`<br>`resources/css/Admin/Analytics/predictive/fuel.css`<br>`resources/css/Admin/Analytics/predictive/bus-health.css`<br>`resources/css/Admin/Analytics/predictive/inventory.css`<br>`resources/css/Admin/Analytics/overview/analytics-stage-hub.css`<br>`resources/css/Admin/Analytics/overview/live-data.css`<br>`resources/css/Admin/Analytics/model-status-stage.css`<br>`resources/css/Admin/Analytics/predictive/fuel-model.css`<br>`resources/css/Admin/Analytics/design-system.css`<br>`resources/js/Admin/Analytics/predictive/fleet.js`<br>`resources/js/Admin/Analytics/predictive/charts.js`<br>`resources/js/Admin/Analytics/predictive/fuel-model.js` | No | 0 / 0 |
| `resources/views/Admin/Analytics/prescriptive/all.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 9 / 0 |
| `resources/views/Admin/Analytics/prescriptive/bus-health.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 0 / 0 |
| `resources/views/Admin/Analytics/prescriptive/fleet-trip.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 0 / 0 |
| `resources/views/Admin/Analytics/prescriptive/fuel.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 0 / 0 |
| `resources/views/Admin/Analytics/prescriptive/inventory.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 0 / 0 |
| `resources/views/Admin/Analytics/prescriptive/layout.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Analytics/prescriptive/prescriptive-rebuild.css`<br>`resources/css/Admin/Analytics/model-status-stage.css`<br>`resources/css/Admin/Analytics/design-system.css`<br>`resources/js/Admin/Analytics/prescriptive/prescriptive-analytics.js` | Yes | 38 / 0 |
| `resources/views/Admin/Analytics/stage.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Analytics/overview/analytics-stage-hub.css`<br>`resources/css/Admin/Analytics/design-system.css` | No | 0 / 0 |
| `resources/views/Admin/Data_Management/batch-file-processing.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Data_Management/batch-file-processing.css`<br>`resources/css/Admin/Data_Management/generic-batch-review.css`<br>`resources/js/Admin/Data_Management/batch-file-processing.js` | No | 1 / 0 |
| `resources/views/Admin/Data_Management/data-history.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Data_Management/data-history.css` | No | 9 / 0 |
| `resources/views/Admin/Data_Management/generic-batch-review.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Data_Management/batch-file-processing.css`<br>`resources/css/Admin/Data_Management/generic-batch-review.css` | No | 0 / 0 |
| `resources/views/Admin/Data_Management/partials/batch-file-processing-workspace.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 2 / 0 |
| `resources/views/Admin/Data_Management/partials/generic-batch-review-modal.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Admin/Data_Management/partials/generic-processed-workspace.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Admin/Data_Management/uploading-data.blade.php` | page-layout-or-standalone-document | `resources/css/Admin/Data_Management/uploading-data.css` | No | 1 / 0 |
| `resources/views/Admin/Settings/general-settings.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/Settings/general-settings.css`<br>`resources/js/Main-js/sidebar.js` | No | 0 / 0 |
| `resources/views/Admin/Settings/notification-settings.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/Settings/notification-settings.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Admin/Settings/notification-settings.js` | No | 0 / 0 |
| `resources/views/Admin/Settings/security-settings.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/Settings/security-settings.css`<br>`resources/js/Main-js/sidebar.js` | No | 0 / 0 |
| `resources/views/Admin/System_Monitoring/activity-logs.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/System_Monitoring/activity-logs.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Admin/System_Monitoring/activity-logs.js` | No | 0 / 0 |
| `resources/views/Admin/System_Monitoring/notifications.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/System_Monitoring/notifications.css`<br>`resources/css/Admin/System_Monitoring/notifications-components.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Admin/System_Monitoring/notifications.js` | No | 0 / 0 |
| `resources/views/Admin/User_Management/applications.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Admin/User_Management/permissions.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/User_Management/permissions.css`<br>`resources/js/Admin/User_Management/permissions.js` | No | 0 / 0 |
| `resources/views/Admin/User_Management/users.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Admin/User_Management/users.css`<br>`resources/js/Admin/User_Management/users.js` | No | 0 / 0 |
| `resources/views/components/admin/notification-item.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/admin/role-directory.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 1 / 0 |
| `resources/views/components/admin/role-permission-panel.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 2 / 0 |
| `resources/views/components/analytics/availability-dashboard.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/analytics/card-header.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/analytics/card.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/analytics/horizontal-bars.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 1 / 0 |
| `resources/views/components/analytics/insight-toast.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/analytics/kpi.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 1 / 0 |
| `resources/views/components/analytics/line-chart.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/analytics/model-status.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/analytics/stage-controls.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/layout/app.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/sidebar-state.css`<br>`resources/css/Main-styles/identifier-text.css`<br>`resources/css/Main-styles/admin-records.css`<br>`resources/css/Main-styles/table-toolbar.css`<br>`resources/js/Main-js/partial-navigation.js`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Main-js/confirmation-modal.js`<br>`resources/js/app.js`<br>`resources/css/Operation/operation-action-buttons.css`<br>`resources/css/Warehouse/warehouse-status.css`<br>`resources/css/Main-styles/record-action-buttons.css`<br>`resources/css/Main-styles/gct-scrollbar.css`<br>`resources/css/Admin/administration-standards.css`<br>`resources/css/Operation/operation-dark-mode.css` | No | 0 / 0 |
| `resources/views/components/layout/sidebar.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/layout/topbar.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/operation/toolbar-popover.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/operation/trip-history-layout.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Trip_Records/trip-records.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Trip_Records/trip-records.js` | No | 0 / 0 |
| `resources/views/components/ui/action-buttom-modal.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 1 |
| `resources/views/components/ui/action-button.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/ajax-region.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/analytics-insight.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/chart-card.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | Yes | 1 / 0 |
| `resources/views/components/ui/chart-data.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/empty-row.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/empty-state.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/file-thumbnail.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/form-actions.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/form-field.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/form-modal.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/form-section.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/form-select.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/id-badge.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/pr-details-modal.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/pr-summary.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/record-identity.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/record-reference.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/section-header.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/spinner.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/status-badge.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/summary-card.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/system-toast.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/table-action.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/table-footer.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/table-toolbar-field.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/components/ui/table-toolbar.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/emails/account-email-change.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 15 / 0 |
| `resources/views/emails/password-reset.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 15 / 0 |
| `resources/views/emails/registration-otp.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 14 / 0 |
| `resources/views/errors/403.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/errors/404.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/errors/419.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/errors/500.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/errors/503.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/errors/layout.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 1 |
| `resources/views/Login/email-change-result.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 1 |
| `resources/views/Login/email-otp-preview.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 2 / 1 |
| `resources/views/Login/email-otp.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 1 |
| `resources/views/Login/forgot-password.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 0 |
| `resources/views/Login/google-link.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 1 |
| `resources/views/Login/google-register.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 1 |
| `resources/views/Login/login.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css`<br>`resources/js/Login/login.js` | No | 1 / 0 |
| `resources/views/Login/pending-approval.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 1 |
| `resources/views/Login/register.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 0 |
| `resources/views/Login/reset-password.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/theme.css`<br>`resources/css/Login/login.css` | No | 1 / 0 |
| `resources/views/Maintenance/fuel-reports.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Maintenance/fuel-reports.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Maintenance/fuel-reports.js` | No | 0 / 0 |
| `resources/views/Maintenance/item-requests.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Maintenance/item-requests.css`<br>`resources/css/Maintenance/request-modal-shared.css`<br>`resources/js/Maintenance/item-requests.js` | No | 7 / 0 |
| `resources/views/Maintenance/job-order.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Maintenance/job-order.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Maintenance/job-order.js` | No | 2 / 0 |
| `resources/views/Maintenance/maintenance-dashboard.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Maintenance/maintenance-dashboard.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Maintenance/maintenance-dashboard.js` | Yes | 3 / 0 |
| `resources/views/Maintenance/mechanic-list.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Maintenance/mechanic-list.css`<br>`resources/css/Maintenance/mechanic-availability-enhancement.css`<br>`resources/js/Main-js/sidebar.js` | No | 1 / 0 |
| `resources/views/Maintenance/partials/job-order-form-sections.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Maintenance/pms-scheduling.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Maintenance/pms-scheduling.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Maintenance/pms-scheduling.js` | No | 5 / 0 |
| `resources/views/Maintenance/purchase-requests.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Maintenance/purchase-requests.css`<br>`resources/css/Maintenance/request-modal-shared.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Maintenance/purchase-requests.js` | No | 2 / 0 |
| `resources/views/Maintenance/referrals.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Maintenance/referrals.css`<br>`resources/js/Main-js/sidebar.js` | No | 1 / 0 |
| `resources/views/Onboarding/welcome.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 7 / 1 |
| `resources/views/Operation/Attendance/driver-attendance.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Attendance/driver-attendance.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Attendance/driver-attendance.js` | No | 6 / 0 |
| `resources/views/Operation/Attendance/mechanic-attendance.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Operation/Attendance/available-mechanics.css`<br>`resources/css/Operation/Attendance/batch-attendance.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Attendance/mechanic-attendance.js`<br>`resources/js/Operation/Attendance/batch-attendance.js` | No | 9 / 1 |
| `resources/views/Operation/Clients/index.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Clients/client-master-list.css`<br>`resources/js/Operation/Clients/client-master-list.js` | No | 0 / 0 |
| `resources/views/Operation/Daily_Driver_Reports/index.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Daily_Driver_Reports/daily-driver-reports.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Daily_Driver_Reports/daily-driver-reports.js` | No | 11 / 0 |
| `resources/views/Operation/dashboard-operation.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Operation/dashboard-operation.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/dashboard-operation.js` | Yes | 6 / 0 |
| `resources/views/Operation/Incidents/create.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Incidents/incidents.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Incidents/incidents.js` | No | 1 / 0 |
| `resources/views/Operation/Incidents/index.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Incidents/incidents.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Incidents/incidents.js` | No | 4 / 0 |
| `resources/views/Operation/Incidents/partials/embedded-workflow.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 3 / 0 |
| `resources/views/Operation/Incidents/show.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Incidents/incidents.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Incidents/incidents.js` | No | 11 / 1 |
| `resources/views/Operation/Incidents/_form-fields.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Operation/Personnel Management/driver_master_list.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Attendance/personnel-master.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Attendance/personnel-master-modal.js` | No | 0 / 0 |
| `resources/views/Operation/Personnel Management/mechanic_master_list.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Attendance/personnel-master.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Attendance/personnel-master-modal.js` | No | 0 / 0 |
| `resources/views/Operation/Routes/routes-stops.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Routes/routes-stops.css`<br>`resources/css/Operation/Routes/route-pin-enhancements.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Routes/routes-stops.js` | No | 2 / 0 |
| `resources/views/Operation/Scheduling_And_Dispatch/assignment-print.blade.php` | page-layout-or-standalone-document | Inherited, standalone or dynamic; inspect parent/controller | No | 8 / 1 |
| `resources/views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/auto-calendar.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Scheduling_And_Dispatch/auto-scheduling.js`<br>`resources/js/Operation/Scheduling_And_Dispatch/auto-calendar.js` | No | 5 / 0 |
| `resources/views/Operation/Scheduling_And_Dispatch/client-trip-times.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/client-trip-times.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Scheduling_And_Dispatch/client-trip-times.js` | No | 1 / 0 |
| `resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/assignment-calendar.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js`<br>`resources/js/Operation/Scheduling_And_Dispatch/assignment-calendar.js` | No | 5 / 0 |
| `resources/views/Operation/Scheduling_And_Dispatch/partials/client-trip-time-panel.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Operation/Scheduling_And_Dispatch/partials/trip-schedule-filters.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css`<br>`resources/css/Operation/Scheduling_And_Dispatch/trip-calendar-status.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Scheduling_And_Dispatch/trip-schedule.js` | No | 4 / 0 |
| `resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css`<br>`resources/css/Operation/Shuttle_Bus_Management/bus-availability.css`<br>`resources/js/Main-js/sidebar.js` | No | 2 / 0 |
| `resources/views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Operation/Shuttle_Bus_Management/bus-master-list.js` | No | 0 / 0 |
| `resources/views/Operation/Trip_Records/assignment-history.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 2 / 0 |
| `resources/views/Operation/Trip_Records/trip-records.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 7 / 0 |
| `resources/views/Purchase/dashboard-purchase.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Purchase/dashboard-purchase.css`<br>`resources/css/Purchase/purchase-module-ui.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Purchase/dashboard-purchase.js` | Yes | 2 / 0 |
| `resources/views/Purchase/purchase-history.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Purchase/Requested_Purchase/maintenance-requests.css`<br>`resources/css/Purchase/purchase-module-ui.css`<br>`resources/css/Purchase/purchase-history.css`<br>`resources/js/Purchase/Requested_Purchase/maintenance-requests.js` | No | 8 / 0 |
| `resources/views/Purchase/purchase-orders.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Purchase/purchase-orders.css`<br>`resources/css/Purchase/purchase-module-ui.css`<br>`resources/js/Purchase/purchase-orders.js` | Yes | 4 / 2 |
| `resources/views/Purchase/Requested_Purchase/inventory-restock.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Purchase/purchase-module-ui.css`<br>`resources/css/Purchase/Requested_Purchase/inventory-restock.css`<br>`resources/js/Purchase/Requested_Purchase/inventory-restock.js` | No | 0 / 0 |
| `resources/views/Purchase/Requested_Purchase/maintenance-requests.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/pr-details-modal.css`<br>`resources/css/Purchase/purchase-module-ui.css`<br>`resources/css/Purchase/Requested_Purchase/maintenance-requests.css`<br>`resources/js/Purchase/Requested_Purchase/maintenance-requests.js` | No | 0 / 0 |
| `resources/views/Warehouse/dashboard-warehouse.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Warehouse/dashboard-warehouse.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Warehouse/dashboard-warehouse.js` | Yes | 1 / 0 |
| `resources/views/Warehouse/incoming-deliveries.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Warehouse/incoming-deliveries.css`<br>`resources/js/Main-js/sidebar.js`<br>`resources/js/Warehouse/incoming-deliveries.js` | No | 1 / 0 |
| `resources/views/Warehouse/inventory-movements.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Warehouse/stock-movements.css`<br>`resources/js/Main-js/sidebar.js` | No | 0 / 0 |
| `resources/views/Warehouse/inventory.blade.php` | page-layout-or-standalone-document | `resources/css/Warehouse/inventory.css`<br>`resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/js/Warehouse/inventory.js` | No | 0 / 0 |
| `resources/views/Warehouse/item-requests.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Maintenance/item-requests.css`<br>`resources/css/Warehouse/item-requests.css`<br>`resources/js/Warehouse/item-requests.js` | No | 2 / 0 |
| `resources/views/Warehouse/part-request-table-body.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Warehouse/part-requests.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Main-styles/form-components.css`<br>`resources/css/Main-styles/pr-details-modal.css`<br>`resources/css/Warehouse/part-requests.css`<br>`resources/css/Warehouse/part-requests-cleanup.css`<br>`resources/js/Warehouse/part-requests.js`<br>`resources/js/Warehouse/part-requests-cleanup.js` | Yes | 2 / 0 |
| `resources/views/Warehouse/partials/inventory-movement-history.blade.php` | partial-or-component | Inherited, standalone or dynamic; inspect parent/controller | No | 0 / 0 |
| `resources/views/Warehouse/stock-movements.blade.php` | page-layout-or-standalone-document | `resources/css/Main-styles/main.css`<br>`resources/css/Main-styles/sidebar.css`<br>`resources/css/Warehouse/stock-movements.css`<br>`resources/js/Main-js/sidebar.js` | No | 0 / 0 |

## GET route inventory

Includes data endpoints and redirects, so this table is not a count of frontend pages. Dynamic record routes were not exercised with invented IDs. Middleware/permissions were preserved.

| URI | Name | Action |
| --- | --- | --- |
| `/` | `login` | `App\Http\Controllers\Auth\LoginController@show` |
| `account/email-change/result` | `account.email-change.result` | `App\Http\Controllers\Auth\AccountEmailChangeController@result` |
| `account/email-change/{id}/{token}` | `account.email-change.verify` | `App\Http\Controllers\Auth\AccountEmailChangeController@verify` |
| `account/personalization` | `account.personalization` | `App\Http\Controllers\AccountController@personalization` |
| `account/preferences` | `account.preferences` | `App\Http\Controllers\AccountController@preferences` |
| `account/profile` | `account.profile` | `App\Http\Controllers\AccountController@profile` |
| `account/settings` | `account.settings` | `App\Http\Controllers\AccountController@settings` |
| `admin/activity-logs` | `admin.activity-logs` | `Illuminate\Routing\ViewController` |
| `admin/applications` | `admin.applications` | `App\Http\Controllers\Admin\AdminUserController@index` |
| `admin/batch-file-processing` | `admin.batch-file-processing` | `App\Http\Controllers\Admin\BatchFileProcessingController@index` |
| `admin/dashboard` | `admin.dashboard` | `Illuminate\Routing\ViewController` |
| `admin/data-history` | `admin.data-history` | `App\Http\Controllers\Admin\DataHistoryController@index` |
| `admin/data-history/{activity}` | `admin.data-history.show` | `App\Http\Controllers\Admin\DataHistoryController@show` |
| `admin/import-export` | `admin.import-export` | `Illuminate\Routing\RedirectController` |
| `admin/import-export/activity/{activity}` | `admin.import-export.activity.details` | `App\Http\Controllers\Admin\StructuredImportReviewController@details` |
| `admin/import-export/recent-activities` | `admin.import-export.activities.recent` | `App\Http\Controllers\Admin\TransferActivityController@recent` |
| `admin/notifications` | `admin.notifications` | `Illuminate\Routing\ViewController` |
| `admin/roles-permissions` | `admin.roles-permissions` | `Illuminate\Routing\ViewController` |
| `admin/settings/general` | `admin.settings.general` | `Illuminate\Routing\ViewController` |
| `admin/settings/notifications` | `admin.settings.notifications` | `Illuminate\Routing\ViewController` |
| `admin/settings/security` | `admin.settings.security` | `Illuminate\Routing\ViewController` |
| `admin/users` | `admin.users` | `App\Http\Controllers\Admin\AdminUserController@index` |
| `analytics` | `analytics` | `Illuminate\Routing\RedirectController` |
| `analytics/bus-health` | `analytics.bus-health` | `Illuminate\Routing\RedirectController` |
| `analytics/delay-predictions` | `analytics.delay-predictions` | `App\Http\Controllers\Admin\DelayPredictionController@index` |
| `analytics/department-access` | `analytics.department-access` | `Closure` |
| `analytics/department/{stage}` | `analytics.department.stage` | `App\Http\Controllers\Admin\DepartmentAnalyticsController@show` |
| `analytics/descriptive` | `analytics.descriptive` | `App\Http\Controllers\Admin\DescriptiveAnalyticsController@index` |
| `analytics/fleet-trip` | `analytics.fleet-trip` | `Illuminate\Routing\RedirectController` |
| `analytics/fuel` | `analytics.fuel` | `Illuminate\Routing\RedirectController` |
| `analytics/fuel-predictions` | `analytics.fuel-predictions` | `App\Http\Controllers\Admin\FuelPredictionController@index` |
| `analytics/inventory` | `analytics.inventory` | `Illuminate\Routing\RedirectController` |
| `analytics/overview` | `analytics.overview` | `App\Http\Controllers\Admin\AnalyticsOverviewController@index` |
| `analytics/recommendations` | `analytics.recommendations` | `Illuminate\Routing\RedirectController` |
| `analytics/{stage}` | `analytics.stage` | `App\Http\Controllers\Admin\AnalyticsStageController@show` |
| `attendance` | `attendance` | `Illuminate\Routing\RedirectController` |
| `auth/google` | `auth.google` | `App\Http\Controllers\Auth\GoogleAuthController@redirect` |
| `auth/google/callback` | `auth.google.callback` | `App\Http\Controllers\Auth\GoogleAuthController@callback` |
| `auth/google/link` | `auth.google.link` | `App\Http\Controllers\Auth\GoogleAuthController@linkForm` |
| `auth/google/register` | `auth.google.register` | `App\Http\Controllers\Auth\GoogleAuthController@registrationForm` |
| `available-mechanics` | `available-mechanics` | `Illuminate\Routing\RedirectController` |
| `batch-file-processing` | `batch-file-processing` | `App\Http\Controllers\Admin\BatchFileProcessingController@index` |
| `batch-file-processing/export` | `batch-file-processing.export` | `App\Http\Controllers\Admin\BatchFileProcessingController@export` |
| `batch-file-processing/generic-profiles` | `batch-file-processing.generic.profiles` | `App\Http\Controllers\Admin\GenericBatchProfileController` |
| `batch-file-processing/generic/{batchUpload}` | `batch-file-processing.generic.review` | `App\Http\Controllers\Admin\GenericBatchModalRedirectController` |
| `broadcasting/auth` | `—` | `Illuminate\Broadcasting\BroadcastController@authenticate` |
| `bus-master-list` | `bus-master-list` | `App\Http\Controllers\Operation\BusController@index` |
| `driver-attendance` | `driver-attendance` | `App\Http\Controllers\Operation\DriverAttendanceController@index` |
| `email/otp` | `verification.otp` | `App\Http\Controllers\Auth\EmailOtpController@show` |
| `email/otp-preview` | `verification.otp-preview` | `Illuminate\Routing\ViewController` |
| `email/verify/{id}/{hash}` | `verification.verify` | `App\Http\Controllers\Auth\RegistrationController@verify` |
| `forgot-password` | `password.request` | `App\Http\Controllers\Auth\PasswordResetController@requestForm` |
| `fuel-reports` | `fuel-reports` | `App\Http\Controllers\Maintenance\FuelReportController@index` |
| `fuel-reports/gps-distance` | `fuel-reports.gps-distance` | `App\Http\Controllers\Maintenance\FuelReportController@gpsDistance` |
| `inventory` | `inventory` | `App\Http\Controllers\Warehouse\InventoryController@index` |
| `inventory-restock` | `inventory-restock` | `App\Http\Controllers\Purchase\InventoryRestockController@index` |
| `inventory-restock/{maintenanceRequest}/approval-signature` | `inventory-restock.approval-signature` | `App\Http\Controllers\Purchase\InventoryRestockController@approvalSignature` |
| `inventory/{inventoryItem}/movements` | `inventory.movements` | `App\Http\Controllers\Warehouse\InventoryMovementController@show` |
| `item-requests` | `item-requests` | `App\Http\Controllers\Maintenance\ItemRequestController@index` |
| `job-orders` | `job-orders` | `App\Http\Controllers\Maintenance\JobOrderIndexController` |
| `job-orders/available-mechanics` | `job-orders.available-mechanics` | `App\Http\Controllers\Maintenance\JobOrderController@availableMechanics` |
| `job-orders/duration-suggestion` | `job-orders.duration-suggestion` | `App\Http\Controllers\Maintenance\JobOrderController@durationSuggestion` |
| `job-orders/inventory-options` | `job-orders.inventory-options` | `App\Http\Controllers\Maintenance\JobOrderController@inventoryOptions` |
| `job-orders/specializations` | `job-orders.specializations` | `App\Http\Controllers\Maintenance\JobOrderController@specializationOptions` |
| `job-orders/work-details` | `job-orders.work-details` | `Closure` |
| `job-orders/{jobOrder}/approval-signature` | `job-orders.approval-signature` | `App\Http\Controllers\Maintenance\JobOrderController@approvalSignature` |
| `maintenance-dashboard` | `maintenance-dashboard` | `App\Http\Controllers\Maintenance\MaintenanceDashboardController@index` |
| `maintenance-referrals` | `maintenance-referrals` | `App\Http\Controllers\Maintenance\MaintenanceReferralController@index` |
| `maintenance-requests` | `maintenance-requests` | `App\Http\Controllers\Purchase\MaintenanceRequestController@index` |
| `maintenance/attendance/batch/mechanic` | `maintenance.attendance.batch.roster` | `App\Http\Controllers\Operation\BatchAttendanceController@roster` |
| `maintenance/mechanics` | `maintenance.personnel.mechanics` | `App\Http\Controllers\Operation\PersonnelController@mechanics` |
| `mechanic-attendance` | `mechanic-attendance` | `App\Http\Controllers\Operation\MechanicAttendanceController@index` |
| `mechanic-list` | `mechanic-list` | `App\Http\Controllers\Maintenance\MechanicListController@index` |
| `onboarding` | `onboarding.show` | `App\Http\Controllers\OnboardingController@show` |
| `operation/attendance/batch/{type}` | `operation.attendance.batch.roster` | `App\Http\Controllers\Operation\BatchAttendanceController@roster` |
| `operation/auto-dispatch` | `auto-dispatch` | `Illuminate\Routing\RedirectController` |
| `operation/auto-scheduling` | `auto-scheduling` | `App\Http\Controllers\Operation\AutoSchedulingController@index` |
| `operation/auto-scheduling/ai/training/status` | `auto-scheduling.ai.training-status` | `App\Http\Controllers\Operation\AutoSchedulingController@trainingStatus` |
| `operation/auto-scheduling/calendar` | `auto-scheduling.calendar` | `App\Http\Controllers\Operation\AutoSchedulingController@calendar` |
| `operation/auto-scheduling/draft` | `auto-scheduling.draft.load` | `App\Http\Controllers\Operation\AutoSchedulingController@loadDraft` |
| `operation/auto-scheduling/drafts` | `auto-scheduling.drafts.index` | `App\Http\Controllers\Operation\AutoSchedulingController@listDrafts` |
| `operation/bus-availability` | `bus-availability` | `App\Http\Controllers\Operation\BusAvailabilityController@index` |
| `operation/client-trip-times` | `client-trip-times` | `App\Http\Controllers\Operation\ClientTripTimeController@index` |
| `operation/client-trip-times/panels/{operationClient}/{category}` | `client-trip-times.panel` | `App\Http\Controllers\Operation\ClientTripTimeController@panel` |
| `operation/clients` | `operation.clients.index` | `App\Http\Controllers\Operation\ClientMasterListController@index` |
| `operation/daily-driver-reports` | `daily-driver-reports` | `App\Http\Controllers\Operation\DailyDriverReportController@index` |
| `operation/daily-driver-reports/assigned-drivers` | `daily-driver-reports.assigned-drivers` | `App\Http\Controllers\Operation\DailyDriverReportController@assignedDrivers` |
| `operation/daily-driver-reports/schedule-lookup` | `daily-driver-reports.schedule-lookup` | `App\Http\Controllers\Operation\DailyDriverReportController@scheduleLookup` |
| `operation/dashboard` | `dashboard-operation` | `App\Http\Controllers\Operation\OperationDashboardController@index` |
| `operation/driver-bus-assignment` | `driver-bus-assignment` | `App\Http\Controllers\Operation\TripAssignmentController@index` |
| `operation/driver-bus-assignment/availability/{tripSchedule}` | `driver-bus-assignment.availability` | `App\Http\Controllers\Operation\TripAssignmentController@availability` |
| `operation/driver-bus-assignment/calendar` | `driver-bus-assignment.calendar` | `App\Http\Controllers\Operation\TripAssignmentController@calendar` |
| `operation/driver-bus-assignment/print` | `driver-bus-assignment.print` | `App\Http\Controllers\Operation\TripAssignmentController@printSheet` |
| `operation/incidents` | `incidents` | `App\Http\Controllers\Operation\IncidentController@index` |
| `operation/incidents/create` | `incidents.create` | `App\Http\Controllers\Operation\IncidentController@create` |
| `operation/incidents/{incident}` | `incidents.show` | `App\Http\Controllers\Operation\IncidentController@show` |
| `operation/personnel-management/driver-master-list` | `operation.personnel-management.driver-master-list` | `Illuminate\Routing\RedirectController` |
| `operation/personnel-management/mechanic-master-list` | `operation.personnel-management.mechanic-master-list` | `Illuminate\Routing\RedirectController` |
| `operation/personnel/drivers` | `operation.personnel.drivers` | `App\Http\Controllers\Operation\PersonnelController@drivers` |
| `operation/personnel/mechanics` | `operation.personnel.mechanics` | `App\Http\Controllers\Operation\PersonnelController@mechanics` |
| `operation/routes` | `operation.routes` | `App\Http\Controllers\Operation\RouteController@index` |
| `operation/routes-stops` | `operation.routes-stops` | `Illuminate\Routing\RedirectController` |
| `operation/routes/location-search` | `operation.routes.location-search` | `App\Http\Controllers\Operation\RouteController@searchLocations` |
| `operation/routes/reverse-location` | `operation.routes.reverse-location` | `App\Http\Controllers\Operation\RouteController@reverseLocation` |
| `operation/shuttle-bus-management` | `operation.shuttle-bus-management` | `Illuminate\Routing\RedirectController` |
| `operation/trip-records` | `trip-records` | `App\Http\Controllers\Operation\TripRecordController@index` |
| `operation/trip-records/export` | `trip-records.export` | `App\Http\Controllers\Operation\TripRecordController@export` |
| `operation/trip-schedule` | `trip-schedule` | `App\Http\Controllers\Operation\TripScheduleController@index` |
| `operation/trip-schedule/available-dates` | `trip-schedule.available-dates` | `App\Http\Controllers\Operation\TripScheduleController@availableDates` |
| `part-requests` | `part-requests` | `App\Http\Controllers\Warehouse\WarehousePartRequestController@index` |
| `pms-scheduling` | `PMS-Scheduling` | `App\Http\Controllers\Maintenance\PmsSchedulingController@index` |
| `pms-scheduling/{pmsSchedule}/create-job-order` | `pms-schedules.create-job-order` | `App\Http\Controllers\Maintenance\PmsSchedulingController@createJobOrder` |
| `purchase-orders` | `purchase-orders` | `App\Http\Controllers\Purchase\PurchaseOrderController@index` |
| `purchase-orders/{purchaseOrder}/approval-signature` | `purchase-orders.approval-signature` | `App\Http\Controllers\Purchase\PurchaseOrderController@approvalSignature` |
| `purchase-requests` | `purchase-requests` | `App\Http\Controllers\Maintenance\PurchaseRequestIndexController` |
| `purchase-requests/job-orders/{jobOrder}/requisition-data` | `purchase-requests.job-order-data` | `App\Http\Controllers\Maintenance\PurchaseRequestController@jobOrderData` |
| `purchase/dashboard` | `dashboard-purchase` | `App\Http\Controllers\Purchase\PurchaseDashboardController@index` |
| `purchase/job-orders/{jobOrder}/approval-signature` | `purchase.job-orders.approval-signature` | `App\Http\Controllers\Maintenance\JobOrderController@approvalSignature` |
| `register` | `register` | `App\Http\Controllers\Auth\RegistrationController@show` |
| `registration/pending` | `registration.pending` | `Closure` |
| `reset-password/{token}` | `password.reset` | `App\Http\Controllers\Auth\PasswordResetController@resetForm` |
| `storage/{path}` | `storage.local` | `Closure` |
| `topbar/summary` | `topbar.summary` | `App\Http\Controllers\TopbarController@summary` |
| `up` | `—` | `Illuminate\Foundation\Http\HealthCheck` |
| `warehouse/dashboard` | `warehouse.dashboard` | `App\Http\Controllers\Warehouse\WarehouseDashboardController@index` |
| `warehouse/incoming-deliveries` | `incoming-deliveries` | `App\Http\Controllers\Warehouse\IncomingDeliveryController@index` |
| `warehouse/item-requests` | `warehouse.item-requests` | `App\Http\Controllers\Warehouse\ItemRequestController@index` |
| `warehouse/item-requests/{itemRequest}/signed-slip` | `warehouse.item-requests.signed-slip` | `App\Http\Controllers\Warehouse\ItemRequestController@signedSlip` |
| `warehouse/item-requests/{itemRequest}/verify` | `warehouse.item-requests.verify` | `App\Http\Controllers\Warehouse\ItemRequestController@verify` |
| `warehouse/job-orders/{jobOrder}/approval-signature` | `warehouse.job-orders.approval-signature` | `App\Http\Controllers\Maintenance\JobOrderController@approvalSignature` |
| `warehouse/stock-movements` | `stock-movements` | `App\Http\Controllers\Warehouse\StockMovementController@index` |

## Reproduce

```powershell
node tools/auditing/css-inventory.mjs storage/logs/css-inventory.json
php artisan route:list --json
```
