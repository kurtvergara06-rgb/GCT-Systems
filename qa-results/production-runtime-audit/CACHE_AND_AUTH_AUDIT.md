# Cache and Authentication Audit

Audit date: 2026-10-16

## Confirmed failure

Observed production exception:

`fopen(/var/www/html/storage/framework/cache/data/...): Failed to open stream: No such file or directory`

Laravel's file cache is configured at `storage/framework/cache/data`. `LoginController` correctly uses Laravel's `RateLimiter` for a per-email/IP limit of five failed attempts with a 60-second decay. The authentication control was not bypassed or weakened.

## Root cause

The Docker image and both web/Reverb startup scripts created `storage/framework/cache`, but omitted the configured `data` root. Although Laravel can create hashed child directories, the production runtime needs a stable, PHP-FPM-writable cache root on every fresh container. The missing explicit root was consistent with the reported first-write failure.

The following now create `storage/framework/cache/data` before Artisan or PHP-FPM/Reverb cache access:

- `Dockerfile`
- `docker/start.sh`
- `docker/start-reverb.sh`
- `.github/workflows/ci.yml`
- `.github/workflows/production-deployment.yml`

The existing recursive `chown www-data:www-data` and `chmod 775` cover the new directory.

## Driver analysis

- Local `.env`: `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`.
- `.env.example`: same explicit drivers.
- Framework fallback if `CACHE_STORE` is absent: database.
- Framework fallback if `SESSION_DRIVER` is absent: database.
- Actual Render environment values were not accessible and were not changed.

Impact of a missing/unwritable file-cache root includes login throttling and any application feature using the default cache store. File sessions use `storage/framework/sessions`, a separate directory already prepared by the image/start scripts. Sync queues do not use the cache directory, while cache-backed locks and throttles do.

## Runtime verification

Production-like file-cache smoke:

- File cache put/get/forget: PASS
- RateLimiter hit count: PASS
- Five-attempt threshold: PASS
- RateLimiter cleanup: PASS

Regression test: `php artisan test tests/Feature/ProductionRuntimeStabilityTest.php`

- Invalid login using file cache: PASS
- First five invalid submissions recorded errors without exception: PASS
- Sixth invalid submission returned the throttling error: PASS
- Runtime directory declarations: PASS
- Result: 3 tests, 278 assertions, PASS

Authentication coverage in the complete suite:

- Successful active-account login and redirect: PASS
- Logout/session invalidation: PASS
- Inactive and pending account rejection: PASS
- Onboarding and required password change: PASS
- Final full suite: 613 tests, 4,943 assertions, PASS

## Remaining operational considerations

- File cache/session state is ephemeral across container replacement.
- File-based rate limiting is per instance, not shared across horizontally scaled instances.
- A shared database or Redis-compatible cache/session backend would improve multi-instance consistency, but changing production drivers requires environment and capacity planning and was intentionally not performed.
