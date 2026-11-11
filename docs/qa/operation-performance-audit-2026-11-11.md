# Operations performance and lifecycle audit — 2026-11-11

Branch: `feature/global-dark-mode`. Baseline: `45d5ca27050e1cae74257f403635dd1e2caf4ca9`.
Local application: `http://gct_system.test`; existing Operations Head session; local `gct_system_clean`. No operational records were created, edited or deleted during browser verification. PHPUnit uses the guarded local `testing` database. No production connection, deployment, schema change, permission change or cache of safety-critical data was introduced.

## Findings and changes

| Problem | Confirmed root cause | Solution and files | Verification |
| --- | --- | --- | --- |
| Attendance made redundant reads | Initial `tripAssignments` eager load was unused; the view uses a second, date-specific batch. Four independent KPI queries repeated the same DDR confirmation predicate. | Remove unused preload and aggregate the four summary values in one query in `DriverAttendanceController.php`. Keep date-specific assignments and DDR confirmation. | Controller/view query count 16 → 9 (44% fewer); populated and date-filtered summary regression checks. |
| DDR repeated counts and bus reads | Today's primary and additional trips were counted/summed separately; historical bus list fetched twice. | Aggregate each source once; reuse the existing historical encode bus collection in `DailyDriverReportController.php`. | Query count 18 → 14 (22% fewer); additional-trip totals, independent filters and inactive historical bus tests. |
| History pages fetched all remaining records after opening | Shared pagination defaults to eagerly loading every page; DDR and Attendance had not opted into its existing lazy mode. | Opt these two Blade footers into lazy pagination. No change to other departments' pagination defaults. | DDR initially 8/89, Attendance 10/83; scrolling loaded 16 and 20 respectively. |
| View failed for DDR records appended by pagination | Modal portals omitted `.ddr-record-overlay`; listeners were bound only to initial buttons. | Include DDR portals in `scroll-table-pagination.js`; delegate View/Close clicks in `daily-driver-reports.js`. | Before: clicking oldest appended DDR opened zero modals. After: `DEMO26-DDR-20261028-3` opened its actual details (Nestor Aquino, NFX-8642, 32 passengers, 22.22 km). Backdrop preserved it; Close dismissed it. |
| View failed after server-side filtering/sorting | Table replacement omitted DDR detail portals, so returned rows referenced missing modal IDs. | Synchronize and prune DDR-scoped portals in `automatic-table-search.js`. | Authenticated browser filter → ascending sort → historical View passed; two executable portal/isolation tests passed. |
| Late pagination responses could append after leaving a page | Fetches had no page-scoped cancellation or connectivity checks. Concurrent load-all could loop while a scroll request was pending. | Abort on navigation, check ownership after asynchronous work, deduplicate pending requests. Shared pagination file. | Executable tests cover stale response and concurrent scroll/load-all with DDR portals. |
| Some initializer listeners survived cleanup | Listeners with caller-owned AbortSignals bypassed initializer cleanup. | Track their removal without aborting the caller's signal in `partial-navigation.js`. | Runtime tests reproduced failures before; passing failed-initializer and page teardown checks after. |
| A superseded navigation could redirect to an older URL | Failure fallback and response validation lacked ownership checks despite the existing guard after asset loading. | Add stale guards after response/text and before fallback; preserve current-request full-navigation fallback. | Runtime tests reproduce late HTTP and asset failures; passing after fixes. |
| DDR details were unreadable in Dark Mode | Hardcoded light summary/comparison backgrounds and dark Close text conflicted with shared dark foreground rules. | Replace the original declarations with existing semantic colors in DDR CSS; no additional override layer. | Screenshots of the same populated report in Light and Dark Mode; original Dark preference restored. |
| Normal local navigation sometimes stalls | Existing ignored `public/hot` points to a local Vite server whose response timed out; built assets render the same pages. | Local-only explicit `?_gct_profile=1` selects compiled assets using a cloned Vite instance; the normal development hot file/server is untouched. | Browser verified both the stalled normal settings navigation and successful built-asset settings page. Treat this as an environment finding, not a proven sole cause of all slowness. |

## Measurement method and limitations

`php tools/profiling/operation-queries.php --samples=3 --date=2026-11-11` invokes the seven actual controllers and renders their Blade views. It requires APP_ENV=local, loopback MySQL, database exactly `gct_system_clean`, no DB_URL, and a MySQL READ ONLY transaction rolled back in finally. It does not execute HTTP middleware or measure browser response time. It outputs query fingerprints without bound values or credentials. Cold view compilation and warm render results must not be compared as an optimization speedup.

