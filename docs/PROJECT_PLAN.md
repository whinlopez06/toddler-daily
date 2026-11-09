# WeeGrow — approved implementation plan

Updated: 2026-11-04 (Asia/Manila).
Status: implementation in progress; local checks passed as described in HANDOFF.md. No hosted deployment or pilot launch has been verified.

## Reading order for another agent

1. Read this file for approved scope and architecture.
2. Read [HANDOFF.md](HANDOFF.md) for verified progress, known gaps, local commands and the next tasks.
3. Read [architecture.md](architecture.md), then inspect the actual source and tests.
4. Keep these documents current after meaningful changes. Distinguish implemented code, passing checks and operational readiness.

The user has authorized implementation. The earlier planning-only restriction was superseded by the explicit implementation request. Work one milestone at a time; explain material changes to agreed architecture. Do not infer authorization to purchase services, publish sensitive data, or connect previews to the pilot database.

## 1. Product and goals

A private family web application for organizing children's development, memories and parent-supplied health records. It supports multiple children and a combined family overview.

The first release serves a small supervised pilot of independent families. Per the user’s 2026-11-01 decision, signup no longer requires an invitation; invitation-based access and free/paid tiers are deferred product decisions. Hosted signup and external onboarding still require the readiness gates below. Each family has one owner. Family isolation and privacy are release requirements.

This is a record organizer. It must not recommend medication, calculate pediatric doses, assess development medically, or promise reliable medical reminders.

## 2. Confirmed scope

### Portfolio demo — approved 2026-11-03

- One address, one Laravel backend and one application database for normal and demo accounts. The demo_accounts relationship identifies demo users; server-side family ownership isolates every account. Each demo starts with fictional sample records. This supersedes the separate-deployment decision.
- Email/password demo signup without email verification, one isolated family per demo, fixed 24-hour server-enforced access. Preserve normal account verification and family isolation.
- Retain accounts and bounded login history after expiry; owner review/revocation through protected server commands. No public visitor-history endpoint.
- Disable recovery, verification and reminder delivery for demo accounts only; preserve normal account flows. Restrict demo creation, API calls, writes, child count, record count and retained event volume.
- Preserve the existing browser-session architecture, CSRF, secure production cookies, immutable intake revisions and no-cost hosting boundaries.
- Hosting remains a separate readiness milestone: verify least-privilege database credentials, protected application key, HTTPS/proxy cookies/CSRF, trusted proxy IP handling, no-cache responses, capacity and the privacy/contact notice before publishing the portfolio link. No hosted deployment is implied by source implementation or local tests.

### Phase 1 — MVP

| Capability | Purpose | Complexity | Acceptance |
| --- | --- | --- | --- |
| Direct signup, email verification, password recovery | Private family accounts | Medium | No invitation required; valid details, unique email and verified session required for family records |
| One owner per family | Clear access boundary | Medium | Other families cannot access nested records, summaries, exports or notifications |
| Multiple child profiles | Organize siblings | Low | Create, update, archive and switch children; age derived from birth date |
| Timeline and milestone entries | Preserve memories and achievements | Medium | Date, title, category, notes and child; chronological browsing |
| Growth measurements | Track supplied measurements | Medium | Calendar date; positive quantities and explicit units; no clinical interpretation |
| Medication instructions and manual intake history | Preserve accurate supplied records | High | Schedule/instructions distinct from actual intake; unknown dose stays unknown |
| Intake correction history | Auditable medication history | High | Append corrections; original remains; idempotent submissions; no revision forks |
| Vaccination records | Record vaccinations already given | Medium | Vaccine, administration date, dose/provider/notes where supplied |
| Appointments | Organize upcoming visits | Medium | Timezone-aware scheduling; scheduled/completed/cancelled lifecycle |
| Fixed dashboard | Useful family or child overview | Medium | Honest empty states and scoped upcoming/recent activity |
| In-app appointment reminders | Best-effort assistance | High | Durable delivery records; duplicate triggers do not duplicate notifications |
| Optional email reminders | Additional delivery channel | High | Opt-in and configured sender; acceptance distinguished from confirmed delivery |
| Export and family deletion | User control over data | High | Recent password confirmation; immediate access/reminder disable; eventual erasure |
| Audit, secure sessions, backup/restore procedure | Safe operation | High | Sensitive changes audited; protected storage; tested recovery before pilot |

