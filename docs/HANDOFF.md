# Agent handoff — WeeGrow

Updated: 2026-11-04, Asia/Manila.

## Start here

Read [PROJECT_PLAN.md](PROJECT_PLAN.md) for approved product scope and architecture. This file records implementation progress; inspect the repository before assuming a feature is complete.

Workspace: C:\workplace\Codex\toddler daily.
Frontend: frontend/. Backend: backend/. No hosted deployment has been verified.

The user authorized implementation, including isolated local PostgreSQL databases and migrations. Do not ask for those permissions again as a planning decision. Platform tool permission prompts still apply when required.

## Current implementation

- Angular 22 and Laravel 13 scaffolded with dependencies and lockfiles.
- Bootstrap 5, Sanctum and Fortify installed.
- PostgreSQL migrations for private app schema and family records.
- Direct registration without invitation codes, verification/recovery/session flows (user changed signup scope on 2026-11-01).
- One-owner family and child policies/scoped queries.
- Child profiles; timeline/milestones; growth; medication instructions; vaccination records; appointments.
- Immutable manual medication intake history, idempotency and appended corrections with encrypted instruction snapshots.
- Version-checked mutable records and encrypted audit details.
- Fixed family/child dashboard, activity listings and notification pages.
- Durable appointment deliveries, row claims/leases, retry handling, schedule invalidation.
- Protected internal maintenance endpoint and authenticated notification refresh.
- Password-confirmed JSON export and deletion request/purge actions.
- Encrypted deletion ledger written before purge (local storage in development; configured private Supabase Storage in production).
- Responsive interface and local development proxy configuration.

These are implemented source paths, not a declaration that the whole MVP is release-ready.

## Verified checks

### Shared address and database � applied 2026-11-04

- User explicitly approved applying `docs/reviews/shared-database-demo/proposed.patch`. Checked all 16 source hashes, then applied the reviewed diff with exact context validation, accounting for Windows line endings. The separate-database decision below is historical and superseded by this section.
- One frontend at **http://localhost:4300/login** offers ordinary sign-in/signup/recovery and **http://localhost:4300/demo**. Both account types use the same Laravel service on port 8000 and its existing application database. `demo_accounts` is the account-type flag; family-scoped authorization remains mandatory. No new columns, migration, database merge, key regeneration or record import was needed.
- Enabled `DEMO_MODE=true` in the existing local backend/.env after checks passed, preserving the existing application key and credentials. The normal Artisan server reloaded its environment. Public checks at localhost:4300 returned 200 for health, options, login and demo. Options report available=true and demo_mode=false (mixed-account mode).
- Removed deployment-wide bans on normal login, signup, recovery, email and reminders. Demo-specific restrictions, expiry, quotas, history and server-only operator commands remain. Demo init is now a read-only readiness command; the old installation table remains dormant. Use `tracker:demo-report --events` against the normal environment to review new demo visitors, without --env=demo-review.
- Backend: **39 tests passed, 451 assertions**, exclusively on toddler_daily_test. Covers both mixed-account isolation directions using actual foreign IDs, demo/demo and normal/normal, login/signup/recovery, verification, feature disable, selective notification/email/reminder delivery, expiry, revocation, quotas and history. Corrected a new test's cached authentication guard between simulated account switches; no production verification bypass was introduced. Added focused selective-delivery/feature-disable tests within the approved verification scope.
- Frontend: **32 tests passed**. Production build passed; existing initial-size warning remains 533.46 kB / 500 kB, budget unchanged. No dependencies added.
- Real HTTP session/CSRF/proxy checks passed using fictional normal and demo accounts together in toddler_daily_demo, never testing against personal records. Verified CSRF 419, HttpOnly host-only cookies, isolated reads/edits/exports, no-store headers, demo email restriction, relogin expiry and normal signup requiring verification. Corrected the fixture's attempt to register while already signed in. Reset only transient cache in that synthetic environment for the rerun; retained account/history rows.
- The old localhost/127.0.0.1:4402 runtime/database is no longer needed for user access but remains intact. It was used solely for synthetic HTTP verification. Do not rerun the old patch generator after application or import its database automatically. Existing family data and the old demo history were not deleted.
- Visual browser acceptance and hosted Vercel/Render/PostgreSQL deployment remain separate readiness work. No public URL or deployment was created. Vercel still hosts Angular with same-origin proxy routes; Laravel/database hosting and release gates remain required. Preview/test databases must remain separate from the application database containing personal data.

### Isolated portfolio demo — 2026-11-03

