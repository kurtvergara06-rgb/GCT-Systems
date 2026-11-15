# Operation frontend and dark-mode audit

Audit dates: November 9 and 11, 2026 (Asia/Manila).
Target branch: `feature/global-dark-mode`.
Prior implementation commit: `711d8caaa0e5a65544ead3eb4ded88a0c68aa9d2`.

## Decision

**NOT READY FOR MERGE under the brief's exhaustive acceptance criteria.** Confirmed frontend palette defects were fixed, but the tests below do not establish that every possible workflow-generated dialog, success/error state, permission combination, network request or initial-paint frame passes. Auto Scheduling also reported unavailable AI ranking readiness. No operational submissions were made to manufacture those states.

This report distinguishes a passing executed check from complete workflow certification. A PASS in the page matrix covers the rendered page and the interactions listed here; it is not a claim that every control/state on that page was tested.

## Environment and branch safety

Initial browser audit used `http://gct_system.test` as Operations Head. Maintenance Head was used for one account-isolation sample, without changing that account's preferences.

On resumption, the original folder was on `feature/landing-page-ipad`, with an unrelated, uncommitted zero-byte `resources/js/Operation/Daily_Driver_Reports/daily-driver-reports.js` and untracked `commnad`. Both were preserved. The earlier theme fixes had already been committed as `711d8caa` on the requested branch.

Continuation used the existing `feature/global-dark-mode` branch in a separate Git worktree at `C:/Users/micha/Documents/Codex/gct-operation-theme-qa`, served at `http://gct_system.test:8765`. No additional branch was created. The isolated server uses built Vite assets and a separate session cookie. Ignored local dependencies/environment files were prepared without printing credentials or changing the original environment.

No main changes, merges, force pushes, migrations, account/permission changes, operational saves, deletes or production connections were performed. Browser date/time values and the unscheduled-trip checkbox were exercised only in an unsaved DDR form, then discarded. Normal authentication and browser preference changes were used for the requested theme checks. Automated database tests use the repository's local test-database guard.

## Page coverage

PASS = executed page-render/palette sample; Responsive PASS = no document-level horizontal overflow in the requested viewport measurements. Modal PASS = listed sample opened/closed; not exhaustive coverage of every confirmation/success/error variant. Intentional horizontal table scrolling is permitted. Overall is restricted to the executed samples.

| Page | Light | Dark | Responsive | Modals | Overall executed sample |
|---|---|---|---|---|---|
| Operations Command Center | PASS | PASS | PASS | N/A | PASS |
| Routes | PASS | PASS | PASS | PASS: New/View/Edit | PASS |
| Driver Master List | PASS | PASS | PASS | PASS: Add/View/Edit | PASS |
| Bus Master List | PASS | PASS | PASS | PASS: Add/View | PASS |
| Client Master List | PASS | PASS | PASS | PASS: Add/View/Edit | PASS |
| Driver Attendance | PASS | PASS | PASS | PASS: View | PASS |
| Mechanic Attendance, accessible directly | PASS | PASS | PASS | PASS: Record Attendance opens/closes | PASS |
| Client Trip Times | PASS | PASS | PASS | PASS: Expand All/Add Configuration | PASS |
| Trip Schedule | PASS | PASS | PASS | PASS: Create | PASS |
| Driver & Bus Assignment | PASS | PASS | PASS | PASS: View | PASS |
| Auto Scheduling | PASS | PASS | PASS | Generation/confirmation not submitted | FAIL: ranking readiness unavailable in audit |
| Trip History | PASS | PASS | PASS | PASS: View | PASS |
| Daily Drivers Report | PASS | PASS | PASS | PASS: Encode/View; date/time picker | PASS |
| Incident Management | PASS | PASS | PASS | PASS: Report; active Workflow; resolved Details | PASS |
| Bus Availability | PASS | PASS | PASS | PASS: Details | PASS |
| Standalone Incident Report | PASS | PASS | PASS | N/A | PASS |
| Assignment print sheet | PASS: screen preview | Separate print document; no theme assets | Not a five-viewport certification | N/A | PASS: date-specific preview; physical printing not tested |

