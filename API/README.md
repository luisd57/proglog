# ProgLog API

Symfony 8 / PHP 8.4 backend for ProgLog, hexagonal (Domain / Application / Infrastructure).
Postgres 16 via Doctrine. Contract: `../docs/api-contract.md`.

## Deliberate deviations from the kit

- No auth (no JWT, users, security bundle, rate limiting) — single-user LAN tool.
- No Redis, no mailer/MailHog, no cron container, no CORS bundle (Angular dev server
  proxies `/api`; prod is same-origin).

## Run

From the repo root (uses `docker-compose.yml` until cutover):

```bash
make build up
make composer c=install
make db-create db-migrate
make seed            # app:seed-exercises (idempotent)
```

API at http://localhost:8080/api (nginx bound to 0.0.0.0 for LAN/PWA access).

## Tests

```bash
make test-db-setup   # once
make test            # or test-unit / test-integration
```

Suites: `Unit` (no DB) and `Integration` (transaction-rollback isolation via
`tests/Helper/{IntegrationTestCase,ApiTestCase}.php`).

## Structure

- `src/Domain/<X>/{Entity,Id,Repository,Exception}` — pure business logic, no framework
- `src/Application/<X>/{Handler,DTO/Input,DTO/Output}` — one handler per use case
- `src/Infrastructure/{Http,Persistence/Doctrine,Console}` — adapters
- Exercise is the complete reference slice; imitate it for Template/Session/Measurement/Profile/Stats.