- User approved implementation of email/password guest access, fixed 24-hour expiry, retained visitor history and strict isolation from personal/other users' records. See architecture.md for the exact deployment boundary and limits.
- Added `demo_accounts`, `demo_access_events` and the explicit empty-database initialization marker. Public demo is off by default. The environment guard checks the actual database name, marker and absence of ordinary accounts. A personal/pilot database must never be initialized as a demo.
- Demo signup creates one owner, one private family and fictional sample child/memory. Email remains unverified; only active demo accounts in a valid isolated environment may bypass email verification. Laravel's family-scoped queries/policies remain mandatory for all reads, writes, aggregates, intake corrections, notifications and exports.
- Expiry/revocation is checked server-side on requests and login. Relogin does not extend expiry. Existing sessions are invalidated when denied; the frontend clears the demo view at the expiry time. Normal account verification/recovery remains intact outside demo mode.
- Disabled demo verification/recovery emails, email preferences and all appointment reminder planning/delivery. Added signup/write/global capacity limits, transactional child/record quotas, bounded event history and smaller demo note fields. HTTP 429 responses preserve Retry-After headers.
- Added `/demo` using the existing Organic account form, privacy acknowledgement, demo expiry banner and appropriate settings. Login shows the demo entry only on a demo deployment. Password managers/paste remain supported. Existing visual direction and typography were retained.
- Visitor review is **operator terminal only**, not a browser admin page: from backend, run `php artisan tracker:demo-report --env=demo-review --events`. It shows unverified supplied emails, expiry, last login and at most 200 retained events per account. `php artisan tracker:demo-revoke USER_ID --env=demo-review` immediately disables access without deleting history. Neither is exposed over HTTP.
- Final backend suite passed all 34 tests with 353 assertions, including the expanded matrix using actual foreign record IDs for every record type and medication corrections; frontend suite passed all 31 tests. Production build passed, retaining the existing initial-size warning (533.46 kB / 500 kB; budget unchanged). New PHP files were formatted with Pint.
- Real HTTP verification passed through the frontend proxy and Laravel/PostgreSQL, including a final rerun at 127.0.0.1:4402: CSRF-less write rejected with 419; host-only HttpOnly session cookie; two independent demo accounts/families; cross-account read/write denial; isolated export; private/no-store responses; email preference rejection; logout/login with unchanged expiry. All fixtures are fictional and stored only in the local demo database. Backend integration tests use only `toddler_daily_test`.
- Additive demo-table migration applied explicitly to the documented local `toddler_daily` database so the normal app remains compatible. No personal records or existing encryption keys changed; demo mode remains off there. No tests ran against that database.
- Local demo database: `toddler_daily_demo` on the existing 127.0.0.1:54329 cluster. Ignored `backend/.env.demo-review` has a new demo-only encryption key, unique session cookie and disabled mail. Never print, commit or regenerate that key. Local runtime reuses this machine's DB role; hosted environments require separate least-privilege credentials.
- Local demo entry: **http://127.0.0.1:4402/demo**, API on 127.0.0.1:8002. The distinct hostname prevents its XSRF cookie from interfering with the personal app on localhost:4300. Ignored helper `.local/demo-review/start.ps1` starts only these two hidden services and refuses occupied ports. `.local/demo-review/serve.mjs` serves the production build and proxies only `/api`, `/auth`, `/sanctum`. This is a local verification helper, not a hosted deployment configuration.
- Per backend/AGENTS.md, installed Laravel Boost 2.10.4 as a development dependency (four additions, no existing package upgrades), ran guideline setup and read the regenerated instructions. Composer reported no advisories. No application key-generation/setup script was run on the existing app.
- **Unverified:** browser visual/mobile review because the computer-use inventory reports local browser use disabled in Work settings. No public URL, hosted HTTPS/cookie/CSRF/proxy-IP verification, hosting quota/eligibility review or external-family onboarding is implied. Provide an actual privacy/contact channel and verify separate hosted database/credentials before publishing the portfolio link.

### Four-second data-entry toasts — 2026-11-02

- Latest correction: the in-flow notification row caused page movement. Toast is now fixed top-center at z-index 1090, with no conditional padding or layout space. Empty container ignores pointer events; visible toast and Dismiss remain interactive. Bootstrap success at 90% opacity and four-second timing retained. This supersedes earlier top-right and in-flow positioning.
- Verified production build and isolated browser layout fixture using built CSS: main Y stayed 0 before/after toast appearance; computed position fixed and z-index 1090. Temporary fixture removed; screenshot retained at .local/ui-review/toast-overlay.png. Existing bundle-size warning remains.

