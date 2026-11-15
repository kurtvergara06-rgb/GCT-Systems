# GCT-Systems Repository Rules

These instructions apply to the entire repository.

## Project Context

GCT-Systems is a fleet and operations management capstone project built with Laravel, MySQL, Blade, Vite, vanilla JavaScript, GSAP, and Laravel Reverb/Echo.

Repository: `https://github.com/kurtvergara06-rgb/GCT-Systems.git`

Local path: `C:\Users\micha\Herd\gct_system`

Main modules:

- Administration
- Operation
- Maintenance
- Purchase
- Warehouse

Primary business workflow:

`Trip -> Incident -> Maintenance Referral -> Job Order -> Part Request / Purchase Request -> Purchase -> Purchase Order -> Warehouse Delivery -> Inventory -> Part Issue -> Job Order Completion`

## Communication

- Always respond in English.
- State clearly what was actually changed and verified.
- Never claim that tests, builds, browser verification, or fixes passed unless they were actually run and verified.

## Git and Branch Safety

- Never work directly on `main`.
- Always create or use an appropriate feature or fix branch.
- Keep normal work on that branch and make as many commits as needed.
- Never merge or push to `main` unless the user explicitly says exactly: **"push to main"**.
- Similar wording, implied approval, task completion, or a request to commit does not authorize merging or pushing to `main`.
- Never force-push or rewrite `main`.
- Do not touch unrelated `package-lock.json` changes.
- Preserve unrelated working-tree changes made by the user or other agents.

## Required Main Integration Procedure

Only after the user explicitly says **"push to main"**:

1. Confirm the working branch is complete and fully validated.
2. Preserve the final working code and all intended fixes.
3. Integrate the completed branch into `main` as exactly one clean commit.
4. Prefer `git merge --squash` so `main` receives only one commit for the completed task or module.
5. Push `main` normally.
6. Never force-push `main`.

## Application Safety and Behavior

- Never run `php artisan migrate:fresh`.
- Preserve the existing business workflow and cross-module state transitions.
- Preserve permissions, Laravel Reverb/Echo realtime updates, partial navigation, GSAP animations, and modal behavior.
- A modal backdrop must never close its modal.
- Search and filter actions must update only the required table or page region; they must not visibly reload the entire page.
- Completed and historical records must remain protected from normal editing and deletion.
- Use database transactions and row locking for critical workflow or inventory mutations.
- Enforce every UI permission check on the backend as well.
- Realtime permission and data changes should update affected open pages when appropriate.

## Before Making Changes

At minimum, inspect:

- `git status`
- `git log -5 --oneline`
- Relevant files for unresolved Git conflict markers
- Relevant routes
- Relevant controllers
- Relevant Blade views
- Relevant JavaScript

Only inspect categories that are applicable to the requested change, but trace the complete affected workflow before editing.

## Validation After Meaningful Changes

Run and verify:

```powershell
php artisan test
npm run build
php artisan view:clear
php artisan view:cache
php artisan view:clear
git diff --check
```

Also run `php -l` on every changed PHP file.

If a validation command cannot be run or fails, report that accurately with the relevant error. Do not present an unverified result as passing.
