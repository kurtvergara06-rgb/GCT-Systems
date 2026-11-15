# System CSS audit — 2026-11-12

**Status: in progress.** Purchasing, Administration and the final Operations below-fold/theme verification are pending authenticated browser access. This report does not certify the entire system as passed.

Branch: `feature/global-dark-mode`. Baseline: `589f5a6b`. First cleanup commit: `738885ff72c4559dc3bf9ff13da5a38bedc7f92f`. Subsequent repairs are currently uncommitted while browser coverage is completed. No production deployment, operational mutation, database migration, permission change or main-branch modification was performed.

## Source coverage and cleanup

All 127 CSS files and 161 Blade templates are indexed in [the source inventory](css-source-inventory-2026-11-12.md). The 84 page/layout/standalone documents include authentication, print and email documents; that number is not a browser page count. JavaScript inventory: 88 files. Dynamic Analytics stage templates remain live consumers; missing text-search references were not treated as proof of unused CSS.

82 identical declaration-only rule blocks were removed with the later same-selector/same-context block retained. [The removal ledger](css-duplicate-removals-2026-11-12.md) records baseline source lines. Header resets now retain their existing theme.css owner, the bell animation retains topbar.css, and generated empty-search markup reuses shared styles. No CSS file or entire component was deleted. 1,482 repeated-selector groups remain candidates, many intentional; they were not all manually classified or bulk-removed.

## Confirmed defects and current verification

| Module | Problem | Root cause | CSS/JS cleanup | Verified |
| --- | --- | --- | --- | --- |
| Operation | Fleet doughnut concealed | Full-size center overlay painted opaque by dark bridge | Remove center overlay from surface-background group | Browser: ring visible with 36 active / 4 maintenance |
| Operation | IDs, plates, client/driver identities unreadable | Child selectors retained light-theme ink | Correct existing page rules to semantic tokens; consolidate superseded model/identity rules | Light/Dark desktop/laptop, View/Add/Edit opened without saving |
| Shared/Operation | Badge text disagreed with canonical tone | Legacy .badge state specificity beat component color | Exclude canonical data-ui-component badges from legacy tone groups; remove obsolete availability patch | Browser on Bus Master and Availability |
| Operation | Trip modal labels and section surfaces unreadable | Later ID-scoped hardcoded light colors overrode base field styles | Retain base semantic label owner; correct controls, mode cards and readonly fields | Browser Light/Dark; Cancel; no trip submitted |
| Operation | Client totals, route names, incident identities too dark | Page child declarations ignored inherited foreground | Correct existing source declarations | Browser retests at both desktop widths |
| Operation | Fleet legend did not match chart colors | Maintenance/inactive CSS colors disagreed with actual series | Match legend to existing JS palette | Browser + actual-consumer Node regression test |
| Shared/Admin | Repeated header resets and bell animation | Multiple layers copied the same shared contract | Remove redundant copies; retain shared owners | Render tests, browser sampled headers and notification dropdown |
| Operation | Attendance/workspace panels and activity/chart labels below fold | Hardcoded light gradients/ink; canvas text does not inherit theme CSS | Correct original component rules; chart labels read tokens and update via cleaned-up theme observer | Source/build/Node tests pass; final browser recheck PENDING |
| Warehouse | Empty-state descriptions clipped inside tables | Inherited white-space:nowrap | Shared empty component explicitly wraps and breaks long strings | Browser: descriptions fit; measured scrollWidth equals clientWidth |
| Warehouse | Lower dashboard status labels clipped at laptop width | Three narrow columns persisted until 1240px despite sidebar | Adjust existing two-column breakpoint to 1440px | Browser 1366x768: two 499px columns; badges have no internal text overflow |
| Maintenance | Shared empty-search styling vulnerable to fixed light ink | Shared title/description used fixed colors; JS repeated inline styles | Use semantic shared styles and remove repeated inline presentation | JO unmatched search; clear search; no record mutation |

## Actual browser page coverage