- Follow-up: moved global toast to top-right (24px top inset with safe-area support); background now 90% opacity while text and Dismiss remain fully opaque. Four-second timing unchanged.

- Added root-level polite live-region toast with manual Dismiss and a four-second timer. New success replaces the previous message and restarts its timer. Root placement survives navigation after account deletion requests.
- ApiClient confirms successful POST/PATCH/DELETE responses across children, timeline, growth, medication, intake/corrections, vaccinations, appointments, settings and notification read actions. Archive/restore and queued family deletion use accurate wording. Password confirmation and background reminder refresh are intentionally silent. Errors remain in existing persistent notices; duplicate settings-save notices removed.
- Production build passed with existing size warning (520.71 kB initial). Added isolated HTTP/timer tests for dismissal, replacement, successful save/delete, rejected updates and silent background refresh. No real family writes or backend changes in this milestone.

### Child profile fields — 2026-11-02

- Added first/middle/last/suffix fields and Male/Female radios to create/edit. Middle and suffix optional; read-only age uses birth date and family timezone. Existing names preserved without guessing name parts. Combined display-name limit remains 100 characters.
- Additive nullable-column migration applied locally. API composes display_name; legacy callers remain compatible. Existing 21 backend tests passed; new persistence/update/validation test passed with 16 assertions on dedicated test database. Production build passed with existing size warning (518.61 kB). Isolated browser confirmed radio selection and calculated age. No real child records created in testing.

### Shared Organic interface system — 2026-11-02

- Standardized all modules through global sage/sand/moss tokens, serif headings, rounded panels/controls, grain, consistent feedback, record tabs and responsive forms. Auth now inherits global palette tokens. Added docs/ui-system.md as the implementation reference; approved-plan UI section updated.
- Dashboard has an honest first-child action using existing original toddler artwork. Main navigation has labeled original SVG icons, aria-current, a matching mobile brand, inline disclosure and Escape dismissal. Menu DOM order matches visual order. No backend, authentication, or database changes.
- Production build passed (518.41 kB initial; existing 500 kB warning remains, no budgets raised). All 27 frontend tests passed outside the restricted temporary-file context. No backend tests needed for presentation-only changes.
- Isolated production preview at 127.0.0.1:4401 serves explicitly labeled sample data and rejects all writes; it never contacts Laravel or a database. Browser inspected 13 routes at 1440, 768 and 390 pixels with no horizontal overflow, including all child record modules, intake history, settings and login. Viewed phone appointment editor, desktop and phone dashboard. Real signed-in dashboard only viewed; no family records created or changed.
- Supplemental taste sheet editorial-greeting seed 1816330559; explicit frontend-design Organic palette/type takes priority over neutral app defaults. Browser review used the required CUA browser surface instead of the skill's separate browser driver. Source lint flagged an existing example.com validation example (authored input guidance, not fake account data); box-shadow:none is intentional removal of Bootstrap glow. Active navigation now uses an inset outline, not a decorative shadow.
- Final live reload returned to /login after the overnight session; no logout or password changes were performed. User may need to sign in again. Final screenshot uses the explicitly labeled isolated sample preview. Keyboard Escape closes mobile navigation and returns focus to its toggle.
- Remaining limits: this UI pass does not establish full save/delete/export browser acceptance, outbound email, hosted readiness, or production deployment. Existing release gates remain.


### Guest-state handling and verified dashboard access — 2026-11-01

- Found the concrete defect in the earlier recovery-session fix: Fortify's POST logout is itself authenticated. An already-signed-out user receives 401, which previously stopped reset before any reset-password request. AuthStore now treats only logout's 401 as the already-signed-out state; other logout errors still abort. Login/register/recovery all establish guest state and fetch fresh CSRF before submission. This also fixes a retained authenticated session redirecting POST login to HTML and causing the generic JSON-client error.
- Live browser showed the account was already authenticated but unverified. Following a fresh genuine verification email exposed a second blocker: Angular proxy changeOrigin:true rewrote Host from localhost:4300 to 127.0.0.1:8000, so Laravel correctly rejected the absolute signed URL. Local proxy now uses changeOrigin:false. Restarted only the verified Angular process on port 4300; port 4200 untouched. No signed middleware disabled and no key/session credentials regenerated.
- Followed the genuine verification link again through the corrected proxy: /?verified=1 loaded the real Family overview dashboard with authenticated family data. Account email verification completed through Laravel's normal signed-link flow; no manual verified timestamp or real password changes by the agent. The live account is signed in and can access its dashboard. This proves real session/verification/dashboard reads; full synthetic browser record workflows and hosted readiness remain outstanding.
- All 21 backend tests passed, 129 assertions, dedicated toddler_daily_test only. Tests cover guest logout returning 401, successful reset afterward, old/new password behavior, signed-host matching, tampered-link rejection, and valid verification. All 27 frontend tests passed, covering login/register/recovery after both logout 204 and 401 and a reset-to-login-to-session-load flow. Production build passed; existing initial-size warning approximately 515.09 kB / 500 kB.
- Earlier handoff entries about an agent-opened reset form and awaiting user reset are historical. Do not request another password reset merely because this account was previously blocked by session handling or verification. No hosted deployment or external onboarding performed.