Appointment reminders are intended for 24 hours and one hour before the appointment. No recurring medication scheduler is in the MVP.

### Phase 2 — enhancements

- Guardian invitations and explicit shared-family permissions.
- Recurring medication schedules, with planned dose instances separate from intake revisions.
- Secure private uploads with type/size checks and appropriate scanning.
- Better calendar views and reminder preferences.
- Dashboard widget selection and ordering.
- Browser push if useful and operationally supported.

Dependencies: proven family isolation, stable appointment/reminder processing, operational readiness and feedback from the pilot. Sharing must include revocation and audit tests; recurring schedules must include timezone, missed-dose and revision behavior.

### Phase 3 — advanced features

- Country-specific vaccination schedule suggestions with sourced/versioned guidance.
- Age-based milestone suggestions explicitly separated from medical assessments.
- Growth visualizations and user-controlled analytics.
- Advanced personalization and automation.

Dependencies: reliable underlying records, appropriately sourced guidance and separate privacy/security review. Do not silently infer medical advice from parent-entered data.

## 3. Stack and hosting

| Layer | Decision |
| --- | --- |
| Frontend | Angular 22, TypeScript, standalone feature components, Reactive Forms, Signals and RxJS |
| Styling | Bootstrap 5 plus restrained responsive application styles; system fonts |
| Backend | Laravel 13; PHP 8.5 deployment target; local PHP 8.4+ supported |
| Authentication | Laravel Fortify and Sanctum browser sessions |
| Database | PostgreSQL, private app schema; Supabase Free in hosting |
| Local database | PostgreSQL 17 currently; match the actual hosted project's supported major before release |
| Frontend hosting | Vercel Hobby, subject to personal/non-commercial eligibility |
| Backend hosting | Render Free Docker web service; Singapore where available |
| Scheduler | Supabase Cron + pg_net; secrets in Supabase Vault |
| Local email | Mailpit with Docker; native setup requires a separately running mail catcher |
| Repository | One repository with independent frontend/backend builds |

Free-tier accounts do not imply a working outbound email sender. Configure a valid SMTP/API sender before independent families onboard. No purchases or paid upgrades are authorized.

Versions above describe the approved major versions and existing scaffold. Preserve lockfiles; verify official support and hosting limitations when preparing a release rather than treating version/price assumptions as permanent.

## 4. Architecture

~~~mermaid
flowchart LR
    Parent[Parent browser] --> Frontend[Angular on Vercel]
    Frontend -->|Same-origin proxy| API[Laravel on Render]
    API --> DB[(Supabase PostgreSQL)]
    Cron[Supabase Cron + pg_net] -->|Only when work is due| API
    API --> Mail[Configured email provider]
~~~

Use a modular monolith. Angular communicates only with Laravel. Supabase Auth and direct browser database access are unnecessary.

Proxy /api, /auth and /sanctum through Vercel. Laravel owns authentication, authorization, validation, business rules and response resources. Keep the internal scheduler endpoint off the public frontend routing surface.

Cookies must work on provider domains without a custom domain purchase. Production session cookies are host-only, Secure and HttpOnly. CSRF protection remains enabled; Angular may read the CSRF token cookie. Private responses must not enter shared caches.

Cold starts need a clear loading state. Preserve entered forms and do not automatically replay failed writes.

### Frontend layers

~~~text
frontend/src/app/
  core/
    api/               DTOs, API client, request/error handling
    auth/              Session state and route guard
    layout/            Application shell and navigation
  shared/              Date/time helpers and reusable interface pieces
  features/
    auth/
    dashboard/
    children/
    records/
    intakes/
    activity/
    notifications/
    settings/
~~~

Keep components focused on presentation and user interaction. Use API services for HTTP, feature state/facades where complexity warrants them, DTOs for wire contracts and mappings/view models when they actually differ. Do not add a mapper or repository for every class by default.

Signals hold local/current view state; RxJS supports HTTP and cancellation where appropriate. Server authorization is mandatory even when route guards hide a page.

### Backend layers

