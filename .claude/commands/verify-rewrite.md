---
description: Boot the Symfony rewrite for the first time, make the test suite green, then import the legacy data.
---

The Symfony backend in `API/` was written by agents that could never execute PHP. It has
never been run. Your job is to make it actually work, then migrate the old data.

Read `CLAUDE.md`, `.claude/rules/api-*.md` and `docs/api-contract.md` first. The contract
is the source of truth: when code and contract disagree, the contract wins unless the
contract is self-evidently wrong — say so before changing it.

## Phase 1 — boot and green suite

```bash
docker compose build
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:database:create --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed-exercises
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec php vendor/bin/phpunit
```

Work the failures until `phpunit` is green. Expected classes of failure, in likely order:

1. **composer/DI** — missing package, autowiring ambiguity, a service not registered in
   `config/services.yaml` or a DBAL type missing from `config/packages/doctrine.yaml`.
2. **Doctrine metadata** — entity mapping rejected, custom type not applied, embedded VO
   misconfigured. Note the deliberate design: NO relation attributes anywhere; aggregates
   reference each other by ID value objects. Do not "fix" that by adding relations.
3. **Test-level** — the suite was written blind against the contract. If a test and the
   code disagree, decide which is wrong by reading `docs/api-contract.md`, not by making
   the assertion match the code.

`ApiTestCase::freezeClock()` must be called before the request that resolves the clock;
if the frozen time appears ignored, check call ordering before suspecting the helper.

## Phase 2 — smoke test the API

With the stack up, exercise a few endpoints by hand (`curl http://localhost:8080/api/...`):
health, exercises list + search (`?search=chin ups` must find "Chin-Up"), template create,
session create, and one stats endpoint. Confirm the envelope and snake_case field names
match the contract.

## Phase 3 — legacy data import

Only once phases 1-2 pass:

```bash
python3 API/bin/generate-legacy-import.py
docker compose exec -T postgres psql -U proglog_user -d proglog_db < API/data/legacy-import.sql
```

The script refuses to run against a non-empty database and verifies row counts inside the
transaction. Expect 1 template, 3 template lines, 2 sessions, 11 session exercises,
31 sets, 6 measurements. Then start the web app and confirm the imported sessions and
measurements render, and that stats compute over them.

## Constraints

- Do NOT merge to `main`. Work on `rewrite/hexagonal`, commit as you go.
- Do NOT delete `data/proglog.db` — it is the only copy of the training history until the
  import is verified in the UI.
- Follow `.claude/rules/`. Deviations listed in CLAUDE.md under "Deliberate Deviations"
  are intentional; do not undo them.
- Update the Implementation Status section in CLAUDE.md when the suite is green.

Report what was broken and why, grouped by root cause — that tells us which parts of the
reusable kit in `WS/Reusable-Claude` led the agents astray.