For browser measurements, build first and open each route with `?_gct_profile=1`. The local-only opt-in loads `resources/js/Diagnostics/performance-profile.js` and exposes native Navigation Timing, buffered LCP/CLS/long tasks, resource timing, initializer duration, DOM and heap snapshots in `html[data-gct-performance]`. It sends no telemetry. Normal and production pages do not enable the diagnostic module. Request counts include observed resources/cache entries; transfer bytes are a lower bound because cache/cross-origin timings can report zero. Herd's injected development tooling is included. Heap snapshots are noisy and do not establish a leak. A sample during full-suite execution is labelled contended, not used as evidence of a speedup.

Raw local evidence (not committed): `storage/logs/operation-perf-before.json`, `operation-perf-after.json`, `frontend-lifecycle-before.log`, `perf-focused-tests.log`, `perf-full-tests.log`, `perf-build.log`; browser JSON and screenshots under `C:/Users/micha/Documents/Codex/operation-performance-2026-11-11`.

### Database counts (same local data)

| Page | Before | After |
| --- | ---: | ---: |
| Operation Dashboard | 29 | 29 |
| Driver Master List | 13 | 13 |
| Driver & Bus Assignment | 25 | 25 |
| Trip Schedule | 24 | 24 |
| Auto Scheduling | 11 | 11 |
| Driver Attendance | 16 | 9 |
| DDR | 18 | 14 |

No index or caching change was justified by these measurements. Warm baseline SQL sample times for Attendance/DDR were 15.95/20.26 ms; after samples were 4.94/12.33 ms. Other unchanged pages also ran faster in the second CLI run, so timing differences are environment-sensitive; query elimination is the reliable measured result.

## Preservation and regression boundaries

- Sidebar links remain full-page navigation. No blanket partial-navigation rollout.
- GSAP, Reverb/Echo, chart dependencies and permission checks remain present. Lifecycle changes clean resources without changing eligibility rules.
- Existing chart and realtime Vite chunks stay split; chart code is not removed because of bundle size. Operation dark styles remain scoped. No speculative global CSS purge or new `!important` layer.
- Attendance View for a newly loaded row showed Joel Mendoza, Oct 27, 2026, and the matching completed trip `TRIP-DEMO26-20261027-1` on NEG-1484. The date relationship remains correct.
- Server-side DDR search found unloaded historical record `DEMO26-DDR-20261001-1` through table replacement, without a full document reload.
- Important specification conflict: the existing UI and controller deliberately use active, licensed drivers for planning and record actual Present attendance retrospectively from DDR. Auto Scheduling labels these “Active · assumed present”; planning does not create attendance. Imposing a new Present-only gate would alter established business behavior and was not done.
- Workflow transitions, approvals, incident/OIC rules and eligibility are checked by automated regression tests; they were not all executed as new browser transactions. Reverb delivery needs a second live session/event for a full end-to-end test. Print/export and every cross-department modal are not claimed browser-passed.

## Validation and remaining work

Final suite, browser timing comparison and readiness results follow.
## Final browser measurements

Milliseconds; one retained warm sample per page after compilation checks. These local observations are not controlled production benchmarks. No percentage speedup is attributed to unchanged controllers or cache effects.

| Page | Before warm TTFB / load | After warm TTFB / load / LCP | After resources | After transfer bytes (lower bound) | After DOM nodes |
| --- | ---: | ---: | ---: | ---: | ---: |
| dashboard | 946 / 4406.9 | 527.2 / 1800.3 / 848 | 41 | 22998 | 1197 |
| drivers | 623.8 / 2011.9 | 253 / 845.2 / 404 | 41 | 21762 | 1312 |
| assignments | 746.1 / 1955.6 | 216.7 / 855.2 / 392 | 43 | 24014 | 1057 |
| schedule | 464.1 / 1812.5 | 220.6 / 738.9 / 412 | 44 | 23410 | 924 |
| auto | 256.7 / 1623 | 152.4 / 822 / 312 | 45 | 21589 | 894 |
| attendance | Not captured warm | 138.5 / 627.1 / 312 | 41 | 19747 | 834 |
| ddr | Not captured warm | 278.7 / 1105.4 / 408 | 41 | 25942 | 2000 |

Attendance and DDR baseline warm samples were not completed: browser-control timeouts occurred while background page loading was active. Their recorded first-load baseline TTFB/load was 2915.9/4024.3 and 5489.4/6611.7 ms respectively; comparing those cold samples to warm after samples would overstate the improvement.

After view clearing, cold after TTFB ranged from 1099 ms (Auto Scheduling) to 5832.6 ms (Assignment); warm after TTFB ranged from 138.5 to 527.2 ms. Blade compilation is a material local first-load cost. Production startup already runs `view:cache` (`docker/start.sh`); that script was inspected, not changed or executed on production.