### Post-reset login investigation — 2026-11-01

- User reported a reset success message followed by rejected new credentials. Read-only account metadata showed unchanged creation/update timestamps, no reset-completion remember-token marker, and a pending reset token. Local service request logs contained forgot-password/logout/login calls but no reset-password submission. No real-account completed reset was recorded; why the earlier UI displayed success was not conclusively reproduced.
- Found a separate reproducible login defect: Laravel reports credential mismatch against email; correcting only the password left the email's server error on the form, so form.invalid prevented another request. Any edit now clears stale server errors/messages while retaining local validators. Regression tests cover password-only correction and required-field preservation.
- Added an explicit password_reset:true JSON flag to Fortify's successful reset response; AuthStore refuses reset success without it. Confirmed resets clear password fields and navigate to a fresh /login?password-reset=1 screen, without retaining the reset token in that URL. Login copy reminds the user to enter the new password rather than an older browser-saved value. Password hashing, token checks, CSRF and guest-only recovery safeguards remain.
- All 20 backend tests passed (122 assertions), dedicated toddler_daily_test only: reset token validation/consumption, old-password rejection, new-password acceptance and authenticated identity. All 21 frontend tests passed, including a real-router/AuthStore/HTTP-mock reset-to-login integration test and rejection of unconfirmed successful HTTP responses. Production build passed; existing initial-bundle warning approximately 515.01 kB / 500 kB.
- Opened the real account's reset-link form for the user, with no new password entered or submitted by the agent. Browser credential policy requires the user to enter, confirm and submit it. Await the user-reported result before claiming their login is fixed; actual account verification remains separate. Screenshot: .local/login-review/password-change-ready.png.

### Password recovery session fix — 2026-11-01

- Reproduced a retained authenticated/unverified signup session while /forgot-password showed a generic failure. Fortify's recovery endpoints require guests; an authenticated request redirects to / rather than sending mail, and Angular's JSON parser surfaces the resulting HTML as a generic failure.
- AuthStore explicitly logs out before forgot-password and reset-password submissions, then fetches fresh CSRF before posting. No automatic retries; logout failure stops the recovery request. Forgot-password copy explains the sign-out and links to Mailpit only in development on loopback hosts. Backend guest/token safeguards remain intact.
- All 20 backend tests passed, 117 assertions, on dedicated toddler_daily_test only. New tests cover the authenticated redirect, recovery after logout, invalid token/confirmation, single-use token consumption, and login after a synthetic password reset. All 16 frontend tests passed, including logout/CSRF ordering and abort-on-logout-failure. Production build passed with the existing initial-size warning (approximately 514.67 kB / 500 kB).
- Real user browser reset-link request completed successfully and a fresh Reset your password message appeared in local Mailpit. User must open its link and personally enter/confirm/submit the new password. No real user password was changed by the agent. Full real-account reset/login remains pending that user step; email verification remains required separately.

### Local verification delivery repair — 2026-11-01

- Diagnosed local signup block: account existed but was unverified; SMTP was configured for 127.0.0.1:1025 and the local Mailpit service was stopped. Angular and Laravel were also stopped. Existing PostgreSQL stayed running. Started Mailpit, Laravel on 8000 and Angular on localhost:4300 as hidden native processes using the documented runtimes; no Docker, migrations, keys or mail credentials changed.
- Real browser login reached /verify-email; Resend verification completed successfully and Mailpit showed a new verification message for the account. This verifies the local login/resend session and CSRF path, not full registration/verification end to end. Left the local inbox open for the user to follow their verification link; did not manually mark the account verified.
- Verification page now explains local capture and links to the local inbox only in development mode on a loopback hostname. Hosted/production copy directs users to their email inbox/spam. Production build passed; initial-size warning remains approximately 514.60 kB / 500 kB. Screenshot: .local/login-review/verification-local-inbox.png.
- Mailpit captures local messages and does not deliver to Gmail or other personal mailboxes. Real outbound delivery remains a separately configured sender/readiness gate. If signup saved an account while mail was unavailable, sign in and resend instead of creating the account again.

