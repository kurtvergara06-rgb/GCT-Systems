# Hotfix Changed Files

Audit date: 2026-10-16

All changes are local, uncommitted, and on `fix/production-runtime-stability`.

## Runtime/source changes

- `.github/workflows/ci.yml` — create `storage/framework/cache/data` during CI preparation.
- `.github/workflows/production-deployment.yml` — create the same exact cache root in the production quality gate.
- `Dockerfile` — create the file-cache data root in the runtime image before applying ownership/permissions.
- `docker/start.sh` — recreate the file-cache data root on every web container startup.
- `docker/start-reverb.sh` — recreate the file-cache data root on every Reverb container startup.
- `vite.config.js` — register three shared CSS entries and remove one duplicate partial-navigation input.
- `tests/Feature/ProductionRuntimeStabilityTest.php` — regression coverage for Blade/Vite registration, runtime cache directory preparation, and file-backed invalid-login throttling.

## QA evidence added

- `qa-results/production-runtime-audit/PRODUCTION_RUNTIME_AUDIT.md`
- `qa-results/production-runtime-audit/VITE_MANIFEST_AUDIT.md`
- `qa-results/production-runtime-audit/CACHE_AND_AUTH_AUDIT.md`
- `qa-results/production-runtime-audit/ROUTE_SMOKE_TEST_RESULTS.md`
- `qa-results/production-runtime-audit/HOTFIX_CHANGED_FILES.md`

## Preserved local state

- Existing `qa-results/operations-browser-v2/` files were not edited.
- Existing `public/hot` was temporarily moved only for compiled-mode verification and restored with its original value `http://[::1]:5173`.
- `package-lock.json` was not modified.
- No migration, seed, destructive database command, commit, push, merge, deploy, or Render configuration change was performed.
- Generated `public/build` output is ignored and is not an intended source diff.
