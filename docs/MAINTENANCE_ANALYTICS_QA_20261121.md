# Maintenance Analytics reference implementation — 21 November 2026

Branch: `feature/maintenance-optimization`. Existing Admin Analytics routes and
Blade layout shells are retained. The four supplied images guide Maintenance
content; the existing GCT sidebar, topbar, summary cards, Analytics cards,
permissions, fonts, and theme standards are reused.

## Implemented behavior

| Stage | Maintenance content |
| --- | --- |
| Descriptive | Four KPIs with prior-period comparisons, created/completed Job Orders, monthly reported fuel, current Job Order status distribution, and bus fuel ranking. |
| Diagnostic | Recorded delay factors, a distance-adjusted fuel comparison, evidence findings, issue filtering, and read-only details. |
| Predictive | Repair-duration baseline, maintenance-frequency review, historical fuel baseline, explicit source/readiness labels, and forecast charts. |
| Prescriptive | Repair, mechanic-assignment, parts-review, and fuel-review counts; prioritized evidence/actions; category filtering and read-only details. |

Date and bus filters affect the reporting context. Issue/category filters narrow
the findings/recommendations tables. Filters replace the Analytics content region
without reloading the entire page. Details dialogs remain open when their
backdrop is clicked.

## Data and permission safeguards

- View is required for every stage; Analyze gates Diagnostic/Predictive;
  Recommendations gates Prescriptive. Direct URLs enforce the same rules.
- Maintenance queries run before any Admin-wide Analytics calculations. URL
  parameters cannot switch a Maintenance account to another department.
- Job Orders reuse the existing Maintenance ownership/handoff scope. Fuel uses
  the Maintenance Fuel Report register. Foreign/unowned jobs are excluded.
- Query Builder avoids the JobOrder model's retrieved notification side effect.
  Tests verify that reading Analytics does not modify Job Orders or notifications.
- Repair averages require valid actual start/completion timestamps. Legacy
  records without verified timing do not receive invented durations.
- Overdue findings preserve the existing parts-issuance timing rule and exclude
  records with a completion date, including legacy completion records.
- A fuel comparison requires at least three other-bus reports with positive
  fuel/distance measurements. It adjusts for distance and does not claim that
  routes, loads, or operating conditions are identical.
- Recommendations never approve repairs, assign mechanics, purchase parts, or
  change inventory. Parts recommendations require stock/workflow review.

## Forecast limitations

These pages do not claim that an ML model produced a result. Repair estimates
use at least three valid completed repair durations from the previous 90 days.
Fuel estimates require reports in each of the previous three complete calendar
months and use their mean. Forecasts are flat historical baselines, labeled
**Rule-Based Estimate**; unknown future actuals are not fabricated.

Maintenance risk is a review signal for buses with at least three recorded Job
Orders in 60 days, not a calibrated breakdown probability. Insufficient history
shows an unavailable state. There is no automatic operational action.

## Verification

Only owned disposable QA databases were used for database tests and browser
fixtures. Browser accounts/data were explicitly synthetic QA fixtures. No
existing application account permissions or operational records were changed;
no operational migration was executed.

| Check | Result |
| --- | --- |
| MaintenanceAnalyticsReferenceTest | PASS — 4 tests, 57 assertions |
| DepartmentAnalyticsIntegrationTest | PASS — 9 tests, 159 assertions |
| Frontend tests | PASS — 13 tests |
| Vite production build and registered Maintenance entries | PASS |
| PHP / JavaScript syntax, Blade cache/clear, whitespace | PASS |
| Full PHPUnit run | FAIL — 1,099 passed, 9 failures, 1 error; 9,143 assertions |
| Admin browser smoke test | All four original Admin stages opened |
| QA Admin grants to Maintenance Head | View, Analyze, Recommendations saved; four sidebar links appeared |
| Maintenance desktop browser | All four pages checked in Light and Dark Mode, including 1440px width |
| Maintenance phone browser | All four pages checked in Light and Dark Mode at 390×844; no document-level horizontal overflow |
| Interactive browser checks | Issue/category filtering, details, backdrop behavior, readable light/dark dialogs |

The final full-suite failure names match nine failures in the earlier
`.git/analytics-final-full.log`; the same MechanicAttendanceCutoff error also
appeared there. The affected suites were rerun after the final legacy date/timing
guards. The full suite is not reported as passing.

Outstanding full-suite checks:

- MaintenanceGsapAnimationTest: account dark loader source expectation.
- OperationDarkModeFoundationTest: shared tokens and saved-theme source expectations.
- PurchaseDarkModeFoundationTest: first-paint and Purchase Order table/status expectations.
- SystemWideSidebarAnimationTest: Warehouse appearance bridge expectation.
- WarehouseItemRequestTableLayoutTest: seven-column source expectation.
- WarehouseActiveHistoryTabsTest: historical requisition copy expectation.
- MechanicAttendanceCutoffTest: undefined `$status` in the test itself.

The QA browser also recorded a Reverb connection error. Manual grants/refresh
and server authorization were verified; automatic Reverb delivery is not claimed.

## Browser evidence

Sixteen full-page JPEG captures (four stages × two themes × desktop/phone) are
stored locally in:

`C:/Users/micha/.codex/visualizations/2026/11/14/01a1d829-2b98-7350-96f9-6e90346aff0b/maintenance-analytics-20261121/`

Names follow `descriptive-light.jpg`, `diagnostic-dark.jpg`,
`predictive-mobile-light.jpg`, and `prescriptive-mobile-dark.jpg`.

`main` was not modified. No commit or push was performed for this implementation.