### Direct signup milestone — 2026-11-01

- User removed invitation codes from signup; this supersedes earlier invitation requirements and invitation validation notes below. Frontend invitation input, validators, payload and copy removed; Laravel no longer validates, looks up or consumes an invitation when creating an account. No migrations needed; dormant invitation tooling/data retained.
- Required fields, password confirmation/strength, unique email, privacy acknowledgement, email verification, transactional owner/family creation and family isolation remain. No billing, tiers, purchases or hosted rollout included.
- Verified: all 18 backend tests passed, 102 assertions, exclusively against toddler_daily_test PostgreSQL. Signup succeeds without invitations, creates one owner/family, stores a hashed password/privacy acknowledgement, and requires verification for record access; invalid details and duplicate email are rejected. Existing family isolation tests pass.- All 13 frontend tests passed, including invitation-free signup payload and navigation to email verification. Production build passed; existing initial-size warning remains at approximately 514.57 kB / 500 kB. Browser inspection of the isolated production preview confirms no invitation field or invitation copy. Screenshot: .local/login-review/direct-signup.png. No user account was created outside the dedicated test database. Real browser authentication/session/CSRF verification and existing hosted readiness gates remain outstanding.

### Account form validation — 2026-11-01

- Submit buttons now remain clickable until a request is pending so missing data is explained instead of silently blocking the action. Invalid forms mark fields touched, show an alert and inline errors, and focus the first invalid field without sending a request. Errors also appear on blur and update while correcting touched fields.
- Added name/length, invitation format, timezone and password-confirmation validation alongside required/email/privacy checks. Laravel remains authoritative for invitation validity, expiry, email matching, timezone support and account creation. Server field errors appear beside their controls and clear when edited. Inputs expose aria-invalid and described error/help text.
- Registration now explains that the operator supplies a private, email-bound invitation link that prefills the code. Preferred operator command remains tracker:invite; see local-accounts.md. No public invitation request endpoint or account/invitation was created by this change.
- All 12 frontend tests passed (including five new account validation tests); production build passed. Existing initial-size warning is now approximately 514.57 kB / 500 kB; component CSS stays under 4 kB. Test runner needed normal temporary-file access after its restricted run could not open a generated temporary module.
- Browser check on isolated production preview confirmed empty registration displays all missing-field messages and focuses Your name. Screenshot: .local/login-review/registration-validation.png. API was isolated; no family database used or form successfully submitted. Real authentication/session/CSRF end-to-end verification remains outstanding.

### Account-entry design milestone — 2026-11-01

- User requested frontend-design skill, a toddler image replacing the FF-like mark, and a more fitting login palette. Implemented the skill's Organic anchor with sage/sand/moss, system Palatino/Georgia headings, rounded controls and subtle grain. New original vector assets: public/toddler-mark.svg and public/toddler-play.svg; no remote assets or font requests.
- Account component styles are scoped; login, invitation registration, recovery and password-reset screens share the presentation. App header and browser tab use the toddler mark; signed-in page colors and architecture remain unchanged.
- Production build passed; component styles remain below their 4 kB warning threshold. Existing initial-bundle warning remains: approximately 512.17 kB against 500 kB. No budget raised.
- Production UI browser checks passed at 1440, 768, 390 and 320 px: no horizontal overflow; login button validation; recovery link and email validation; invitation token/email prefill; new-password minimum; unavailable-server alert; no browser page errors. Synthetic inputs only, no form submissions and no family data used.
- Isolated temporary production preview is on 127.0.0.1:4399, serving built files with API routes returning 401 rather than reaching Laravel. Review scripts/screenshots live in .local/login-review; design review artifacts in .taste-skill/login-review. These checks do not verify real authentication, sessions or CSRF.
- Screenshot review prompted darker text and a full-opacity disabled button for contrast, plus content-sized rows on tablet. Desktop, tablet and phone renders inspected. Larger welcome typography and centered narrow form are intentional account-entry design choices; dashboard density rules do not apply to this focused login screen. The tall sage illustration panel is deliberate. Lint's shadow warning is for box-shadow:none, which removes Bootstrap's default input glow.
- Final built-page inspection: 0 failures, no contrast warnings at 1440/768/390 px. Three layout/heading warnings are the intentional login choices described above. Button uses a darker moss shade for readable sand text. Final production build passes with only the existing initial-bundle warning.
- Supplemental taste sheet: editorial-greeting, seed 1289809334, palette/type kept. User-specified frontend-design Organic tokens take precedence over that sheet's dashboard shell and hue restrictions. No fabricated account data or added dependencies.
- Remaining release gates below still apply; no deployment, external onboarding or backend changes in this milestone.

