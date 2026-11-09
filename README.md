# WeeGrow

A private child growth, health, and life tracker. Angular provides the interface; Laravel owns authentication and business rules; PostgreSQL stores family records.

Start with the [approved project plan](docs/PROJECT_PLAN.md) and [agent handoff](docs/HANDOFF.md) for the scope, current progress, known gaps, and local startup commands. The application is in development and has not been deployed.

## Architecture

- Angular 22, TypeScript, Reactive Forms, Signals, RxJS, Bootstrap.
- Laravel 13, PHP 8.5 deployment target, Sanctum and Fortify.
- PostgreSQL in a private `app` schema, including sessions and locks.
- Vercel frontend → Render API → Supabase database.
- Supabase Cron triggers bounded Laravel maintenance only when work is due. No permanent worker or artificial keep-alive.

See [local setup](docs/local-development.md), [deployment](docs/deployment.md), [architecture](docs/architecture.md), and [backup and recovery](docs/backup-and-recovery.md).

## Development

Requires Node.js 24.15+ (24.22.1 pinned), Docker Desktop with WSL2, and Git. PHP 8.5 and Composer run in the local backend container; PHP 8.4+ is also supported for native development.

```sh
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
cd frontend
npm ci
npm start
```

Open http://localhost:4300. Mailpit is at http://localhost:8025. The API is at http://localhost:8000.

No real family data is seeded. There is no default username or password. See [local accounts](docs/local-accounts.md) to sign up and use the local verification inbox.

## Verification

```sh
docker compose exec backend php artisan test
docker compose exec backend vendor/bin/pint --test
cd frontend
npm test -- --watch=false
npm run build
```

Free hosting can sleep, pause, or exhaust its quota. Appointment reminders are best effort. This application does not recommend medication, calculate pediatric doses, or make medical assessments.
