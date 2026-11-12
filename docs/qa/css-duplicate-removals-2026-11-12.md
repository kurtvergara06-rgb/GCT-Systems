# Verified exact CSS removals — 2026-11-12

Baseline: `589f5a6b` on `feature/global-dark-mode`. Each row removes an earlier exact declaration block with the same selector and media/support context, retaining its later copy. Nested rules, keyframes, layers and other conditional contexts were excluded from this automatic exact-copy pass. No JavaScript consumers of CSS rule indices were found. This is redundancy removal, not an inference that a component is unused.

**82 earlier rule copies** removed across **18 stylesheets**. Original line numbers below refer to the baseline, not the shorter final files. Related theme/color repairs may additionally update the retained source rule.

| Stylesheet | Removed original line | Retained original line | Selector |
| --- | ---: | ---: | --- |
| `resources/css/Admin/User_Management/permissions.css` | 1524 | 1568 | `.permissions-page .permission-employee-choice[hidden]` |
| `resources/css/Admin/User_Management/permissions.css` | 1540 | 1595 | `.permissions-page .permission-access-intro` |
| `resources/css/Admin/User_Management/permissions.css` | 1540 | 1595 | `.permissions-page .permission-access-intro > span` |
| `resources/css/Maintenance/fuel-reports.css` | 755 | 1589 | `.fuel-page .fuel-action-btn.view` |
| `resources/css/Maintenance/fuel-reports.css` | 761 | 1602 | `.fuel-page .fuel-action-btn.edit` |
| `resources/css/Maintenance/fuel-reports.css` | 767 | 1615 | `.fuel-page .fuel-action-btn.delete` |
| `resources/css/Maintenance/item-requests.css` | 538 | 1172 | `#viewItemRequestModal .ir-view-document-summary > div:last-child` |
| `resources/css/Maintenance/item-requests.css` | 544 | 1178 | `#viewItemRequestModal .ir-view-document-columns` |
| `resources/css/Maintenance/item-requests.css` | 564 | 1209 | `#viewItemRequestModal .ir-view-document-table .ir-view-parts-list` |
| `resources/css/Maintenance/job-order.css` | 2491 | 4659 | `:is(#jobModal, #editJobModal) .jo-create-parts-table` |
| `resources/css/Maintenance/job-order.css` | 3333 | 3669 | `:is(#jobModal, #editJobModal) .jo-create-parts-table-head` |
| `resources/css/Maintenance/job-order.css` | 3571 | 3669 | `:is(#jobModal, #editJobModal) .jo-create-parts-table-head` |
| `resources/css/Maintenance/job-order.css` | 3577 | 3675 | `:is(#jobModal, #editJobModal) .jo-create-parts-repeater .jo-part-row input[type="number"]` |
| `resources/css/Maintenance/job-order.css` | 3579 | 3677 | `:is(#jobModal, #editJobModal) .jo-create-parts-repeater .remove-part-btn` |
| `resources/css/Maintenance/job-order.css` | 4386 | 7002 | `.jo-page .job-orders-table th:nth-child(6)` |
| `resources/css/Maintenance/job-order.css` | 4387 | 7003 | `.jo-page .job-orders-table th:nth-child(7)` |
| `resources/css/Maintenance/job-order.css` | 4655 | 4693 | `:is(#jobModal, #editJobModal) .jo-create-parts-repeater .jo-row-stock-badge[hidden]` |
| `resources/css/Maintenance/job-order.css` | 5800 | 5886 | `:is(#jobModal, #editJobModal) .jo-coverage-table .jo-coverage-row` |
| `resources/css/Maintenance/job-order.css` | 6866 | 6910 | `.jo-head-review-body .jo-review-detail-grid > div` |
| `resources/css/Maintenance/job-order.css` | 6876 | 6897 | `.jo-head-review-body .jo-review-detail-grid` |
| `resources/css/Maintenance/purchase-requests.css` | 3178 | 3591 | `#editPrModal .pr-review-footer,     #editPrModal .pr-review-footer-actions` |
| `resources/css/Maintenance/purchase-requests.css` | 3566 | 3687 | `#editPrModal #editPrMainActions .ui-form-btn-primary i, #editPrModal #editPrMainActions .ui-form-btn-primary span` |
| `resources/css/Maintenance/purchase-requests.css` | 5378 | 5455 | `#prModal .pr-review-parts-table th:nth-child(2)` |
| `resources/css/Maintenance/purchase-requests.css` | 5426 | 5618 | `.purchase-page .pr-ir-style-table th:nth-child(5)` |
| `resources/css/Maintenance/purchase-requests.css` | 5499 | 5614 | `.purchase-page .pr-ir-style-table th:nth-child(1)` |
| `resources/css/Maintenance/purchase-requests.css` | 5505 | 5620 | `.purchase-page .pr-ir-style-table th:nth-child(7)` |
| `resources/css/Maintenance/purchase-requests.css` | 5545 | 5634 | `.purchase-page .pr-ir-style-table .pr-ir-date` |
| `resources/css/Maintenance/purchase-requests.css` | 5554 | 5613 | `.purchase-page .pr-ir-style-table th:nth-child(n)` |
| `resources/css/Maintenance/purchase-requests.css` | 5603 | 5633 | `.purchase-page .pr-ir-style-table .pr-ir-breakdown` |
| `resources/css/Maintenance/purchase-requests.css` | 5660 | 5687 | `#prModal .pr-ir-panel-body` |
| `resources/css/Operation/Attendance/driver-attendance.css` | 1191 | 1268 | `.driver-attendance-page .attendance-table td:nth-child(4), .driver-attendance-page .attendance-table td:nth-child(5), .driver-attendance-page .attendance-table td:nth-child(6)` |
| `resources/css/Operation/Clients/client-master-list.css` | 63 | 163 | `.client-master-page .gct-sticky-actions .client-master-actions` |
| `resources/css/Operation/Daily_Driver_Reports/daily-driver-reports.css` | 2251 | 2252 | `#ddrEncodeModal .ddr-report-meta-grid > *` |
| `resources/css/Operation/dashboard-operation.css` | 965 | 1700 | `.operation-dashboard-grid` |
| `resources/css/Operation/dashboard-operation.css` | 1157 | 2450 | `.fulfillment-metric` |
| `resources/css/Operation/dashboard-operation.css` | 1171 | 2465 | `.fulfillment-metric__value` |
| `resources/css/Operation/dashboard-operation.css` | 1415 | 2960 | `.operation-scheduling-alert.has-pending` |
| `resources/css/Operation/dashboard-operation.css` | 1565 | 3354 | `.workspace-link-icon.purple` |
| `resources/css/Operation/dashboard-operation.css` | 1569 | 3369 | `.workspace-link-text` |
| `resources/css/Operation/dashboard-operation.css` | 1600 | 3667 | `.operation-activity-list` |
| `resources/css/Operation/dashboard-operation.css` | 1636 | 3717 | `.activity-content` |
| `resources/css/Operation/dashboard-operation.css` | 1655 | 3735 | `.activity-time` |
| `resources/css/Operation/dashboard-operation.css` | 1697 | 3805 | `.operation-top-stats` |
| `resources/css/Operation/dashboard-operation.css` | 1709 | 3822 | `.operation-top-stats` |
| `resources/css/Operation/dashboard-operation.css` | 1712 | 3842 | `.operation-fulfillment-banner` |
| `resources/css/Operation/Routes/routes-stops.css` | 153 | 1228 | `.routes-page .route-summary-grid,     .routes-page .routes-summary-grid` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 2792 | 3197 | `.gct-conflict-summary-item + .gct-conflict-summary-item` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 2801 | 3210 | `.gct-conflict-actions` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 3493 | 3745 | `.auto-scheduling-page .gct-conflict-workspace` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 3525 | 3944 | `.auto-scheduling-page .gct-conflict-table th:nth-child(2)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 3526 | 3945 | `.auto-scheduling-page .gct-conflict-table th:nth-child(3)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 3527 | 4158 | `.auto-scheduling-page .gct-conflict-table th:nth-child(4)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 3529 | 4020 | `.auto-scheduling-page .gct-conflict-table th:nth-child(6)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 4016 | 4156 | `.auto-scheduling-page .gct-conflict-table th:nth-child(2)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 4017 | 4157 | `.auto-scheduling-page .gct-conflict-table th:nth-child(3)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 4019 | 4159 | `.auto-scheduling-page .gct-conflict-table th:nth-child(5)` |
| `resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css` | 4556 | 4658 | `body:has(.auto-scheduling-page) .auto-schedule-table thead` |
| `resources/css/Operation/Scheduling_And_Dispatch/client-trip-times.css` | 655 | 986 | `.client-trip-times-page .ctt-client-card[open] > .ctt-client-header` |
| `resources/css/Operation/Scheduling_And_Dispatch/client-trip-times.css` | 890 | 1299 | `.client-trip-times-page .ctt-overview-copy` |
| `resources/css/Operation/Scheduling_And_Dispatch/client-trip-times.css` | 1823 | 2186 | `.client-trip-times-page .ctt-configurations-card` |
| `resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css` | 498 | 1445 | `.assignment-table th:nth-child(1)` |
| `resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css` | 1402 | 1570 | `.resource-pagination .table-footer` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 513 | 1297 | `.trip-table th:nth-child(4), .trip-table td:nth-child(4), .trip-table th:nth-child(5), .trip-table td:nth-child(5), .trip-table th:nth-child(6), .trip-table td:nth-child(6), .trip-table th:nth-child(7), .trip-table td:nth-child(7), .trip-table th:nth-child(8), .trip-table td:nth-child(8), .trip-table th:nth-child(9), .trip-table td:nth-child(9)` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 1176 | 1446 | `.trip-toolbar` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 1378 | 1446 | `.trip-toolbar` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 1760 | 2898 | `#viewTripModal .trip-detail-copy` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 1918 | 1937 | `.trip-schedule-page .trip-toolbar` |
| `resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css` | 3191 | 3233 | `#tripModal .trip-editor-grid` |
| `resources/css/Operation/Trip_Records/trip-records.css` | 2336 | 2368 | `.trip-records-page .trip-history-kpis` |
| `resources/css/Operation/Trip_Records/trip-records.css` | 2338 | 2369 | `.trip-records-page .trip-history-card` |
| `resources/css/Purchase/Requested_Purchase/maintenance-requests.css` | 1424 | 1666 | `main.purchase-maintenance-requests-page .requested-purchase-table` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 704 | 2917 | `[data-ajax-region="dashboard-incoming-deliveries"] .warehouse-dashboard-table th:nth-child(3), [data-ajax-region="dashboard-incoming-deliveries"] .warehouse-dashboard-table td:nth-child(3)` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 748 | 3158 | `[data-ajax-region="recent-stock-movements"] .warehouse-dashboard-table th:nth-child(1), [data-ajax-region="recent-stock-movements"] .warehouse-dashboard-table td:nth-child(1)` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 931 | 2455 | `.warehouse-bottom-grid > .warehouse-panel:last-child` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 949 | 2461 | `.warehouse-bottom-grid` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 1429 | 2942 | `.warehouse-reference-overview` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 1827 | 3181 | `.warehouse-bottom-grid` |
| `resources/css/Warehouse/dashboard-warehouse.css` | 2738 | 2993 | `.warehouse-reference-title p` |
| `resources/css/Warehouse/item-requests.css` | 33 | 45 | `.warehouse-ir-page .ir-table th,.warehouse-ir-page .ir-table td` |
| `resources/css/Warehouse/item-requests.css` | 589 | 643 | `#warehouseItemRequestDetails .warehouse-ir-view-section` |
| `resources/css/Warehouse/item-requests.css` | 650 | 780 | `.warehouse-ir-page .ir-table th:nth-child(2)` |
| `resources/css/Warehouse/item-requests.css` | 651 | 781 | `.warehouse-ir-page .ir-table th:nth-child(3)` |

Additional deliberate consolidation:

- Header-divider resets: the existing Main-styles/theme.css contract remains; copies in main.css, shared-ui-enhancements.css and Analytics/design-system.css were removed.
- Notification bell keyframes/animation: Main-styles/topbar.css remains the owner, imported by shared app.js; the Analytics duplicate and its extra !important were removed.
- Bus IDs, plates and model text: the active page-scoped table rules own typography; earlier unscoped copies and the unused strong.bus-model-text rule were removed. The template renders spans and JavaScript does not create strong model labels.
- Bus Add/Edit panel declarations share their existing rule; its colors now resolve through semantic variables.
- Schedule labels retain their original base label color contract; three later color overrides were removed while their spacing/typography rules remain.
- Bus Availability maintenance badge: the old bright-text patch was removed after canonical status component colors were restored.
- Client-side search empty markup reuses the same shared empty-state CSS as Blade; duplicated inline presentation was removed.

Different declarations, responsive variants, dynamic classes, print rules and state overrides were not bulk-merged. No entire CSS file, animation with an unresolved consumer, business workflow or component was removed.