Earlier backend run on 2026-11-01 (before direct signup):
- 17 tests passed, 86 assertions, against toddler_daily_test on PostgreSQL (including invitation command validation and replacement).
- Tests cover invitation behavior, cross-family access, constraints/version conflicts, intake integrity/idempotency, reminder retries/invalidation, export and erasure.

Earlier frontend verification:
- 7 tests passed across session/API and timezone helpers.
- Production build passed with an initial-bundle size warning (about 512 kB against the 500 kB warning budget).
- Development frontend compiled and started during this handoff.

Not yet verified:
- Complete browser flows with real session cookies/CSRF.
- Desktop/mobile visual inspection of every screen.
- Hosted proxy, cold starts and Supabase Cron.
- Encrypted backup restoration and deletion-ledger replay.
- Production credentials, sender, privacy readiness or pilot eligibility.

## Local addresses

Frontend: http://localhost:4300/ (preferred browser address).
Backend: http://127.0.0.1:8000/.
Backend readiness through frontend: http://localhost:4300/api/v1/health.
Native isolated database: 127.0.0.1:54329.

These are development servers, not permanent services. Check that they are running when resuming a session. Do not claim a localhost service is live without an HTTP check.

Latest local verification: Toddler Daily was restarted outside the restricted process context with Angular bound to localhost:4300. HTTP checks returned 200 for the frontend, 200 with status ready for /api/v1/health through the frontend proxy, and 200 for the direct backend health endpoint. Angular's default host is now localhost. Run the frontend outside the restricted process context when the agent's confined process is unreachable. The user's separate bus schedule application on port 4200 was not stopped or changed.

Create an account directly at /register; there is no default password and no invitation requirement. Earlier invitation tooling remains dormant; see [local-accounts.md](local-accounts.md). Native Mailpit v1.33.0 is available under .local/mailpit for verification and recovery emails. Its UI listens on 127.0.0.1:8025 and SMTP on 127.0.0.1:1025; start it separately when needed. Local messages are not sent to external mailboxes.

## Runtime paths on this computer

Global Node 22.17 is too old for this Angular scaffold. Use Node 24.22.1:

~~~powershell
& 'C:\Users\whin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' --version
~~~

Local PHP 8.4.13 is available through Herd; production target remains PHP 8.5:

~~~powershell
& 'C:\Users\whin\.config\herd\bin\php84\php.exe' --version
~~~

Composer PHAR: C:\ProgramData\ComposerSetup\bin\composer.phar.
npm CLI JS: C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js.
Invoke that CLI with the compatible Node binary if npm.cmd selects the older global Node.

## Native startup without Docker

The repository-local PostgreSQL runtime is ignored by Git. It is a machine-specific fallback, not a dependency to commit or deploy.

Check first:

~~~powershell
Set-Location 'C:\workplace\Codex\toddler daily'
& '.\.local\postgresql-runtime\pgsql\bin\pg_isready.exe' -h 127.0.0.1 -p 54329
~~~

Only if not running, start the existing project cluster, preserving its configured port:

~~~powershell
& '.\.local\postgresql-runtime\pgsql\bin\pg_ctl.exe' start -D 'C:\workplace\Codex\toddler daily\.local\pgdata' -l 'C:\workplace\Codex\toddler daily\.local\postgres.log' -o '-p 54329 -h 127.0.0.1' -w
~~~

Databases toddler_daily and toddler_daily_test already exist, with app schemas; development migrations have been applied. Never reinitialize the existing cluster or delete a postmaster PID file just because a health check fails. Inspect process/log information first.

In separate terminals:

~~~powershell
Set-Location 'C:\workplace\Codex\toddler daily\backend'
& 'C:\Users\whin\.config\herd\bin\php84\php.exe' artisan serve --host=127.0.0.1 --port=8000
~~~

~~~powershell
Set-Location 'C:\workplace\Codex\toddler daily\frontend'
& 'C:\Users\whin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' node_modules/@angular/cli/bin/ng.js serve --host localhost --port 4300 --proxy-config proxy.conf.json
~~~

Angular's serve defaults now select port 4300 and proxy.conf.json, so npm start uses them automatically. Keep browser requests on port 4300 so cookies and API calls share an origin. Port 4200 is reserved for the user's separate bus schedule project; do not stop or change that project.

backend/.env already exists and has a generated app key. Never print or commit it. .env.example contains development defaults, not hosted credentials. Do not regenerate an existing encryption key: stored encrypted records depend on it.

## Tests