~~~text
backend/app/
  Actions/
    Accounts/
    Appointments/
    Medications/
    Privacy/
  Http/
    Controllers/Api/V1/
    Middleware/
    Requests/
    Resources/
  Models/
  Policies/
  Providers/
backend/database/
  migrations/
  schema/
  factories/
backend/tests/
  Feature/
  Unit/
~~~

Controllers coordinate. Form Requests validate. API Resources allowlist response fields. Policies and family-scoped lookups authorize. Actions own multi-step transactions, intake corrections, reminder delivery and deletion. Eloquent is sufficient for normal data access; no generic repository layer is required.

Jobs/events/notifications may be introduced where they simplify behavior, but the free web service must not run a permanent queue worker.

## 5. Data design and invariants

The committed migrations and PostgreSQL schema SQL are the source of truth for exact columns, indexes and constraints.

Core entities:
- users, families and children; pilot_invitations retained as dormant earlier scaffolding.
- timeline_entries (including recorded milestone categories).
- growth_measurements.
- medications and medication_intakes (append-only corrections).
- vaccination_records and appointments.
- reminder_deliveries and notifications.
- audit_logs and deletion_requests.
- Framework sessions, cache/locks and other scaffold support tables.

Relationships:
- One owner user owns one family; one family has many children.
- Every child record belongs to exactly one child, and authorization resolves the child's family.
- Intake rows belong to the medication and matching child.
- Corrections reference the prior intake; unique predecessor prevents branching.
- Appointment delivery identity includes appointment, schedule version, recipient, lead time and channel.
- In-app notification delivery identity is unique.

Use ULIDs for business identifiers. Foreign keys, composite keys, check constraints and indexes reinforce application validation. Index family/child lookup paths, chronological listing and due-work claims.

Use date for birthdays and calendar events; timestamptz for instants; numeric for quantities. Store instants consistently in UTC and keep relevant IANA timezone information. Birthdays must not shift through UTC conversion.

Optimistic versions prevent stale record overwrites. Audit sensitive mutations. Encrypt medical notes, supplied instructions, intake snapshots and audit differences at the application layer; keep the application encryption key outside the database backup.

Intakes are immutable at both application and database levels. A narrowly scoped transaction setting permits intentional family erasure; it is not an ordinary correction mechanism.

## 6. API contract

Use /api/v1 with resource nouns and standard HTTP methods. Fortify lives under /auth to avoid frontend route conflicts. Sanctum's CSRF endpoint is /sanctum/csrf-cookie.

