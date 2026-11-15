# Operations layout source review — 2026-11-12

Scope: feature/global-dark-mode. Source-level inspection only. Automated suites and browser verification were intentionally skipped for this pass. Earlier audit results do not validate these later changes.

## Source inventory

Indexed all 24 Operation Blade files, 21 Operation JavaScript files and 739 modal/table/grid sizing declarations. This is static coverage, not proof that every conditional runtime state was inspected.

| View | Tables | Modal IDs |
| --- | ---: | --- |
| resources/views/Operation/Attendance/driver-attendance.blade.php | 2 | viewDriverAttendanceModal, closeViewDriverAttendanceModal, attendanceModalTripsTitle, attendanceModalTripsCount, attendanceModalTripsBody |
| resources/views/Operation/Attendance/mechanic-attendance.blade.php | 1 | viewMechanicAttendanceModal, closeViewMechanicAttendanceModal, dismissViewMechanicAttendanceModal, editMechanicAttendanceModal, closeEditMechanicAttendanceModal, cancelEditMechanicAttendanceModal, deleteAttendanceModal |
| resources/views/Operation/Clients/index.blade.php | 1 | clientMasterDetailModal, clientMasterCreateModal |
| resources/views/Operation/Daily_Driver_Reports/index.blade.php | 2 | openEncodeReportModal, ddrEncodeModal |
| resources/views/Operation/dashboard-operation.blade.php | 1 | None declared locally |
| resources/views/Operation/Incidents/create.blade.php | 0 | None declared locally |
| resources/views/Operation/Incidents/index.blade.php | 1 | openIncidentReportModal, incidentRecordModal, incidentRecordModalTitle, incidentRecordModalSubtitle, incidentReportModal, incidentWorkflowModal, incidentWorkflowModalTitle |
| resources/views/Operation/Incidents/partials/embedded-workflow.blade.php | 0 | None declared locally |
| resources/views/Operation/Incidents/show.blade.php | 0 | None declared locally |
| resources/views/Operation/Incidents/_form-fields.blade.php | 0 | None declared locally |
| resources/views/Operation/Personnel Management/driver_master_list.blade.php | 1 | personnelModalTitle, personnelModalSubtitle |
| resources/views/Operation/Personnel Management/mechanic_master_list.blade.php | 1 | personnelModalTitle |
| resources/views/Operation/Routes/routes-stops.blade.php | 1 | openRouteModal, routeModal, routeModalTitle, cancelRouteModal, closeRouteModal, routeDetailsModal |
| resources/views/Operation/Scheduling_And_Dispatch/assignment-print.blade.php | 1 | None declared locally |
| resources/views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php | 2 | None declared locally |
| resources/views/Operation/Scheduling_And_Dispatch/client-trip-times.blade.php | 0 | cttConfigModal, cttSlotModal |
| resources/views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php | 1 | assignmentModal, assignmentModalTitle, cancelAssignmentModal, closeAssignmentModal, viewAssignmentModal, closeViewAssignmentModal, removeAssignmentModal |
| resources/views/Operation/Scheduling_And_Dispatch/partials/client-trip-time-panel.blade.php | 0 | None declared locally |
| resources/views/Operation/Scheduling_And_Dispatch/partials/trip-schedule-filters.blade.php | 0 | None declared locally |
| resources/views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php | 1 | openTripModal, tripModal, tripModalTitle, cancelTripModal, closeTripModal, cancelServiceTripModal, viewTripModal, closeViewTripModal |
| resources/views/Operation/Shuttle_Bus_Management/bus-availability.blade.php | 1 | availabilityBusModal |
| resources/views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php | 1 | openBusModal, viewBusModal, closeViewBusModal, dismissViewBusModal, busModal, closeBusModal, cancelBusModal, editBusModal, closeEditBusModal, cancelEditBusModal, deleteBusModal |
| resources/views/Operation/Trip_Records/assignment-history.blade.php | 1 | None declared locally |
| resources/views/Operation/Trip_Records/trip-records.blade.php | 3 | tripDetailModal, modalTripCode, modalStatus, modalDdrTimeRows, modalTripDate, modalRouteName, modalRouteSpan, modalDistance, modalEstTime, modalBusNo, modalBusDetails, modalDriverName, modalDriverId, modalSchedDept, modalSchedArr, modalActualDept, modalActualArr, modalDuration, modalNotes |

## Changes

| Source | Original conflict or constraint | Change |
| --- | --- | --- |
| Routes/routes-stops.css | Desktop viewer expanded by overlapping 1360px and 1380px blocks; map forced to at least 430px | Removed redundant desktop modal/body/map declarations; retained ID-scoped two-column layout capped at 1120px, 88dvh, 300–440px map. Existing single-column breakpoint and mobile map dimensions preserved. |
| Shuttle_Bus_Management/bus-master-list.css | Final table rule forced 1250px minimum; action column forced 150px | Regular minimum 940px; Reserve 820px. Rebalanced seven columns to 12/11/13/14/16/19/15 percent. Removed action minimum pressure; retained scroll fallback, sticky actions and all columns. |
| Attendance/personnel-master.css | Driver table final minimum 1120px | Lowered to 960px. Existing wrapping/disclosure, six columns, actions, and scroll fallback retained. |
| Clients/client-master-list.css | Six-column table minimum 1020px | Lowered to 960px; existing proportions, typography and actions retained. |
| Scheduling_And_Dispatch/driver-bus-assignment.css | Repeated minimum widths 900/1060/0/1120px | Removed three superseded minimum declarations; final table rule owns 960px minimum. |