Each row below represents an actually visited route. The modes list records captured viewport inspections, not full interaction or accessibility certification. Early baseline failures are retained in the evidence log; later retests supersede only the component checked. Initial scans sampled the viewport and excluded gradients/translucent backgrounds. Full-page review subsequently found the Operations below-fold defects noted above. Screenshots at logical 1920x1080 and 1366x768 were requested; the browser extension may scale native captures.

| Module | Page | Route | Captured checks | Document overflow observations |
| --- | --- | --- | --- | --- |
| Operation | dashboard | `/operation/dashboard` | dark-desktop, dark-laptop, light-laptop, light-desktop, light-tablet, light-mobile, dark-mobile, dark-desktop-final | None in captured checks |
| Operation | drivers | `/operation/personnel/drivers` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | buses | `/bus-master-list` | dark-desktop, dark-laptop, light-laptop, light-desktop, light-tablet, light-mobile, dark-mobile, dark-desktop-final | None in captured checks |
| Operation | routes | `/operation/routes` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | clients | `/operation/clients` | dark-desktop, dark-desktop-retest, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | driver-attendance | `/driver-attendance` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | mechanic-attendance | `/mechanic-attendance` | dark-desktop, dark-desktop-retest, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | schedule | `/operation/trip-schedule` | dark-desktop, dark-desktop-retest, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | auto-scheduling | `/operation/auto-scheduling` | dark-desktop, dark-desktop-retest, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | client-trip-times | `/operation/client-trip-times` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | assignments | `/operation/driver-bus-assignment` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | trip-records | `/operation/trip-records` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | ddr | `/operation/daily-driver-reports` | dark-desktop, dark-laptop, light-laptop, light-desktop, light-tablet, light-mobile, dark-mobile, dark-desktop-final | None in captured checks |
| Operation | incidents | `/operation/incidents` | dark-desktop, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Operation | bus-availability | `/operation/bus-availability` | dark-desktop, dark-desktop-retest, dark-laptop, light-laptop, light-desktop, dark-desktop-final | None in captured checks |
| Maintenance | maintenance-dashboard | `/maintenance-dashboard` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | maintenance-referrals | `/maintenance-referrals` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | job-orders | `/job-orders` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | maintenance-item-requests | `/item-requests` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | maintenance-pr | `/purchase-requests` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | pms | `/pms-scheduling` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | fuel-reports | `/fuel-reports` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | mechanic-list | `/mechanic-list` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | maintenance-mechanic-attendance | `/mechanic-attendance` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Maintenance | maintenance-mechanic-master | `/maintenance/mechanics` | maintenance-desktop, maintenance-laptop | None in captured checks |
| Warehouse | warehouse-dashboard | `/warehouse/dashboard` | light-desktop, light-laptop | None in captured checks |
| Warehouse | inventory | `/inventory` | light-desktop, light-laptop | None in captured checks |
| Warehouse | warehouse-item-requests | `/warehouse/item-requests` | light-desktop, light-laptop | None in captured checks |
| Warehouse | part-requests | `/part-requests` | light-desktop, light-laptop | None in captured checks |
| Warehouse | incoming-deliveries | `/warehouse/incoming-deliveries` | light-desktop, light-laptop | None in captured checks |
| Warehouse | stock-movements | `/warehouse/stock-movements` | light-desktop, light-laptop | None in captured checks |

## Interaction and data evidence