~~~powershell
Set-Location 'C:\workplace\Codex\toddler daily\backend'
& 'C:\Users\whin\.config\herd\bin\php84\php.exe' artisan test
~~~

~~~powershell
Set-Location 'C:\workplace\Codex\toddler daily\frontend'
& 'C:\Users\whin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' node_modules/@angular/cli/bin/ng.js test --watch=false
& 'C:\Users\whin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' node_modules/@angular/cli/bin/ng.js build
~~~

The backend test harness refuses to use anything other than the dedicated PostgreSQL test database. RefreshDatabase destroys/rebuilds test tables; never weaken this guard or point tests at family data.

In this Codex environment, PHP runtime/tests may need tool escalation because cache directories are reported unwritable inside the restricted execution context. Explain the actual tool permission if prompted.

## Important implementation details

- Database connection timezone is UTC; business models use offset-aware timestamp formatting.
- Newly created mutable models define version defaults explicitly because database defaults are not automatically hydrated by Eloquent.
- Appointment schedule_version defaults to 1; reminder comparison must use consistent types.
- Privacy export selects repeatable-read/read-only when it owns the outer transaction; nested callers own their isolation.
- Tests simulate an actual browser Origin for Sanctum stateful requests.
- Frontend referrer policy is strict-origin. Changing it to no-referrer can break Sanctum's stateful detection on GET requests.
- Do not automatically retry writes. Intake request UUIDs survive a retry and detect mismatched payload reuse.
- Supabase privileged keys stay server-side.
- SMTP's stable Message-ID alone does not guarantee deduplication. Resend requests use stable idempotency keys.

## Next work, in recommended order

1. Finish reproducible local startup; direct signup is now the approved flow documented in local-accounts.md. Verify the entire registration/verification/login flow in a browser.
2. Add actual browser authentication/CSRF and core record workflow verification with synthetic data.
3. Review UI/accessibility on desktop and mobile; address the build size warning without hiding it.
4. Review reminder behavior after child restore or email opt-in changes; enforce the maintenance budget including purge.
5. Create production container/Render configuration and a Vercel Build Output API proxy build.
6. Add Supabase private roles, separate runtime/migrator privileges, due-work-only Cron and Vault setup.
7. Add bounded retention, encrypted backup tooling and restore/deletion-ledger replay tests.
8. Add PostgreSQL CI and formatter checks.
9. Configure synthetic hosting only after target accounts/projects are established; then verify cold starts, cookies, CSRF and failure states.
10. Complete sender, privacy, backup and hosting eligibility gates before external-family onboarding.

See PROJECT_PLAN.md for the full phase/checklist details.

## Decisions and constraints future agents must preserve

- No paid upgrades or purchases; no fixed deadline.
- One family owner in MVP; sharing deferred.
- Manual medication records only; recurring schedules deferred.
- No uploads, clinical advice, pediatric dose calculations or assumed vaccination schedules.
- Laravel is the only backend accessed by the browser; Supabase supplies PostgreSQL.
- No migrations on every web startup and no permanent queue worker on Render Free.
- No artificial keep-alive traffic.
- Private data must not reach frontend bundles, logs, public caches or preview environments.
- Do not introduce an unnecessary repository layer or state-management framework.

The user declined installing @vercel/functions. Do not retry that package without a new request. The agreed alternative is a dependency-free Vercel Build Output API generator for fixed external proxy routes; this has not been implemented yet.

## Docker incident

Docker failed opening its dockerInference Windows socket after an agent-driven launch. A matching issue reports packaged-app filesystem virtualization as a possible cause. The cause on this machine has not been definitively established.

Do not launch Docker Desktop through the agent shell again. Ask the user to open it from Windows Start when needed. Never factory-reset Docker, unregister its WSL distributions or remove runtime directories as an automatic repair. No Docker data was changed.

See [docker-troubleshooting.md](docker-troubleshooting.md). Native PostgreSQL/PHP/Angular development is available independently.

## Documentation hygiene

README.md currently links to local-development.md, deployment.md and backup-and-recovery.md that have not been completed. Do not present those procedures as already available. This plan and handoff are intended to make unfinished work explicit.

There is no verified hosted URL, configured external Git remote or deployment. Establish actual state before creating/pushing/deploying anything.


### Entry deletion (2026-11-04)
Confirmed Delete actions cover appointments, medications, timeline, growth and vaccinations. Appointment reminders and notifications are removed transactionally. Empty child profiles can be deleted under Edit. Medication intake history is retained through reasoned void revisions. Family scope, version checks and auditing enforced. Production frontend build passed with existing size warning; 40 backend tests passed with 466 assertions on the dedicated test database. No live family deletion tested.