The 15 main pages were visited in both themes. The viewport matrix used 1366x768, 1440x900, 1920x1080, 768x1024 and 390x844. The saved measurement file has one Bus Master repeat measurement at 1366 instead of 1440 in a post-fix run; earlier coverage included the five requested sizes. This measurement artifact is not presented as an additional independent 1440 check.

Five-size modal bounds were measured for New Route, Add Driver, Add Bus, Add Client, Encode DDR, active Incident Workflow and Bus Availability Details. Date and time popup bounds were additionally measured at all five sizes after the final picker fix. These checks found the sampled shells inside the viewport. Internal accessibility of every footer/action at every scroll position was not exhaustively tested.

## Confirmed defects and corrections

| Component / severity | Actual evidence and root cause | Correction and verification |
|---|---|---|
| Dashboard/cards/calendar shells / Medium | Legacy white backgrounds and gradient images remained; dark text aliases were not connected to semantic colors. Changing only background-color did not remove background-image. | Named Operation surface selectors use semantic `background`, text and border tokens. Dashboard shell verified as rgb(22,34,53); text rgb(237,243,252). Auto Scheduling unselected days and headings rechecked; selected-day blue preserved. |
| Modal forms and read-only details / High where unreadable | ID-specific legacy modal rules outranked class-only dark styles. Headings remained dark on dark shells; inner cards remained light. | Explicit modal-root selectors match the legacy specificity. Route, bus, client, attendance, assignment, trip and DDR portals sampled in browser. No positioning/event handlers changed. |
| DDR/Bus Availability table headings / High | Existing `background: ... !important` declarations defeated dark header rules, leaving pale text on a pale header. | Existing declarations now use `--gct-screen-table-head` with the original Light/print fallback. No new important declarations. Availability header verified rgb(32,48,71), foreground rgb(177,191,210). |
| DDR/Trip History inline text / High | Inline navy and literal muted colors overrode theme selectors. | Semantic screen-only variables with original fallback colors. Dates, names and sampled detail values rechecked. Print styling is not recolored by the new screen token bridge. |
| Bus details client allocation / Medium | `clientAllocationType` alone controlled the label even when actual `clientNames` were populated. ADQ-5369 could show Not allocated despite client records in the table. | Read-only JS fallback now uses actual client names when legacy allocation type is empty. ADQ-5369 displayed Batangas Industrial Services, Lipa Manufacturing Campus and authorized driver Ramon Santos. No relationships changed and no allocation inferred from the driver. |
| Embedded incident workflow / Medium | Its document already had theme=dark and Operation marker=1; missing palette rules, not failed theme initialization, caused white identity/tabs/cards. | Named iframe-document workflow surfaces/labels connected to semantic colors. All four tabs were opened. Identity, steps and side cards verified rgb(22,34,53). Approvals/response/deployment controls not submitted. |
| Resolved incident Details / Medium | A separate `#incidentRecordModal` UI, not the active workflow iframe, retained white cards and fixed dark labels. | Matched ID-scoped section/KPI/timeline rules. DEMO26-INC-02 verified with dark shell/sections and rgb(237,243,252) headings and values. Read-only behavior retained. |
| Shared date/time popup on Operation / High | Body portal `.gct-picker-popover` used white background while its inherited text was rgb(237,243,252). Parent form overrides could not cover the portal. | Operation-only, screen-only picker palette. Dark shell rgb(22,34,53); fields rgb(16,27,43); text rgb(237,243,252). Light remains white with rgb(11,35,68) text. Bounds passed five sizes; selected-date and primary-yellow styles excluded. |
| DDR exception and overnight guidance / High where pale text | `#ddrEncodeModal .ddr-exception-choice` and `.ddr-form-note` retained pale backgrounds under theme text. | Named surfaces and guidance labels connected to tokens. Checked exception uses theme hover/accent border. Unchecked/checked states exercised and discarded. Neutral read-only bus field was already readable and intentionally retained. |
| Availability maintenance status / Medium | Actual badge foreground rgb(29,78,216) on a translucent blue fill over a dark table was difficult to read. | Brightened only this Operation badge's text to rgb(155,199,255); label, icon, state and blue fill unchanged. Browser rechecked actual label color. |
| Scheduling test fixture / Test reliability | Full run failed `test_configured_client_departure_accepts_only_an_enabled_slot`: a date generated in the default timezone could make 07:00 past-dated in Manila. | Frozen UTC/Manila boundary and business-timezone tomorrow in the test only. Focused test passes. Backend future-departure rule unchanged. |