After warm CLS was zero for six pages and 0.0013 for Dashboard. Observed long-task totals were 453–879 ms in retained run 4; profiler and Herd overhead are included. Initializer timing is not equivalent to all browser JavaScript execution. Modal checks establish functional responsiveness; no separate high-resolution click-to-paint latency is claimed.

Five browser Back/Forward cycles between Attendance and DDR retained stable per-page DOM/script/stylesheet counts (834/10/18 and 2000/12/18), eight DDR portals and no duplicate IDs. Heap fluctuated approximately 9.6–31.0 MB, which does not prove leak absence. These were browser history traversals; no partial-navigation timing logs were emitted, so they are not claimed as measured partial transitions. The executable tests cover that lifecycle directly.

## Actual final checks

- Full `php artisan test`: PASS, 970 tests, 7,868 assertions. Initial run 399.318 seconds; final run after the last shared-table fix 392.749 seconds.
- Focused `DailyDriverReportTest`: PASS, 22 tests, 129 assertions (included in full suite).
- `npm run test:frontend`: PASS, ten executable tests. The baseline partial lifecycle harness reproduced four failures before fixes. Pagination tests were added after that baseline.
- `npm run build`: PASS (latest build 12.87 seconds; plugin timing advisory, no build failure).
- PHP lint: PASS on both controllers, feature test, profiling tool and all three changed Blade files.
- `php artisan view:clear`, `view:cache`, `view:clear`: PASS.
- `git diff --check`: PASS.
- Browser: all seven pages rendered using compiled assets; DDR/Attendance lazy scroll, appended View, DDR Close/backdrop, historical search and Light/Dark checks executed.
- Auto Scheduling reports AI readiness unavailable. Generation/ranking with that service is not browser-passed.
- Reverb connection errors were captured in later console logs. Live delivery is unverified; preserved imports do not establish runtime connectivity.
- Initial final-filter attempts were blocked by an expired session. After user sign-in, filtering/sorting returned correct rows; the separate missing-modal defect was reproduced, fixed and browser-retested successfully (details below).

Readiness: targeted changes pass automated validation and the completed browser checks. Production performance, live two-session Reverb delivery, all new transaction paths, print/export and every department modal are not certified by this audit.

Recommended follow-up: diagnose the local Vite server before judging normal development navigation; verify AI and Reverb service readiness; measure production with compiled/warmed views; run multi-session realtime and non-production transaction acceptance checks. Do not disable eligibility or security rules to improve timings.

## Final authenticated filter/sort correction

After the user restored the session, driver filtering returned the correct eight of twelve Joel Mendoza reports and ascending sorting correctly placed October 4 first. However, View still failed: the row referenced `ddrView-10`, which was absent from the DOM. This second failure was a confirmed application defect, distinct from the earlier expired-session response.

`automatic-table-search.js` replaced only table rows/footer and omitted DDR detail portals. Added DDR-scoped synchronization of those portals on a successful table replacement, pruning old details before appending the returned page-owned details. Other departments are excluded. Two executable tests cover new portal ownership/replacement and department isolation. Ten frontend tests now pass.

Browser retest PASS: filter Joel Mendoza → ascending Report Date → View `DEMO26-DDR-20261004-1` showed NBG-6729, two trips, 49 passengers and 71.43 km; Close worked. No duplicate IDs and exactly eight DDR portals remained after replacement. The `data-gct-performance-profile` body marker remained present after the query flag disappeared from the address, confirming an in-place update rather than a document reload. Screenshot: `ddr-filter-sort-view.jpg` in the evidence directory.

DDR page/modal checks at 1366×768 and 1920×1080 found no document-level horizontal overflow; the populated modal fit, with the existing internal vertical scrolling. Browser viewport and original Dark preference were restored. These viewport checks cover the affected DDR surface, not every application page at every breakpoint.

Recorded warm page-load timings predate this final filter-only correction; its behavior is separately browser-tested and the assets rebuilt. A final full Laravel suite was rerun after the shared table correction; final result below.
Final full regression result: **PASS — 970/970 tests, 7,868 assertions, 392.749 seconds**, after the filtered-modal correction. Ten frontend tests and the production build also passed. The branch has no unresolved merge conflicts and the staged whitespace check passed.

For an existing clean checkout of the approved branch:

```powershell
git switch feature/global-dark-mode
git pull --ff-only origin feature/global-dark-mode
```

The targeted patch is ready for review. AI/Reverb service connectivity and the broader live acceptance checks listed above remain separate work; this audit does not certify the whole system as production-ready.