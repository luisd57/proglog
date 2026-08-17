Delegate to a subagent only for large, genuinely independent investigations. Don't delegate work
you can finish in a handful of tool calls, and don't use subagents to double-check your own work.

## Process Skills

All from the **mattpocock-skills** plugin. Invoke them; do NOT restate their guidance here.

| Situation | Skill |
|---|---|
| Idea too big for one session, route unclear | `wayfinder` |
| Plan or decision that needs stress-testing | `grilling` |
| Conversation has settled, needs writing up | `to-spec`, then `to-tickets` |
| Question dialogue can't answer | `prototype` |
| Implementing anything | `tdd` |
| Something broken, throwing, or slow | `diagnosing-bugs` |
| Before opening a PR | `code-review` |
| Terminology or an ADR | `domain-modeling` |

`to-spec`, `to-tickets` and `wayfinder` are user-invocable only. Ask for them rather than writing
a spec or ticket by hand.

Keep this file and `.claude/rules/` focused on project facts the plugin doesn't cover.

# ProgLog

Single-user strength training tracker (personal StrengthLog replacement). Workout templates ("splits"), session logging with rest timer and warm-up sets, progressive overload stats (e1RM/top set/volume/PRs), strength-level ratings from standards tables, body measurements, and muscle highlighting on an SVG body diagram. Units: kg only. iOS path is a PWA over LAN/Tailscale - no native build.

## Project Structure

- `API/` - Symfony 8 / PHP 8.4 backend, hexagonal (Domain / Application / Infrastructure), Postgres 16 via Doctrine.
- `web/` - Angular 22, domain-based layout (`<domain>/{feature,ui,data-access,utils}` + `shared/`). Vitest.
- `docs/api-contract.md` - the API contract. Source of truth for both backend and client.
- `API/data/legacy-import.sql` - the migrated training history from the retired NestJS backend. Already imported; kept as the only surviving copy (the source `data/proglog.db` was deleted after verification).

Timezone: `Europe/Paris` (`API/docker/php/php.ini`). Timestamps are stored as local wall-clock (`TIMESTAMP WITHOUT TIME ZONE`), which is what the stats day-bucketing expects. Change both the php.ini value and `APP_TZ` in the import script together if you move.

## Deliberate Deviations (do not "fix")

- **No auth of any kind** - no JWT, users, security bundle, rate limiting, Redis. Single-user tool on a private network; a login screen is pure friction. The reusable kit's `api-security.md` deliberately does not apply and is not present in `.claude/rules/`.
- No mailer/MailHog, no cron container, no CORS bundle (dev proxies `/api`, prod is same-origin).
- No pagination anywhere - the exercise catalog is ~870 rows and everything else is tiny.
- No Doctrine relation attributes; aggregates reference each other by ID value objects (see `.claude/rules/api-architecture.md`).

## Domain Terminology

- **Split / workout template**: reusable workout definition. Logging a session against it pre-fills last session's sets.
- **Working set vs warm-up set**: warm-ups are excluded from all stats and PRs.
- **e1RM**: estimated one-rep max, Epley `weight × (1 + reps/30)`, working sets only.
- **Strength level**: beginner/novice/intermediate/advanced/elite from hardcoded bodyweight×sex standards (squat, bench, deadlift, OHP, row).
- **Muscle highlighting**: primary muscles full color, secondary lighter.

## Dev Environment

All tooling runs in Docker - never run npm/node/php on the host.

```bash
make build up            # php, nginx (:8080), postgres, web (:4200)
make composer c=install
make db-create db-migrate
make seed                # app:seed-exercises (idempotent)
make test                # PHPUnit; test-unit / test-integration
make shell               # php container
```

Web tests: `docker compose run --rm web npm test`.

The check that proves the tree is green: `make test` plus the web Vitest run.

First run on a fresh clone must be `make composer c=install` - until `API/vendor/` exists, every Symfony/Doctrine symbol shows as undefined in the editor.

## Data Migration (done)

Ran 2026-08-04. To replay on a fresh database, after `db-migrate` + `seed`:

```bash
docker compose exec -T postgres \
  psql -U proglog_user -d proglog_db < API/data/legacy-import.sql
```

Exercise IDs changed (cuid → UUID v7), so references re-resolve by name against the seeded catalog. The SQL refuses to run against a non-empty database and verifies row counts inside the transaction. `API/bin/generate-legacy-import.py` regenerated it from `data/proglog.db`, which no longer exists - the SQL is now the only copy.

## Adding Project-Specific Rules

Stack/architecture conventions go in `.claude/rules/*.md`. Add `paths:` frontmatter to scope a rule to matching files (lazy-loaded); omit it for always-on rules. Repeatable multi-step workflows go in `.claude/skills/` instead. Follow `.claude/rules/documentation-style.md`.

## Status

Current per-component status: `docs/STATUS.md`. Read it when the state of an unfinished
component matters; the `/done` skill updates it.
