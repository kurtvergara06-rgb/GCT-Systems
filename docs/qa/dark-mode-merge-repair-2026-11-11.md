# Dark-mode merge repair — November 11, 2026

The reported Vite `Invalid empty selector` error came from unresolved Git conflict markers in `resources/css/Operation/operation-dark-mode.css`. The merge had been started on `feature/landing-page-ipad`, not the requested target branch. Source-string PHPUnit checks passed without parsing CSS, so they could not establish that the Vite build was valid.

The conflicted CSS, merged test file, staged diff and working diff were copied to `C:/Users/micha/Documents/Codex/merge-repair-2026-11-11` before aborting that mistaken merge. The user's existing stash was preserved without applying its empty DDR script.

The correct merge combines local `feature/global-dark-mode` at `1a443bc7` with remote `origin/feature/global-dark-mode` at `6c40939c`. Both the audited Operation portal/picker/history corrections and the remote Command Center reference-table/fleet-card corrections are retained. A regression test now rejects unresolved CSS merge markers; the actual Vite build remains required.

The two extra local branches, `feature/landing-page-ipad` and `backup/dark-mode-local-before-merge`, both pointed to `711d8caa`. That commit is already an ancestor of `1a443bc7`; no additional source changes are unique to either branch. They can be deleted with normal merged-branch checks after the combined target is validated and pushed. `main` and `backup/gct-history` are preserved. No production configuration, operational records, database schema or workflow rules changed.

Validation completed before commit:

- Focused theme tests: PASS, 9 tests / 84 assertions.
- `npm.cmd run build`: PASS, Vite finished in 1.66 seconds.
- `php artisan optimize:clear`: PASS.
- Blade view clear/cache/clear: PASS.
- Changed PHP test file lint: PASS.
- Conflict-marker scan and `git diff --check`: PASS.
- Browser: merged Command Center trip table and fleet cards verified in Dark mode; zero console warnings/errors in this dashboard check. Original Light preference restored. Evidence: `C:/Users/micha/Documents/Codex/operation-qa-evidence/dashboard-merged-dark-2026-11-11.jpg`.
- Full PHPUnit suite: PASS, 960 tests / 7,752 assertions (426.043 seconds).

The earlier comprehensive frontend report remains an audit record. This repair addresses the merge/build error and branch consolidation; it does not claim to resolve the separately documented AI readiness service warning.