Relevant source files:

- `resources/css/Operation/operation-dark-mode.css`: named screen palette bridges, modal specificity, picker body portals, incident details and one verified status foreground.
- `resources/css/Operation/Daily_Driver_Reports/daily-driver-reports.css`: existing important header declaration with screen-token fallback (711d8caa).
- `resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css`: same header fallback (711d8caa).
- `resources/views/Operation/Daily_Driver_Reports/index.blade.php` and `resources/views/Operation/Trip_Records/trip-records.blade.php`: inline color fallbacks (711d8caa).
- `resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php`: plate supporting-text fallback (711d8caa).
- `resources/js/Operation/Shuttle_Bus_Management/bus-master-list.js`: read-only allocation label fallback (711d8caa).
- `tests/Feature/OperationDarkModeFoundationTest.php`: palette, portal, scope and fallback regression assertions.
- `tests/Feature/FrontendUiUxComprehensiveTest.php`: assertions updated for pre-existing semantic sticky tokens (711d8caa).
- `tests/Feature/OperationTripScheduleHardeningTest.php`: deterministic test date only.

Shared layout loading order, theme tokens, account preference hydration, partial navigation, identifiers, toolbars, shared enhancement and scrollbar styles were inspected. The Operation stylesheet loads after page assets. Existing duplicate/legacy page rules remain where removal could affect Light/print or other workflows. No blanket global color rule or new important declaration was added. Brand-yellow actions, colored state indicators, map tiles and deliberately neutral/read-only controls were evaluated separately, rather than classifying every light surface as defective.

## Executed interactions and actual records

- Search/Filter/Sort triggers opened and closed across applicable lists. Bus Availability search ADQ-5369 reduced 40 rows to one and clearing restored the list. Applying every filter/sort combination was not tested.
- Driver Arnel Garcia View fields were disabled. Add Driver bus picker opened and reported no matching available buses when all choices were assigned. No driver saved.
- New Route blank submission blocked at required Origin with “Please fill out this field.” Route count was not changed.
- Driver table at 390 width: keyboard horizontal scroll reached scrollLeft=40; Actions header and row cell shared left=203.17/right=360 within wrapper right=361. This verifies one sticky-action example, not every table.
- Bus Availability Details ADQ-5369 stayed open after backdrop click. Close worked. Five-size bounds passed.
- Client Batangas Industrial Services Add/View/Edit opened and closed. No changes submitted.
- Driver Attendance Joel Mendoza, November 3, and DDR DEMO26-DDR-20261103-1 were inspected. The DDR showed two trip entries and 43 passengers. No DDR saved.
- Trip/assignment samples included TRIP-DEMO26-20261109-1 and TRIP-DEMO26-20261109-3. Past/protected edit/remove actions stayed disabled where displayed.
- Active incident DEMO26-INC-03: Details, Updates, Related Records and Workflow tabs opened; no OIC response, deployment, referral or status submission.
- Incident History settled to four Resolved records: DEMO26-INC-REPLACEMENT, DEMO26-INC-02, DEMO26-INC-ROAD-01 and DEMO26-INC-01. Resolved DEMO26-INC-02 details showed referral 5. Existing missing route/driver values were displayed as missing; no data invented.
- Assignment print preview for 2026-11-09 showed three saved assignments with actual Pending DDR/Assigned and Cancelled/Assigned states. A request without required trip_date produced a validation notification; the valid date-specific preview rendered. No print job or signature was generated.
- Date picker advanced November to December 2026 and back. Selecting November 3 set form value 2026-11-03. Time 09:15 AM set departure value 09:15. Form was cancelled.
- Logout confirmation, cancel/close paths and same-account re-login were exercised. Dark preference returned for Operations Head in the same browser origin.
- Light/Dark switching, refresh persistence, System following device Dark, and partial sidebar navigation were observed. Maintenance Head retained its own Light preference; dashboard had no Operation marker and color-scheme=light. This is one department-boundary sample, not an audit of every Maintenance/shared route.

