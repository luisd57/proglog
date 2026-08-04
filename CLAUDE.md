1. Ask, don't assume. If something is unclear, ask before writing a single line. Never make silent assumptions about intent, architecture, or requirements.

2. Simplest solution first. Always implement the simplest thing that could work. Do not add abstractions or flexibility that weren't explicitly requested.

3. Don't touch unrelated code. If a file or function is not directly part of the current task, do not modify it, even if you think it could be improved.

4. Flag uncertainty explicitly. If you are not confident about an approach or technical detail, say so before proceeding. Confidence without certainty causes more damage than admitting a gap.

## Process Skills (Superpowers)

Brainstorming, TDD, systematic debugging, verification-before-completion, and code review come from the Superpowers plugin — invoke those skills; do NOT restate their guidance here. Keep this file and `.claude/rules/` focused on project facts and conventions Superpowers doesn't cover.

# ProgLog

Single-user strength training tracker (personal StrengthLog replacement). Workout templates ("splits"), session logging with rest timer and warm-up sets, progressive overload stats (e1RM/top set/volume/PRs), strength-level ratings from standards tables, body measurements, and muscle highlighting on an SVG body diagram. Units: kg only. iOS path is a PWA over LAN/Tailscale — no native build.

## Workflow Rules

When the user says "/done" or indicates a feature/milestone is complete:
1. Update the "Implementation Status" section at the bottom of this file.
2. If the work revealed reusable patterns or gotchas, suggest updating auto-memory (but ask first).
3. Do NOT update `.claude/rules/` files unless explicitly asked.

Memory: keep all auto-memory inline in `MEMORY.md` — one file only, do NOT create per-memory files. Follow `.claude/rules/documentation-style.md` terseness for memory entries too.

## Project Structure

- `API/` — Symfony 8 / PHP 8.4 backend, hexagonal (Domain / Application / Infrastructure), Postgres 16 via Doctrine.
- `web/` — Angular 22, domain-based layout (`<domain>/{feature,ui,data-access,utils}` + `shared/`). Vitest.
- `docs/api-contract.md` — the API contract. Source of truth for both backend and client.
- `data/proglog.db` — legacy SQLite data from the retired NestJS backend (gitignored). Keep until the import has been run and verified; migrate via `API/bin/generate-legacy-import.py`.

Timezone: `Europe/Paris` (`API/docker/php/php.ini`). Timestamps are stored as local wall-clock (`TIMESTAMP WITHOUT TIME ZONE`), which is what the stats day-bucketing expects. Change both the php.ini value and `APP_TZ` in the import script together if you move.

## Deliberate Deviations (do not "fix")

- **No auth of any kind** — no JWT, users, security bundle, rate limiting, Redis. Single-user tool on a private network; a login screen is pure friction. The reusable kit's `api-security.md` deliberately does not apply and is not present in `.claude/rules/`.
- No mailer/MailHog, no cron container, no CORS bundle (dev proxies `/api`, prod is same-origin).
- No pagination anywhere — the exercise catalog is ~870 rows and everything else is tiny.
- No Doctrine relation attributes; aggregates reference each other by ID value objects (see `.claude/rules/api-architecture.md`).

## Domain Terminology

- **Split / workout template**: reusable workout definition. Logging a session against it pre-fills last session's sets.
- **Working set vs warm-up set**: warm-ups are excluded from all stats and PRs.
- **e1RM**: estimated one-rep max, Epley `weight × (1 + reps/30)`, working sets only.
- **Strength level**: beginner/novice/intermediate/advanced/elite from hardcoded bodyweight×sex standards (squat, bench, deadlift, OHP, row).
- **Muscle highlighting**: primary muscles full color, secondary lighter.

## Dev Environment

All tooling runs in Docker — never run npm/node/php on the host.

```bash
make build up            # php, nginx (:8080), postgres, web (:4200)
make composer c=install
make db-create db-migrate
make seed                # app:seed-exercises (idempotent)
make test                # PHPUnit; test-unit / test-integration
make shell               # php container
```

Web tests: `docker compose run --rm web npm test`.

First run on a fresh clone must be `make composer c=install` — until `API/vendor/` exists, every Symfony/Doctrine symbol shows as undefined in the editor.

## Data Migration (one-shot)

```bash
python3 API/bin/generate-legacy-import.py     # regenerate from data/proglog.db
# then, after db-migrate + seed:
docker compose exec -T postgres \
  psql -U proglog_user -d proglog_db < API/data/legacy-import.sql
```

Exercise IDs change (cuid → UUID v7), so references re-resolve by name against the seeded catalog. The script refuses to run twice and verifies row counts inside the transaction.

## Adding Project-Specific Rules

Stack/architecture conventions go in `.claude/rules/*.md`. Add `paths:` frontmatter to scope a rule to matching files (lazy-loaded); omit it for always-on rules. Follow `.claude/rules/documentation-style.md`.

## Implementation Status

### Symfony API (`API/`): all 6 domains ported (exercises, templates, sessions, measurements, profile, stats) — UNVERIFIED, never executed (no PHP in the authoring environment). First run must be `composer install` → `db-migrate` → `test`.
### Web: restructured to domain layout, adapted to the new contract. `ng build` clean, 53/53 Vitest passing.
### Data migration: generated + row-count verified against `data/proglog.db`; not yet executed against Postgres.
### NestJS backend: removed (2026-08-04).
### TODO before merging to main: `make composer c=install` → `db-create db-migrate seed` → `make test`; then run the import and spot-check a session in the UI. Prod packaging (single-container build) was NestJS-specific and was deleted — the dev stack already serves the PWA over LAN, so rebuild it only if you want a prod image.
