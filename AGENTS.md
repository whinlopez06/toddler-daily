# Working on Toddler Daily

Before changing code, read:

1. [Approved plan](docs/PROJECT_PLAN.md)
2. [Current agent handoff](docs/HANDOFF.md)
3. [Architecture decisions](docs/architecture.md)

The user has authorized implementation. Continue in small, verifiable milestones and update the handoff with completed checks and remaining work. Do not confuse implemented source with production readiness.

Preserve Angular + Laravel + PostgreSQL, one owner per family, server-side family isolation, immutable medication intake revisions, and the approved free-tier hosting boundaries. Explain material changes before replacing established architecture.

Never expose environment secrets, regenerate an existing encryption key casually, run tests against family data, connect previews to the pilot database, or deploy migrations automatically on web-service startup. Backend integration tests must use the dedicated PostgreSQL test database.

Read the machine-specific startup instructions in the handoff before starting local services. Do not launch Docker Desktop through this agent shell; the user should open it from Windows Start. Do not reset Docker or remove its data as an automatic repair.

No purchases or paid upgrades are authorized. Hosted deployment and external-family onboarding still have the readiness gates listed in the plan.