- Operation: Bus View (ADQ-5369), Add, Edit (NAH-9314), client picker empty state, trip Create modal, notification dropdown, sidebar collapse/restore. All forms cancelled. Modal backdrop check left Add open.
- Maintenance: all ten listed pages visited at both desktop widths. Dashboard bar and fleet ring rendered (36 active, 4 maintenance). JO New opened/cancelled; JO-2026-0008 View opened/closed. History showed completed JO-0011/0010/0009/0006/0004 with View-only actions. An unmatched search showed the correct empty state and was cleared. The signed JO detail screenshot was deliberately not published.
- Warehouse: all six listed pages visited at both desktop widths in the account's existing Light preference. Three actual charts rendered: inventory bars, stock ring and movement trend. Snapshot totals: 10 inventory items; chart distribution 6 sufficient / 2 low / 2 unavailable. These are observed values, not a fresh stock-ledger reconciliation.
- Warehouse inventory: Add opened/cancelled; Air Filter DEMO26-PART-04 Edit opened/cancelled. View showed on-hand 12, reserved 10, available 2. Category filter applied; descending sort produced Wiper Blade first; unmatched search displayed the empty state; search cleared. No save or stock movement submitted.
- Warehouse delivery History: DEMO26-PO-RESTOCK-01 (5/5), DEMO26-PO-03 (4/4), DEMO26-PO-02 (4/4), all Delivered with Read only actions. Active list empty, so receiving-form execution is NOT certified.
- Expected permission denial observed when Operations attempted Maintenance before the user switched accounts; no permission bypass used.

## Automated checks on current code

| Check | Actual result |
| --- | --- |
| php artisan test | PASS: 972 tests, 7,879 assertions, 102,572ms |
| npm run test:frontend | PASS: 13 tests |
| npm run build | PASS |
| Changed PHP lint | PASS: FrontendUiUxComprehensiveTest.php |
| Changed JS and audit tool syntax | PASS |
| git diff --check | PASS at latest check |
| View clear/cache/clear | PASS: clear, cache and final clear completed |

Laravel tests use the repository testing configuration. Browser checks did not submit operational forms. The chart regression tests execute the actual initializer with a Chart stub: data is retained across a theme change, real zero counts are drawn, and chart instances/observer are cleaned up. They supplement rather than replace the pending browser theme recheck.

## Evidence

| Before | After |
| --- | --- |
| [Bus dark text](evidence/css-audit-2026-11-12/before-bus-dark.png) | [Bus dark text and badges](evidence/css-audit-2026-11-12/after-bus-dark.png) |
| [Fleet ring concealed / full dashboard baseline](evidence/css-audit-2026-11-12/before-operation-dashboard.png) | [Fleet ring visible](evidence/css-audit-2026-11-12/after-operation-fleet-dark.png) |
| [Bus Add dark](evidence/css-audit-2026-11-12/before-bus-add-dark.png) | [Bus Add dark](evidence/css-audit-2026-11-12/after-bus-add-dark.png) |
| [Trip Create dark](evidence/css-audit-2026-11-12/before-trip-create-dark.png) | [Trip Create dark](evidence/css-audit-2026-11-12/after-trip-create-dark.png) |
| [Warehouse laptop](evidence/css-audit-2026-11-12/before-warehouse-dashboard-laptop.png) | [Warehouse laptop](evidence/css-audit-2026-11-12/after-warehouse-dashboard-laptop.png) |

## Remaining work and limits

- Purchasing and Administration browser checks require the relevant signed-in role. No PASS claimed yet.
- Operations below-fold chart/theme changes need final browser validation, including navigating away/back and switching Light/Dark.
- Maintenance and Warehouse were checked under their existing Light account preference; no Dark-mode certification for those modules. Existing theme support is not expanded by claiming a global branch name.
- Mobile/tablet checks sampled Operations dashboard, buses and DDR. No document-wide overflow was observed, but the existing mobile layout stacks navigation above content, placing main content below the first screen. A speculative sidebar-height change was tested and reverted because it did not solve that existing design. No full mobile redesign was undertaken.
- Live Reverb delivery, actual approval/issuance/receiving mutations, signature execution, print dialogs, every record-dependent modal and every chart data combination have not been executed.
- Console evidence includes errors from chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/executors/200.js. These are extension-origin errors and are not attributed to GCT. No HAR/network-wide success claim is made.
- The local Vite hot file can point at an intermittent dev server. Browser asset verification used the existing local-only _gct_profile=1 option to load the compiled current build; no user dev server or hot file was removed.
- Final report, final commit SHA, normal push and exact pull command will be recorded after remaining browser checks.

