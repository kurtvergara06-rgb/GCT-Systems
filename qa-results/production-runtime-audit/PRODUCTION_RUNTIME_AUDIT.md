# GCT-Systems Production Runtime Audit

Audit date: 2026-10-16 (Asia/Manila)  
Branch: `fix/production-runtime-stability`  
Base: verified `origin/main` at `57ae2054`  
Production changes: none; this audit and hotfix are local and uncommitted.

## Outcome

Both reported HTTP 500 causes were reproduced by static/runtime inspection and fixed locally:

1. The container created `storage/framework/cache`, but not Laravel's configured file-cache root at `storage/framework/cache/data`. Fresh containers could therefore reach login throttling without a prepared PHP-FPM-writable cache root.
2. The shared authenticated layout requested three CSS files that existed in source but were absent from Vite's input list and compiled manifest. Since the shared layout is used by all departments, any authenticated department page could throw `ViteException` before rendering.

No further production-blocking runtime error was found in the tested scope. The final complete Laravel suite passed (613 tests, 4,943 assertions), the focused hotfix regression run passed (3 tests, 278 assertions), the production Vite build passed, and an authenticated dashboard rendered with compiled assets and no browser console warning/error.

## Changes

- Added the three shared layout CSS sources to `vite.config.js`.
- Removed the duplicate `partial-navigation.js` input literal.
- Changed Docker image/startup and both CI preparation paths to create `storage/framework/cache/data` explicitly before Laravel cache use.
- Added `ProductionRuntimeStabilityTest` to prevent recurrence.
- No controller, route, business workflow, permission, modal, Reverb/Echo, GSAP, or database migration was changed.

## Audit coverage

- Git safety and verified remote base.
- Login controller and file-backed Laravel rate limiting.
- Cache, session, queue, application, database, broadcasting, Reverb, and filesystem configuration.
- Shared layout and every literal local CSS/JS reference under `resources/views`.
- Vite inputs, source-file existence, exact path casing, and compiled manifest keys.
- All route files and the generated route table: 208 routes total, 89 GET-capable routes.
- Literal/dynamic controller-to-view paths through route rendering tests and Blade compilation.
- Administration, Operation, Maintenance, Warehouse, Purchase, account, onboarding, authentication, shared components, partial navigation, realtime lifecycle, and permissions through the existing test suite.
- `Dockerfile`, both startup scripts, Nginx, Supervisor, Docker ignore rules, Composer PHP requirements, and GitHub Actions workflows.

No unresolved Git conflict markers were found in the inspected repository source.

## Docker and Render readiness

- Frontend assets are built in the Node stage and copied from `/app/public/build` into the PHP runtime image.
- PHP 8.4 satisfies Composer's PHP `^8.3` requirement. The image installs `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`, `gd`, `opcache`, and `pcntl`, matching the observed application/runtime needs.
- Nginx serves `/var/www/html/public` on port 10000 and forwards PHP requests to PHP-FPM on port 9000, consistent with Supervisor.
- `storage`, `storage/logs`, session/view/cache directories, and `bootstrap/cache` are prepared and assigned to `www-data` with group-writable permissions.
- The startup migration command is `php artisan migrate --force` (pending migrations only). No `migrate:fresh`, database wipe, or destructive reset exists in deployment startup.
- `APP_DEBUG` defaults to `false` in `config/app.php`. The actual Render value was not accessible and remains unverified.
- The Docker build requires all four `VITE_REVERB_*` build arguments. The runtime also requires valid `APP_KEY`, database, and Reverb server/application values. Actual Render values were not read or changed.

## Remaining risks and unverified items

- Docker Desktop's Linux engine was not running, so the Docker image itself was not built or started locally. Static Docker validation and the host production asset build passed.
- Render environment variables, writable filesystem behavior, current service topology, and deployed image were not accessed. Production remains unchanged.
- File cache and file sessions are container-local. They are writable after this fix, but they reset on replacement/redeploy and do not coordinate rate limits across multiple web instances. A shared database/Redis cache/session design should be considered before horizontal scaling; no driver was changed in this hotfix.
- Startup executes pending production migrations automatically. Each future migration must be reviewed for locking/data impact before deployment.
- `docker/start.sh` removes `public/storage` before recreating the storage link. In the current immutable image design this targets the public link path, not `storage/app/public`, but it should not be pointed at a persistent real directory.
- The operation-specific action-button stylesheet remains in the global layout, so non-Operation pages download an extra ~2.7 kB uncompressed CSS entry. It is correctly scoped and no longer causes runtime failure; route-scoping it is an optional later optimization, not part of the emergency fix.
- Many page views repeat main/sidebar asset declarations already supplied by the shared layout. `array_unique` prevents duplicate tags at runtime. Cleanup would be non-functional refactoring and was intentionally not included.

## Final validation log

- `php artisan test`: PASS — 613 tests, 4,943 assertions, 168.688 seconds.
- `npm run build`: PASS — Vite 8.1.4, 189 modules transformed.
- `php artisan view:clear`: PASS.
- `php artisan view:cache`: PASS.
- Final `php artisan view:clear`: PASS.
- `php -l tests/Feature/ProductionRuntimeStabilityTest.php`: PASS.
- `git diff --check`: PASS.
- Docker build: NOT RUN — Docker Desktop Linux engine unavailable.

## Recommended deployment procedure (not performed)

1. Review this uncommitted diff and QA evidence.
2. Re-run the mandatory validation in an environment with Docker Desktop running, including a full image build and container startup with production-like non-secret values and an isolated database.
3. Confirm Render has `APP_ENV=production`, `APP_DEBUG=false`, a valid `APP_KEY`, database/TLS values, Reverb runtime values, and all four Vite Reverb build arguments.
4. Review every pending migration against a current backup and maintenance-window plan.
5. After explicit release approval, create the requested clean release commit/merge according to repository rules, deploy one instance first, and verify `/`, invalid login throttling, a successful login, all five dashboards, `/up`, logs, and Reverb connectivity.
6. Roll out further only after the canary instance shows no cache, session, Vite, PHP-FPM, Nginx, or migration errors.

This report does not authorize a commit, push, merge, or deployment.