## Browser errors and remaining limits

Auto Scheduling logged: `Auto Scheduling ranking source could not be loaded: Error: Operation AI readiness is unavailable.` Its status source is `/operation/auto-scheduling/ai/training/status`. The visible ranking panel reported Bus/Driver Unavailable in the original audit. This is confirmed frontend/service evidence; the underlying AI service failure was not diagnosed or changed in this UI-only task. Do not mark the model ready artificially.

No complete HTTP status/network HAR capture was available through the browser tooling. Console and asset observations do not prove every request passed. During isolated-environment setup, a request before the build finished produced a missing-manifest error; the successful build and subsequent renders resolved that setup error. It is not classified as a production code defect.

Not certified: all workflow-generated success/error/confirmation variants; every hover/focus/disabled state on every page; every filter/sort/pagination combination; every RBAC role; realtime mutation broadcasts; frame-by-frame absence of initial-paint flash; event-listener counts; physical PDF/print output; exact chart canvas contrast at every viewport. The mobile sidebar stacks above content and requires scrolling; root no-overflow results alone do not certify its usability.

## Validation

- `php artisan optimize:clear`: PASS in the isolated worktree.
- `php artisan test --filter=OperationDarkModeFoundationTest`: PASS, 7 tests / 73 assertions.
- `php artisan test --filter=test_configured_client_departure_accepts_only_an_enabled_slot`: PASS, 1 test / 8 assertions.
- Final `php artisan test`: PASS, 958 tests / 7,741 assertions, 86.951 seconds. The preceding run's timezone-fixture failure is documented above rather than omitted.
- Final `npm.cmd run build`: PASS; Vite completed in 1.54 seconds.
- `php artisan view:clear`, `view:cache`, `view:clear`: PASS.
- PHP lint of the two PHP test files changed in this continuation: PASS. Earlier changed Blade/test files were also linted during the initial audit.
- `git diff --check`: PASS.
- Browser: dark date/time popup five-size bounds, calendar navigation and time selection PASS; Light picker palette PASS; incident History and read-only details PASS; maintenance badge foreground PASS. Normal origin theme persistence and one cross-account boundary sample PASS as detailed above.
- Final isolated Auto Scheduling check: ranking panel again showed Bus/Driver Unavailable with the same console warning; unresolved.

There is no configured frontend lint script in package.json. Browser interactions and computed styles provide the manual frontend evidence; source assertions alone are not treated as visual PASS.

## Evidence

Local evidence folder: `C:/Users/micha/Documents/Codex/operation-qa-evidence`.

Useful files: `picker-dark-fixed-2026-11-11.jpg`, `picker-light-2026-11-11.jpg`, `picker-viewport-checks.json`, `incident-history-dark-2026-11-11.jpg`, `availability-dark-final-2026-11-11.jpg`, `assignment-print-2026-11-11.jpg`, `driver-mobile-sticky.jpg`, `bus-details-dark.jpg`, `maintenance-boundary.jpg`, `final-audit.json`, `modal-audit.json`.

Some earlier screenshots captured entrance-animation frames or have generic/postfix names. They are supporting evidence only; computed styles and settled follow-up observations were used before reporting corrections. The report does not treat transient animation screenshots as blank-page defects.

## Recommended next steps

1. Diagnose the unavailable AI readiness service separately; keep truthful fallback labels meanwhile.
2. Finish the unverified acceptance combinations above on the correct branch before a full READY FOR MERGE claim.
3. Review the separate zero-byte DDR script edit on feature/landing-page-ipad before using that folder as a QA target. It is unrelated to this commit and was not overwritten.
4. Review the committed frontend diff and this report. No main merge or deployment is authorized by this audit.