### Dashboard current medications (2026-11-04)
Added Current medications panel with supplied instructions, child, date range and intake-history link. Uses family-local today, inclusive recorded start/end and open-ended end dates. Excludes missing starts, archived medications/profiles and other families. TrackerTest passed (17 tests, 125 assertions), including date boundaries and family isolation. Production build passed with existing 533.59 kB warning. Live dashboard displayed existing matching medication. No real records changed. Screenshot .local/ui-review/dashboard-medications.png.

### Child tabs and Life event (2026-11-04)
Child tabs reordered to Medications, Appointments, Vaccinations, Timeline, Growth. Timeline entry dropdown and API validation/filter accept life_event; dashboard displays Life event label. Added PostgreSQL check-constraint migration with rollback protection for existing life events. Dedicated database create/update/filter/dashboard test passed (8 assertions). Production frontend build passed with existing size warning.

### Dashboard instructed amount (2026-11-04)
Current medications now displays recorded instructed amount and dose unit when supplied, matching child medication records. Production build passed with existing size warning. Verified on live dashboard with supplied amount and unit. Screenshot .local/ui-review/medication-amount.png. No records changed.

### Dashboard child-name typography (2026-11-04)
Appointment and current-medication child names share 15px semibold body typography and full ink color. Appointment provider moved onto its own secondary line. Production build passed with existing bundle warning.

### Dashboard clinic and birthdays (2026-11-05)
Upcoming appointment clinic appears below date/time with Clinic not recorded fallback. Children age now followed by dash and full month/day birthday using UTC date-only formatting. Verified live dashboard. Responsive details stack on phones. No backend changes.

### Dashboard medication links (2026-11-05)
Split each medication row into sibling links. Medication information opens its child's Medications page; View intake history opens the medication-specific Administration history. Verified both normal clicks in browser without writes. Production build passed with existing bundle warning. Screenshot .local/ui-review/medication-separate-links.png.

## Local restart — 2026-11-07

- Started the existing native PostgreSQL cluster, Laravel API, Angular frontend, and Mailpit after finding their ports idle. PostgreSQL completed automatic recovery and accepted connections. No migration or database reset was needed.
- Verified HTTP 200 on /register, direct and proxied API readiness, and Mailpit's inbox; the CSRF endpoint returned 204 and Mailpit SMTP was listening on 1025. Angular compiled successfully. No account was created and no test email was sent.


### Dashboard Manage medications navigation (2026-11-08)

- Manage medications carries the requested tab through the Children picker. Selecting a child opens that child's Medications tab. Opening the ordinary Children page still leads to Timeline by default.
- All 33 frontend tests passed, including a router regression test that clicks through both paths with mocked API data and checks the selected tab. Production build passed with the existing initial-bundle warning (534.62 kB against 500 kB). Live signed-in browser verification remains outstanding.


### Medication instructed amount display (2026-11-09)

- The Medications tab trims fractional trailing zeros in saved record summaries and edit fields. Whole numbers, meaningful decimal digits, and blank amounts are preserved. Formatting is frontend-only.
- All 34 frontend tests passed, including add and edit checks with mock data. Production build passed with the existing initial-bundle warning (534.62 kB against 500 kB). Live signed-in browser verification remains outstanding.


### Dashboard recent entries by calendar year (2026-11-09)

- The Recent memories and milestones panel shows up to five entries dated within the current calendar year in the family's timezone. The Timeline still includes earlier years. The dashboard empty state now refers to this year.
- All 43 backend tests passed with 485 assertions on the dedicated PostgreSQL test database, including the family-local New Year boundary and Timeline history. Production frontend build passed with the existing 534.62 kB initial-bundle warning. Live signed-in browser verification remains outstanding.

## WeeGrow branding - 2026-11-09

- Renamed the visible app, browser title, export filenames, and email sender display name to WeeGrow. The workspace path, database names, URLs, mail address, application key, and Docker identifiers retain their existing values. Existing session-cookie and cache prefixes are explicit in the local environment and example.
- Angular production build passed with its existing initial bundle size warning. All 34 frontend tests passed. Laravel loaded the new display names and preserved session/cache identifiers. Local login and registration returned the WeeGrow title, the API reported ready through the frontend, the CSRF endpoint issued the existing session cookie name, and Mailpit responded. No email was sent or family data used for testing.

## Workspace folder rename pending

- Updated the local frontend restart and demo launch helpers to resolve paths from their own locations. After the separate Laravel terminal was closed, Windows still refused to rename the directory with the local services and frontend workers stopped. The services were restarted from the current path. The original workspace remains in place.
