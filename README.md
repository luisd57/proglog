# ProgLog

Personal strength training tracker — workout splits, session logging, progressive overload stats, strength levels, and body measurements. Single user, self-hosted, everything in Docker.

Symfony 8 (PHP 8.4, hexagonal) + Postgres 16 + Angular 22. No auth by design: it runs on a private network.

## Run

```sh
make build up                 # nginx :8080 (API), Angular dev server :4200, Postgres
make composer c=install       # first time only — creates API/vendor/
make db-create db-migrate
make seed                     # 873-exercise catalog, idempotent
```

## Tests

```sh
make test-db-setup            # first time only
make test                     # PHPUnit — also test-unit / test-integration
docker compose run --rm web npm test    # Angular (Vitest)
```

## Migrating data from the old SQLite backend

The retired NestJS/Prisma backend stored everything in `data/proglog.db`. After
`db-migrate` and `seed`:

```sh
python3 API/bin/generate-legacy-import.py    # regenerate API/data/legacy-import.sql
docker compose exec -T postgres psql -U proglog_user -d proglog_db < API/data/legacy-import.sql
```

Exercise IDs change (cuid → UUID v7), so references re-resolve by name against the seeded
catalog. The script refuses to run against a non-empty database and verifies row counts
inside the transaction.

## Phone

Open the app's URL in Safari (same LAN, or via Tailscale from anywhere) → Share → Add to Home Screen.