No new stylesheet, dependency, test file, CSS override layer, !important declaration, hidden column or smaller text was introduced by this pass. Existing audit changes were retained. No business/controller/route/permission logic changed. No production or database record was modified.

## Disposition of remaining pages

| Area | Source disposition |
| --- | --- |
| Dashboard | Existing audit chart/theme changes preserved; no further arbitrary density change. |
| Routes | Viewer changed; map/editor JS invalidates map size after opening. Fit Route, zoom, saved OSRM geometry and DOM controls untouched. Actual map behavior not verified. |
| Drivers, buses, clients, assignment | Minimum-width changes above. Fit at 1366/1440/1920 remains unverified; content-dependent widths may still require scrolling. |
| Driver and mechanic attendance | Existing compact modal and scrollable detail-table rules retained. No evidence justified blanket shrinking. |
| Client Trip Times | Nested editing/configuration panels retained; no fields or time columns removed. |
| Trip Schedule | Multi-section trip form and calendar retained; prior audit fixes preserved. |
| Auto Scheduling | Generated directional schedules/conflict reports retained. Their dense columns and approval controls make blind shrinking unsafe. |
| Trip Records / assignment history | Wide history tables retained: nine columns combine dates, route, resources, slots, status and actions. Layout may still scroll on desktop. |
| DDR | Wide operational table and paper/report modals retained to protect timestamp/mileage visibility. Desktop overflow not claimed fixed. |
| Incidents, create/show/embedded workflow | Existing responsive workflow and replacement/referral panels retained; source size alone does not establish excess space. |
| Bus Availability | Existing compact table/detail modal rules retained. |
| Assignment print | Print layout retained. |

## Validation and limitations

- git diff --check passed after changes. Changed stylesheets were parsed by the existing PostCSS dependency during the edits. This is source validation only.
- No php artisan test, npm run test:frontend, browser automation suite, build, or browser verification was run for this pass.
- All six requested widths (1920, 1440, 1366, 1024, 768, 390) still require rendered review. There is no viewport PASS claim.
- No CRUD submissions, modal-backdrop interactions, focus traversal, pagination, map interactions or theme-switch checks were executed in this pass. Their hooks were not changed.
- Dense tables remain intentionally unchanged where readable fit could not be established confidently without testing. This is a targeted source improvement, not certification that every oversized/overflowing component has been repaired.

## Git

Current branch: feature/global-dark-mode. Current HEAD: 738885ff72c4559dc3bf9ff13da5a38bedc7f92f (earlier duplicate cleanup). These layout changes are uncommitted and unpushed. The working tree also contains existing audit modifications and untracked documentation/tests from the earlier task; none were discarded or newly attributed to this pass. Main was not modified.


## Phase 2 continuation — source-only review

The continuation preserves all prior modifications. No test suite, browser automation, build, commit or push was performed for Phase 2.

### Additional source corrections

| File | Confirmed source cause | Correction |
| --- | --- | --- |
| resources/css/Operation/Attendance/batch-attendance.css | Driver-only batch dialog required 1440px width and fixed height up to 860px even for a short roster | Original desktop rule now uses width min(1180px, viewport minus 48px), height auto and max-height 88dvh. Header padding reduced from 22/24/14px to 16/24/12px. Existing table scrolling, fixed control/footer flex sizing, 1040px dense roster minimum, and mobile rules remain. Mechanic batch rules are not resized. |
| resources/css/Operation/Attendance/driver-attendance.css | Three superseded 900px breakpoint rules declared table minima 900, 960 and 880px; later 1000px rule widened it to 1020px | One existing final breakpoint now owns an 880px minimum. Superseded declarations and resulting empty media blocks removed. All six columns, date/time content, 88px View action and scrolling fallback retained. |
| resources/css/Operation/Incidents/incidents.css | Base 92vh height/480px minimum and mobile 96vh height were defeated by the later scoped viewport-safety block | Removed only those defeated height declarations. Existing scoped owner still uses min(92dvh,960px), min-height 0 and max-height viewport minus 48px. Iframe flex sizing retained; do not collapse an iframe workflow to auto height. |

### Remaining-page review and decisions

