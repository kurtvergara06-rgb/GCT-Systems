# Codex validation checklist — JO reservations and IR issuance

Branch: `feature/maintenance-improvements-v2` (do not create a branch, merge to main, force push, or run migrate:fresh).

## Scope of changes
- New `job_order_part_reservations` table, model, service; on-Head-approval reservation under inventory-row locks.
- Head choice only when the saved stock availability is partial; server rechecks stock and safely defaults to Wait Until Complete if availability changes.
- Global free inventory subtracts all JO reservations; IR/PR quantities take the specific JO reservation into account.
- Reservations consumed on signed Warehouse IR issue; unused reservations released on JO completion and by cascade when a JO is deleted.
- Central stock ledger blocks unrelated stock-outs or downward adjustments that would consume reserved inventory.
- Warehouse IR verification uses physical on-hand minus other JOs' reservations, and subtracts previously issued batches from remaining request quantities.
- Warehouse allows signed partial batches with a cumulative over-issuance guard and unique batch references.
- Follow-up IR after an earlier completed IR can top up its reservation on newly delivered stock; existing active IRs cannot be duplicated.
- Unit alias normalization (pc / pcs).

## Run locally — required before approval
1. Confirm clean/expected working tree: `git status`, `git log -5 --oneline`, `git diff --check`.
2. Inspect migration and database compatibility; back up database before `php artisan migrate`. **Never run migrate:fresh.**
3. Run `php -l` on all modified PHP files and new migration/tests.
4. Run `php artisan test --filter=JobOrderPartReservationTest` and `php artisan test --filter=ItemRequestPartialIssuanceTest`.
5. Run full `php artisan test`, `npm run build`, `php artisan view:clear`, `php artisan view:cache`, `php artisan view:clear`.
6. Browser-verify Maintenance Head approval and Warehouse IR signed slip in both full reload and partial-navigation flows.

## Critical functional cases for Codex review
- Three JOs each need ten filters, only ten on hand: first approval reserves ten, later approvals reserve zero, on hand stays ten until handover.
- Approval with all/partial/no stock; confirm conditional Head selection, stale modal fallback, and signed approval workflow.
- A partial stock JO chooses Issue Available Parts; IR issues only allocated amounts; PR covers shortages.
- Wait Until Complete: no premature issue; after restock, additional reservation + IR can fulfill the remaining requirement.
- Two sequential signed partial batches: quantities 6 then 4; IR statuses Partially Issued then Issued; exactly 10 deducted and no further issuance.
- Multiple IRs for the same JO after earlier completed handover; all signed issues count toward JO part completion.
- Direct PR/other stock issue and adjustments cannot consume allocated stock.
- Concurrent approvals and concurrent Warehouse handovers cannot over-reserve, over-issue or double-deduct.
- Verify pc/pcs unit aliases and duplicate/ambiguous inventory item names.
- Confirm existing legacy PR/IR/JO data and prior permission boundaries behave unchanged.
- Verify signed slip download, evidence storage, history, counters, status filters, and notification behavior.
- Verify JO cancellation/rejection/deletion/completion and reservation release; confirm whether an explicit cancellation flow exists.
- Examine query performance (IR list verifies each item request) and potential lock-order deadlocks.

## Known constraints / reviewer attention
- Remote GitHub edits do **not** establish passing PHP tests, compiled assets, migrated schema, or browser-verified behavior.
- Newly approved JO reservations depend on the new migration. Do not deploy code before migrating database.
- UI conditional visibility initially uses a saved snapshot; concurrent change safely falls back to waiting. Evaluate whether a live-refresh affordance is needed.
- The new allocation/top-up path is an implementation to validate, not a replacement for real Warehouse acceptance testing.
- Codex should report exact regressions, file paths, test commands/output and suggested fixes; do not merge without user approval.
