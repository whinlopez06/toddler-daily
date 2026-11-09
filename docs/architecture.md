# Architecture decisions

## Boundaries

One repository, separate frontend and backend deployments, one relational database. The browser never connects directly to Supabase. Laravel policies and scoped lookups enforce family access. Supabase Auth is not used.

The MVP has one owner per family and one family per account. Guardian sharing, attachments, recurring medication schedules, and customizable widgets are deferred.

## Historical records

Calendar dates remain dates; instants use UTC and retain the relevant IANA timezone. Age is derived from birth date.

Medication instructions are editable and audited. Administration entries capture an encrypted snapshot of those instructions, are immutable, and accept corrections as appended revisions. A unique predecessor prevents branching; a client request UUID prevents duplicate submissions. An unknown actual dose stays unknown.

## Authentication

On 2026-11-01 the user approved direct signup without invitation codes. Registration creates one owner and one private family transactionally; email verification and existing authorization, CSRF and validation remain required. Invitation tables/operator tooling remain dormant and are not checked during signup. No free/paid tiers are implemented. Hosted rollout still requires the release gates.

Sanctum uses browser session cookies. Fortify endpoints live under `/auth` so frontend pages such as `/login` and `/reset-password` do not collide with proxy routes. Vercel and the Angular development server proxy `/auth`, `/sanctum`, and `/api` to Laravel. Cookies are host-only and production cookies are Secure and HttpOnly. XSRF-TOKEN is deliberately readable by Angular; it is not the session credential.

Local Angular proxy preserves the original Host (`changeOrigin:false`) so absolute email-verification signatures generated for localhost:4300 remain valid at Laravel. Hosted proxies must likewise preserve or correctly reconstruct the public origin through a trusted configuration; signature validation stays enabled. Account-entry requests establish guest state first; logout's 401 is an already-signed-out result, while other failures stop the operation.

## Portfolio demo (shared database approved 2026-11-04)

Personal and demo accounts use one public address, one Laravel backend and the same application PostgreSQL database. This supersedes the separate-database decision of 2026-11-03. `DEMO_MODE` now enables an optional guest feature alongside normal accounts; it defaults to false. No `DEMO_DATABASE_NAME`, empty-database marker, separate encryption key or separate session cookie is required. The old `demo_installations` table is dormant; `tracker:demo-init` is a read-only readiness check. Signup capacity uses a PostgreSQL transaction advisory lock.

A `demo_accounts` row identifies a demo user and stores its fixed 24-hour expiry, revocation and quotas. API `is_demo` is derived from that relationship. Family ownership enforces isolation across every account type, including dashboard, nested records, intake corrections, notifications, writes and exports. Only active demos bypass email verification; normal verification remains mandatory. Both types use the existing Sanctum session/CSRF flow, and login never extends demo expiry.

`/login` offers normal sign-in, signup, password recovery and `/demo` on the same host and port. Demo email/recovery/reminders remain blocked per account; enabling demos does not suppress normal verification, recovery or reminder delivery. Demo self-deletion remains blocked to retain visitor history. Switching accounts signs out the current session before account entry. The old demo database is not automatically imported, deleted or reset.

Signup is limited to three attempts per IP per hour, 100 attempts globally per day, 30 successful accounts per UTC day and 100 retained accounts total. Demo API limits are 60 requests/minute, writes 20/minute and 300/day per account. Each demo permits three child profiles (including archived profiles) and 100 added records including intake revisions; an idempotent intake replay consumes no extra quota. Demo notes are limited to 1,000 characters. Quota updates are transactional and serialized per demo account.

`demo_access_events` retains up to 200 events per account without passwords, tokens, IP addresses or request payloads. The account's last-login and expiry/revocation fields remain authoritative after that event cap. Operator reports and revocation are Artisan commands (`tracker:demo-report --events`, `tracker:demo-revoke USER_ID`), protected by server access; there is no visitor-history HTTP endpoint or browser admin role. The commands do not expose family records. Expiration does not purge any data.

## Persistence

ULIDs identify business entities. PostgreSQL tables live in the non-exposed `app` schema. Runtime credentials have data privileges, while migration credentials own schema changes. Laravel migrations are the source of truth. PostgreSQL backs sessions, cache locks and rate limiting.

## Scheduling

Durable delivery rows are the outbox. Supabase Cron and pg_net make authenticated HTTP calls only when due work exists. Laravel alone processes business rules. Repeated calls are safe because of database locks, unique delivery keys, row claims and expiring leases. Work is bounded to twenty seconds. Deployment requires a tested timeout that accommodates Render cold starts.

## Release stages

Local development → synthetic-data staging → privacy and operational readiness → supervised non-commercial pilot. Free-tier availability and backup operation are measured, not guaranteed. Never point unreviewed previews at a live family database.