| Page/component | Source review / decision |
| --- | --- |
| Dashboard | Existing chart shells, card grids, theme rules and lifecycle work retained. No additional decorative resizing. |
| Routes | Prior 1120px desktop consolidation retained; two columns above 1100px and single column below remain. Map geometry and JS resize hooks unchanged. |
| Driver Master | Prior 960px table minimum retained; authorized-bus picker and disclosure content remain. |
| Bus Master | Prior seven-column rebalancing retained; sticky Actions and responsive scrolling remain. |
| Clients | Prior 960px minimum retained; existing six-column proportions and CRUD controls unchanged. |
| Driver Attendance | Batch modal corrected and tablet width cascade consolidated as above. Read-only attendance View remains 900px with its own trip-detail scroll area. |
| Mechanic Attendance / master | Separate shared personnel and batch rules retained. Driver-only changes do not resize mechanic dialog. |
| Client Trip Times | Blade uses shared medium form modals for configuration and slots. Nested category/time controls retained. No evidence supports blanket reduction. |
| Trip Schedule | Multi-section form, availability/calendar widgets and shared form controls retained; no form or approval hook altered. |
| Auto Scheduling | Generated directional tables and conflict report panels retain minimum widths and scroll access. Dense resource/approval columns cannot safely be certified at laptop width without rendering. |
| Driver & Bus Assignment | Prior single 960px minimum owner retained; resource-table and pairing interfaces unchanged. |
| Trip Records / assignment history | Existing 930px detail dialog uses a flex middle scroll area. Broad history tables retain scrolling so route/resource/timing/status data stays readable. |
| DDR | Existing record-table wrapper has auto horizontal scrolling and a scoped 680px minimum. Wide paper/details/encode layouts retained; no assumption that every .ddr-table rule applies to the current record table. |
| Incident list, create, show, embedded workflow | Record dialog already caps at 650px. Large iframe workflow retained, with obsolete height declarations consolidated. Its internal sections need rendered inspection before further compaction. |
| Bus Availability | Existing 760px table floor and 740px detail modal retained; actions remain 34px. |
| Print and partial views | Assignment print, incident fields/embedded workflow, client-trip-time panel and schedule filters retained. No print sizing or dynamic initializer changed. |

This is a complete static inventory/disposition pass, not an exhaustive rendered-state audit. Short-viewport footer access, long translated content, per-account permissions, conditional columns, maps, nested pickers and all six requested viewport widths remain unverified. Dense tables intentionally retained may still scroll on desktop. No visual PASS or universal no-overflow claim is made.

Source review: changed CSS parsed successfully with the existing PostCSS dependency. git diff --check passed after the edits. Automated tests were intentionally skipped. The test files already present in the working tree belong to the earlier system-wide audit; Phase 2 introduced no tests.

Branch remains feature/global-dark-mode. HEAD remains 738885ff72c4559dc3bf9ff13da5a38bedc7f92f. Phase 2 adds changes to the three CSS files above and this report; all prior tracked/untracked work remains present. No changes were committed or pushed. Main and production remain untouched.


## Phase 3 browser checkpoint — incomplete

Existing Operations Head session used in Brave. Current source rebuilt successfully; no automated suites run. Manual browser measurements at 1366x768, Light mode:

| Page opened | Table width / parent width |
| --- | --- |
| Bus Master List | 967 / 967 |
| Routes | 1105 / 968 |
| Operations Command Center | 1125 / 608 |
| Driver Master List | 963 / 963 |
| Client Master List | 969 / 969 |
| Driver Attendance | 964 / 964 |
| Mechanic Attendance | 964 / 964 |
| Trip Schedule | 1280 / 969, 1280 / 969 |
| Auto Scheduling | Opened; calendar and empty recommendation states observed. No recommendations generated. |
| Assignment | 977 / 977 (two tables) |
| Trip History | 1460 / 967 |
| DDR | 980 / 968 |
| Incidents | 963 / 963 |
| Bus Availability | 981 / 981 |

Route Details DEMO26-RT-03 opened. Measured approximately 1120px x 676px within 768px viewport. Confirmed overlapping Route Information and Route Path: grid row was 284px while its card was 416px tall. Added grid-template-rows:max-content max-content to the existing desktop body rule. Rebuilt; saved after screenshot shows Path below Information, but it caught the opening animation and is not final stable-state certification. Source route has no saved OSRM geometry; reference marker displayed, map tiles appeared gray. No real map geometry was fabricated.

Remaining defects: Bus table fits but client disclosures/IDs appear cramped; Routes, Trip Schedule, Trip History and DDR retain measured internal overflow. No blanket no-overflow PASS. Dark mode, other requested sizes, driver batch dialog, incident dialogs, other Add/Edit/View/confirmation states, backdrop behavior and final route stable recheck remain pending. Earlier phase results do not cover these states. Inspection was interrupted by a browser runtime timeout; connection recovered and remaining main pages were opened, but not every screenshot or modal was visually reviewed.

Phase 3 changed only routes-stops.css plus this report, alongside all preserved prior work. Build and git diff --check passed. Branch feature/global-dark-mode, HEAD 738885ff72c4559dc3bf9ff13da5a38bedc7f92f. All changes remain uncommitted and unpushed. No data mutations or production actions.
