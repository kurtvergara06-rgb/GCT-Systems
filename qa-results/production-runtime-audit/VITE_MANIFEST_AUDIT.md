# Vite Manifest Audit

Audit date: 2026-10-16

## Reproduction

1. Disable `public/hot` so Laravel uses `public/build/manifest.json`.
2. Render any authenticated page using `resources/views/components/layout/app.blade.php`.
3. Before the fix, Laravel attempted to resolve `resources/css/Operation/operation-action-buttons.css` and threw `Illuminate\Foundation\ViteException` because that key was absent from the manifest.
4. A complete literal asset comparison showed two more missing shared layout entries with the same failure mode.

Expected: every local path passed to `@vite` exists, is registered as an input, and is present in the compiled manifest.  
Actual before fix: 130 Blade-requested assets, 127 Vite inputs, and 3 requested keys absent from the manifest.

## Missing entries found

- `resources/css/Operation/operation-action-buttons.css`
- `resources/css/Main-styles/record-action-buttons.css`
- `resources/css/Main-styles/gct-scrollbar.css`

All three source files existed with correct spelling/casing. The defect was input registration, not deletion or rename.

## Other asset findings

- `resources/js/Main-js/partial-navigation.js` appeared twice in `vite.config.js`; one redundant input literal was removed.
- Shared layout/page asset duplication is normalized by `array_unique`, so duplicate HTML tags are not emitted.
- The Operation action stylesheet is globally requested although its rules are Operation-scoped. This is a small optional optimization opportunity, not a correctness defect after manifest registration.
- Local HMR masked the issue because `public/hot` lets Vite serve source paths directly. Compiled production mode requires every entry to exist in the manifest.

## Post-fix evidence

Command: `npm run build`  
Result: PASS with Vite 8.1.4; 189 modules transformed.

Post-build comparison:

- Blade local CSS/JS references: 130
- Unique Vite input literals: 130
- Compiled manifest keys: 142 (includes dynamic/internal chunks)
- Missing Blade source files: 0
- Blade references missing from manifest: 0
- Vite inputs missing source files: 0
- Vite inputs missing from manifest: 0
- Duplicate Vite input literals: 0
- Exact-case mismatches against Git-tracked paths: 0

Compiled browser check with `public/hot` temporarily moved aside:

- Page: authenticated `http://gct_system.test/operation/dashboard`
- Document state: complete
- Body font: `Poppins, sans-serif`
- Sidebar computed display: `flex`
- Formerly missing compiled files loaded from `/build/assets/`:
  - `operation-action-buttons-CoL6XbQU.css`
  - `record-action-buttons-CKbt_kqk.css`
  - `gct-scrollbar-EOVzfpKS.css`
- Browser console warnings/errors: none
- Original `public/hot` restored exactly to `http://[::1]:5173` after the check.

The first sandboxed build attempt failed because native Windows Vite/Rolldown execution was blocked (`spawn EPERM`). The same build was rerun outside that restriction and passed; this was an execution-environment failure, not a source/build failure.
