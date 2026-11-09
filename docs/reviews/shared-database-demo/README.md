# Proposed change: one address, one database

Prepared 2026-11-04. **Review only — not applied.**

The user approved preparing this patch and requested another review before application. The application, running services, environment files and databases have not been changed by this preparation. The patch has not been executed or tested. Previous passing tests describe the separate-database implementation, not this proposal.

## What you would see

At the existing `/login` page:

- Sign in with an existing personal or demo account.
- Create an ordinary family account.
- Try the demo — free for 24 hours.

The guest form is `/demo` on the same host and port. Both account types use the existing `/auth`, `/sanctum` and `/api` routes, one Laravel service and its configured PostgreSQL database. There is no redirect to another host, no database switching and no second frontend build.

## Database identification and access

An entry in `demo_accounts` identifies a demo user. A user's family and all of its child records inherit that account type through their relationships. The API already returns `is_demo` from that relationship. No duplicate `is_demo` column is added to every table.

Family ownership continues to enforce access, regardless of the demo flag. The proposed tests use real foreign IDs to check detail, lists, aggregates, edits, deletion, medication intake/corrections, notifications and export in all four directions: demo → personal, personal → demo, demo → demo and personal → personal.

No migrations or data copies are needed for this change on the existing migrated local database. Existing personal records keep their current family ownership. Records in the old separate demo database are left untouched; they are not imported automatically. The dormant `demo_installations` table is retained to avoid destructive schema changes, but is no longer consulted.

## Exact file-by-file patch

See [proposed.patch](proposed.patch) for the complete unified diff. `source-hashes.json` records the original file hashes so application can stop if source has changed since review.

| File under the project root | Proposed change |
| --- | --- |
| `backend/app/Support/DemoEnvironment.php` | Check explicit opt-in and required tables; allow ordinary users in the same database. |
| `backend/app/Http/Middleware/DemoAccess.php` | Apply expiry, disabled access, recovery and verification restrictions to demo accounts only; preserve ordinary account flows. |
| `backend/app/Providers/FortifyServiceProvider.php` | Permit ordinary login while demos are enabled; still reject expired or disabled demos. |
| `backend/app/Providers/AppServiceProvider.php` | Suppress email only for demo recipients. |
| `backend/app/Actions/Appointments/PlanReminders.php` | Disable reminder planning only for demo owners. |
| `backend/app/Actions/Appointments/DeliverReminder.php` | Disable reminder delivery only for demo recipients. |
| `backend/app/Actions/Accounts/ResetUserPassword.php` | Explicitly reject demo password resets in the reset action as well as middleware. |
| `backend/app/Http/Controllers/DemoController.php` | Return mixed-account metadata; use a PostgreSQL transaction advisory lock to serialize signup capacity without an installation marker row. |
| `backend/config/demo.php` | Remove the separate-database-name setting; retain opt-in and all quotas. |
| `backend/.env.example` | Describe opt-in for the shared application database. |
| `backend/app/Console/Commands/InitializeDemo.php` | Turn the old initialization command into a readiness check with no data writes. |
| `backend/app/Console/Commands/DemoReport.php` | Update environment wording; retain server-only visitor reporting. |
| `backend/app/Console/Commands/RevokeDemo.php` | Update environment wording; retain server-only revocation. |
| `frontend/src/app/features/auth/auth-page.ts` | Show ordinary signup, recovery and demo entry together; remove redirects caused by demo deployment mode. |
| `backend/tests/Feature/DemoAccessTest.php` | Replace empty-database assumptions with shared-database checks; add both mixed-account isolation directions and ordinary login/signup/recovery regressions. |
| `frontend/src/app/features/auth/auth-page.spec.ts` | Check that all entry links coexist on one login page. |

## Application steps to approve

1. Check original hashes, then apply the reviewed patch. Any material additional fix will be shown before changing its scope.
2. Run backend tests only against `toddler_daily_test`; run frontend tests/build. Verify ordinary reminder delivery remains enabled and demo delivery remains blocked. Verify actual session/CSRF exchange using synthetic records without testing against personal family data.
3. After the checks pass, enable `DEMO_MODE=true` in the existing local application's environment, preserving its encryption key and database credentials. Use the existing localhost:4300 entry point and one backend. Do not merge, reset or delete either database.
4. Update the approved plan, architecture and handoff to supersede the separate-deployment decision and record measured checks. The old local demo runtime becomes unnecessary; any retirement must target only its identified processes and leave its data intact.

The 24-hour deadline, retained visitor history, rate limits, quotas, CSRF, browser sessions, immutable medication revisions and ordinary email verification remain required. Disabling the demo feature must not disable personal-account access.

## Vercel scope

This feature change prepares one public application address with one Laravel backend/database. The existing Angular same-origin proxy contract already serves both account types. Vercel hosts the frontend; Laravel and PostgreSQL still require the approved backend/database hosting. The unfinished hosted proxy/container configuration and release verification remain separate deployment work. This patch does not create a public deployment, purchase services, change hosting accounts or connect previews to personal data.

## Approval

Application was explicitly approved and completed. The patch and original hashes remain a historical record; do not reapply them to the updated source.