| Route group | Behavior |
| --- | --- |
| GET /api/v1/health | Public minimal database readiness |
| /auth/* | Direct registration, login/logout, verification, recovery |
| GET/PATCH /api/v1/me | Current account/profile preferences |
| GET/PATCH /api/v1/family | Owned family settings, version checked |
| GET/POST /api/v1/children | Owned child listing/create |
| GET/PATCH /api/v1/children/{child} | Child details/update/archive |
| /api/v1/children/{child}/{recordType} | Timeline, growth, medications, vaccinations and appointments |
| /api/v1/children/{child}/medications/{medication}/intakes | History and idempotent intake creation |
| .../intakes/{intake}/corrections | Append correction or void revision |
| GET /api/v1/dashboard | Family/child overview |
| GET /api/v1/timeline, /appointments | Scoped combined listings |
| GET /api/v1/notifications | Owned notifications |
| PATCH /api/v1/notifications/{notification} | Mark own notification read |
| POST /api/v1/notifications/refresh | Refresh due in-app deliveries for current family |
| POST /api/v1/confirm-password | Recent confirmation for privacy tools |
| GET /api/v1/family/export | Scoped JSON export after confirmation |
| DELETE /api/v1/family | Disable access and request erasure |
| POST /internal/maintenance/tick | Scheduler-authenticated bounded work |

Check routes/api.php and route:list for exact recordType paths and methods; this table summarizes contracts rather than overriding actual routes.

Resources return data envelopes; paginated lists include pagination metadata/links. Errors have a safe message, code, optional field errors and request identifier. Validation uses 422; missing authentication 401; forbidden access 403 or scoped 404; stale versions/idempotency conflicts 409; recent-password/account-lock requirements 423.

Allowlist sorting/filtering, paginate listings and validate IDs through scoped lookups. Calendar dates use YYYY-MM-DD. Instants require an offset; the interface uses explicit IANA timezone conversion, including ambiguous/nonexistent daylight-saving times.

## 7. Reminder processing

Render Free sleeps; Supabase Free projects may pause. Reminders are best effort and must be described that way in the interface.

- No free Render background worker or cron is assumed.
- No frequent Vercel Hobby cron is assumed.
- Supabase checks due database work each minute.
- Only if due work exists, pg_net calls Laravel directly on Render.
- Scheduler URL and dedicated bearer secret live in Vault.
- The endpoint accepts no caller-selected child/family IDs and returns operational counts only.
- Rate limit, database-backed global lock, row claims and expiring leases prevent overlap.
- Maximum application processing budget: 20 seconds, with small bounded batches; HTTP timeout accommodates cold start.
- Durable pending rows survive failures and are retried on subsequent ticks.
- Retry transient failures after one, five and fifteen minutes.
- Expire at intended time plus one hour or appointment start, whichever comes first.
- Rescheduling/cancellation invalidates old schedule versions.
- Refresh due in-app reminders on application opening as recovery.
- Use stable provider idempotency keys for email where supported.
- SMTP acceptance is not confirmed delivery; ambiguous SMTP failures need visible review rather than blind duplicate sends.
- No artificial keep-alive traffic.

Production scheduling SQL, end-to-end cold-start verification and operational failure visibility remain release work until explicitly recorded as verified.

## 8. Security and privacy

Essential MVP safeguards:
- Direct registration; verified email; strong password validation; rate limiting.
- Database-backed encrypted sessions, CSRF, secure production cookies and HTTPS.
- Server-side family authorization for detail, nested IDs, aggregates, export and notifications.
- Restrict runtime database credentials; separate migration identity; TLS to the Supabase session pooler.
- Store tables in non-exposed app schema; never put privileged Supabase keys in Angular.
- No medical notes, recipients, credentials or SQL bindings in logs.
- No shared caching of authenticated responses.
- Recent password confirmation for export and deletion.
- Disable access and reminders immediately when deletion is requested.
- Append audit history for sensitive mutations and intake corrections.
- No real personal records in preview environments or test fixtures.
- Minimize collected information and keep the privacy notice honest about hosting and reminder limits.
- Philippine privacy obligations and hosting eligibility require review before external onboarding.

No uploads are in Phase 1. Private uploads later require size/type limits, access checks, private object storage, authorized downloads and retention/deletion behavior.

## 9. Backup, erasure and recovery

Supabase Free automatic backups are not assumed. Prepare an operator-run PostgreSQL export procedure that encrypts output without leaving plaintext backups, after active days and before remote migrations.

Retain backups for no more than 30 days. Keep recovery keys separately and maintain an independent off-site copy. Test restoration locally before any real-family onboarding.

A minimal encrypted deletion ledger must exist outside the restored application database. Apply it before reopening a restored database, so old backups do not resurrect deleted families. The current code writes this before purge, but an independent recovery-copy/replay procedure still needs verification. Do not treat same-project storage alone as an independently tested recovery strategy.

Deletion runs in bounded maintenance steps and stays pending until complete. Report measured recovery and deletion targets, not unsupported guarantees.

## 10. UI and accessibility

Main navigation: overview, children, timeline, appointments, notifications, settings. A child's records include timeline, growth, medications/intakes, vaccinations and appointments.

Prioritize clear empty states, readable forms, mobile layouts, visible focus, semantic labels, keyboard access, adjacent validation errors and plain dates/units. Avoid fabricated dashboard statistics or prefilled medication doses. The signed-in interface retains its quiet neutral palette and system typography. Per the user's 2026-11-01 redesign request, account-entry screens use an Organic sage/sand/moss theme, system serif headings, rounded controls, subtle grain and original toddler SVG artwork. The toddler mark also identifies the app header and browser tab.

Important flows:
1. Invitation → registration → verification → family overview.
2. Add child → add a record → review history.
3. Record intake → retry safely → correct through a new revision.
4. Schedule/change appointment → review reminder status.
5. Confirm password → export or request deletion.

## 11. Implementation sequence

| Milestone | Dependencies | Exit criteria |
| --- | --- | --- |
| 1. Baseline and local foundation | Approved plan | Separate projects, PostgreSQL schema, local configuration, reproducible start |
| 2. Accounts and isolation | Foundation | Direct signup, sessions, verification and cross-family tests pass |
| 3. Core records | Ownership policies | Child/timeline/growth/vaccine/medication flows work with validation |
| 4. Intake history | Medications and auditing | Idempotent requests, immutable revisions, correction tests pass |
| 5. Appointments | Timezone handling | Lifecycle/version changes plan and invalidate deliveries |
| 6. Notifications/maintenance | Durable deliveries | Duplicate/retry/interruption tests; protected endpoint; hosted scheduler verified |
| 7. Privacy/operations | All personal tables known | Export, ledger, erasure, encrypted backup and restore replay verified |
| 8. Synthetic staging | Deployment configuration | Vercel/Render/Supabase connectivity and cookie/CSRF flow verified |
| 9. Pilot readiness | Staging evidence | Sender configured, privacy/eligibility reviewed, failure operations tested |
| 10. Supervised pilot | Readiness gates | Small supervised onboarding and documented feedback |

Build feature by feature. Do not equate files existing with release acceptance.

## 12. Remaining task checklist

- [ ] Complete reproducible local setup instructions, direct signup workflow and startup scripts.
- [ ] Browser-test the actual authentication and record workflows on desktop and mobile.
- [ ] Resolve frontend production build size warning through sensible style imports.
- [ ] Review archive/restore and email-preference changes against future reminders.
- [ ] Enforce and test the maintenance time budget including purge/network work.
- [ ] Complete bounded delivery retention and external ledger retention/replay.
- [ ] Add production PHP 8.5 container, Render configuration and Vercel proxy build.
- [ ] Add Supabase private roles/schema bootstrap and due-work-only Cron/Vault SQL.
- [ ] Add PostgreSQL integration CI and formatting checks.
- [ ] Implement and test encrypted backups, restore procedure and deletion-ledger replay.
- [ ] Deploy synthetic staging with separate migration credentials.
- [ ] Verify proxy cookies, CSRF, no-cache headers, cold starts, pauses and sender failures.
- [ ] Complete privacy and personal/non-commercial hosting eligibility review.
- [ ] Invite external families only after readiness is established.

## 13. Testing and release strategy

Backend feature/integration tests must use real PostgreSQL, not SQLite. Cover ownership, nested access, schema checks, version conflicts, idempotency, revision integrity, scheduler duplicates/leases/retries, invalidated deliveries, export and deletion.

Frontend tests cover forms, API/session behavior and calendar/timezone conversion. Browser workflows must traverse browser → proxy → Laravel → PostgreSQL → visible result. Include mobile layouts and actual session/CSRF exchange.

Test failure modes: sleeping API, paused database, email provider errors, quota exhaustion, interrupted processing and stale form versions. Validate no private data leaks to logs, caches or frontend bundles.

Migrations run through a dedicated local/CI release step with migration credentials, never on every web-service startup. Keep previews connected to synthetic data. Do not create a public repository or deploy a pilot database without establishing the correct account/project and environment.

## 14. Assumptions and unresolved release decisions

Confirmed defaults: no purchases; no deadline; Singapore Render/Supabase where available; local development before hosting; one family owner; manual medication history.

Still to verify/configure: exact hosted PostgreSQL major, project URLs and credentials, outbound email provider/sender, independent encrypted backup destination and key custody, deletion-ledger recovery copy, Vercel Hobby eligibility and Philippine privacy review.

## 15. Official references for release verification

- [Render Free](https://render.com/docs/free)
- [Supabase pricing](https://supabase.com/pricing)
- [Supabase Laravel setup](https://supabase.com/docs/guides/getting-started/quickstarts/laravel)
- [Supabase Cron](https://supabase.com/docs/guides/cron)
- [Supabase pg_net](https://supabase.com/docs/guides/database/extensions/pg_net)
- [Supabase backup guidance](https://supabase.com/docs/guides/platform/backups)
- [Vercel Hobby](https://vercel.com/docs/plans/hobby)
- [Vercel Cron limits](https://vercel.com/docs/cron-jobs/usage-and-pricing)

These links are references to recheck during release; their inclusion is not evidence of deployed configuration.

