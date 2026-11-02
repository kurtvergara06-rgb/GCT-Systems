# Account-only Aiven MySQL cutover (prepared; not executed)

## Scope and dependency audit

The source is `defaultdb`; the empty target is `gct_production_clean`. The transfer allowlist is exactly:

| Table | Rows to copy | Reason |
| --- | --- | --- |
| `users` | Every row, regardless of status | Active, inactive, pending, and rejected accounts are all account records. Copy every column, including IDs, password and remember-token hashes, Google IDs, email verification timestamps, department, role, status, onboarding and password-change flags, avatar paths, and original timestamps. |
| `role_permissions` | Every row | `User::permissionRoleKey()` and `hasSystemPermission()` read permissions by `role_key`. Copy IDs, JSON permissions, labels, departments, role types, timestamps, and `updated_by` unchanged. |

The exact production counts and individual record inventory are **unknown until a read-only dry run against Aiven**. The command prints counts and writes only counts plus SHA-256 fingerprints to its report; it never logs account fields or secrets. It rejects an active non-admin user without a matching `role_permissions` key, and a permission `updated_by` that does not identify a copied user.

The current migrations define these exact account columns, all of which are copied: `users.id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role`, `department`, `status`, `last_login_at`, `must_change_password`, `onboarding_completed`, `onboarding_completed_at`, `avatar_path`, and `google_id`; `role_permissions.id`, `role_key`, `label`, `department`, `role_type`, `permissions`, `updated_by`, `created_at`, and `updated_at`. The transfer selects every column from both tables and refuses source/target column-shape drift. Production may have extra columns; the read-only schema check must resolve that before transfer.

`users` has no foreign key to an operational table. `role_permissions.updated_by` is a logical user reference without a database foreign key. Many operational tables point **to** users; empty operational tables require no dependent rows. The `email_verification_otps` and `account_email_changes` tables point to users, but contain expiring challenges rather than verified status. Their schema is migrated, their rows are excluded, and affected users must request fresh links/codes after cutover. `password_reset_tokens`, `sessions`, cache, jobs, notifications, activity logs, applicants represented by pending `users` rows, drivers, mechanics, and every other operational table have schema but no copied rows. Existing browser sessions end at cutover. No PR, PO, JO, GPS, inventory, fleet, or analytics records are copied.

Account avatar paths are copied as user columns. If the target deployment changes its public-storage location, separately copy **only** files referenced by `users.avatar_path` and verify each file before cutover; never copy operational uploads. Keep the existing `APP_KEY` and authentication provider settings for the application deployment. No production environment setting is changed by this preparation.

## Safety gates in the implementation

- `gct_account_source`, `gct_account_target`, and `gct_account_restore` are dedicated connections with separate environment variable names. They never inherit `DB_*`. The command requires source/target database names `defaultdb` and `gct_production_clean`, and refuses a shared source/target endpoint.
- `gct:account-cutover` defaults to read-only dry run. It checks account schemas, source role references, and that every non-account target table is empty. It refuses to overwrite target accounts.
- `--check-backup` compares a scratch database restored from the source backup: all table names/counts and exact `users`/`role_permissions` row fingerprints. It produces a manifest with the backup file hash. This verifies the restore result, provided the operator restored **that exact backup file** to the scratch database. Keep the backup and manifest in restricted storage.
- `--apply` requires a backup manifest and a dry-run report from the last 24 hours, rechecks the backup file hash, source fingerprint, target emptiness, and schema, then inserts only the two allowlisted tables in a single target transaction. It verifies the exact copied rows before commit. A failure rolls the transaction back; a repeated apply refuses to overwrite accounts.
- `--verify` is read-only and repeatable after transfer. It checks exact account fingerprints and that every other target table remains empty.
- The dedicated target connection itself suppresses migration-time inserts into `role_permissions`, `mechanic_specialization_options`, and `maintenance_job_catalog`; no temporary environment flag is needed. All three seeders refuse production and the dedicated target connection. Never run `db:seed`, `migrate --seed`, `migrate:fresh`, demo seeders, or sample seeders during cutover.

## Future operator procedure (no step below has been run against Aiven)

1. Deploy a reviewed commit of this branch to a trusted cutover workstation, without changing the running application's database configuration. Prepare separate, least-privilege connection secrets in the workstation's protected environment for source (read only), target (schema/write), and a disposable restore database (read only after restore). Never put passwords in commands, reports, Git, or terminal transcripts. Keep the application on `defaultdb` throughout preparation.
2. Before any target write, establish a maintenance window, stop application writes and background jobs, and make a consistent full backup of `defaultdb`. Save it to restricted storage. Restore **that file** to a disposable database. Verify the restore tool completed successfully. The source and restored database must be stable while comparing them.
3. With the three dedicated connections set, run the read-only restore check. It can run while the target has no tables:

   ```powershell
   php artisan gct:account-cutover --check-backup --backup-file=<protected-backup-path> --backup-manifest=<new-protected-manifest-path>
   ```

   Review the manifest file hash and the scratch-restore table counts. If any count or account fingerprint differs, stop. Preserve the original backup. A second independent backup or Aiven snapshot is recommended.
4. Confirm `gct_production_clean` has no existing tables or records. Preview the migrations against **that explicit target** and review the SQL, then run the schema migration without `--seed`. Do not point the running web application at the target yet.

   ```powershell
   php artisan migrate --database=gct_account_target --pretend
   php artisan migrate --database=gct_account_target --force --no-interaction
   ```

   The schema migration is a **future write** to the empty target and requires explicit cutover approval. If the source account columns differ from this branch's migrated target schema, the transfer will refuse; resolve the schema mismatch by review, not by dropping or overwriting source data.
5. Generate a fresh read-only transfer report; inspect user and role row counts and confirm all operational tables are empty:

   ```powershell
   php artisan gct:account-cutover --report=<new-protected-dry-run-report-path>
   ```

6. **Stop here until an authorized operator approves the actual transfer.** Later, with writes still frozen and the verified backup available, execute:

   ```powershell
   php artisan gct:account-cutover --apply --report=<dry-run-report-path> --backup-manifest=<backup-manifest-path>
   php artisan gct:account-cutover --verify
   ```

7. Before changing the app connection, perform a read-only target audit: all source users and role permissions equal target by count and fingerprint; all other target tables contain zero rows; ID ranges and unique emails/Google IDs are intact. Check the intended branch release, configured `APP_KEY`, mail/OAuth configuration, storage, and account login in a private test environment. Only then change the running application's connection through a separately approved deployment. Resume workers after the application points at the target and smoke tests pass.

## Rollback and recovery

The source `defaultdb` is never written by this transfer. If target migration or account copy fails, keep the app on `defaultdb`; inspect the failed target without truncating it, and provision a **new** empty target for another attempt. If a later application cutover fails, stop target writes, point the app back to `defaultdb` through the approved deployment process, and invalidate sessions. Record any writes made to the target after cutover before deciding how to reconcile them; switching back without that audit could lose new activity. Retain the full backup, manifest, dry-run report, and scratch restore until the cutover is accepted.

## Known limitations

No Aiven connection, production backup, production dry run, target migration, account transfer, or cutover has been executed during preparation. Therefore production row counts, schema equality, backup restore status, and live login behavior are not verified yet. The restore check compares exact account rows and counts of other tables; it does not compare every operational field in the full backup. The old source remains the rollback path, while the full backup is an additional recovery control.
