1. Ask, don't assume. If something is unclear, ask before writing a single line. Never make silent assumptions about intent, architecture, or requirements.

2. Simplest solution first. Always implement the simplest thing that could work. Do not add abstractions or flexibility that weren't explicitly requested.

3. Don't touch unrelated code. If a file or function is not directly part of the current task, do not modify it, even if you think it could be improved.

4. Flag uncertainty explicitly. If you are not confident about an approach or technical detail, say so before proceeding. Confidence without certainty causes more damage than admitting a gap.

## Process Skills (Superpowers)

Brainstorming, TDD, systematic debugging, verification-before-completion, and code review come from the Superpowers plugin — invoke those skills; do NOT restate their guidance here. Keep this file and `.claude/rules/` focused on project facts and conventions Superpowers doesn't cover.

# ProgLog

Single-user strength training tracker (personal StrengthLog replacement, no auth, no monetization). Workout templates ("splits"), session logging with rest timer and warm-up sets, progressive overload stats (e1RM/top set/volume/PRs), strength-level ratings from standards tables, body measurements, and muscle highlighting (primary/secondary) on an SVG body diagram. Units: kg only. iOS path is a PWA over LAN/Tailscale — no native build.

Full design + phase plan: `C:\Users\luisr\.claude\plans\i-use-the-ios-wobbly-sprout.md`

## Workflow Rules

When the user says "/done" or indicates a feature/milestone is complete:
1. Update the "Implementation Status" section at the bottom of this file.
2. If the work revealed reusable patterns or gotchas, suggest updating auto-memory (but ask first).
3. Do NOT update `.claude/rules/` files unless explicitly asked.

Memory: keep all auto-memory inline in `MEMORY.md` — one file only, do NOT create per-memory files. Follow `.claude/rules/documentation-style.md` terseness for memory entries too.

## Project Structure

- `api/` — NestJS 11 + Prisma 6 + SQLite. Modules under `src/modules/`. Schema: `api/prisma/schema.prisma`. Jest unit tests (`*.spec.ts` next to source) + e2e in `api/test/`.
- `web/` — Angular 22 (standalone components, signals). Vitest for unit tests. Dev server proxies `/api` → api container (`proxy.conf.json`).
- `Dockerfile` — multi-stage prod build: Angular build + Nest build → one `node:24-alpine` container; Nest serves the SPA from `/app/public` and the API under `/api`.
- `data/` — SQLite database (gitignored). Backup = copy `data/proglog.db`.

## Domain Terminology

- **Split / workout template**: reusable workout definition (user alternates two). Logging a session against it pre-fills last session's sets.
- **Working set vs warm-up set**: warm-ups are excluded from all stats and PRs.
- **e1RM**: estimated one-rep max, Epley formula `weight × (1 + reps/30)`, working sets only.
- **Strength level**: beginner/novice/intermediate/advanced/elite from hardcoded bodyweight×sex standards tables (squat, bench, deadlift, OHP, row).
- **Muscle highlighting**: primary muscles full color, secondary lighter — shown per exercise, per template (aggregate), and weekly recap.

## Dev Environment

All tooling runs in Docker — never run npm/node on the host.

- Dev: `docker compose -f docker-compose.dev.yml up` → web on :4200 (hot reload via polling), api on :3000.
- Prod: `docker compose up --build` → everything on :8080.
- API tests: `docker compose -f docker-compose.dev.yml run --rm api npm test` (`test:e2e` for e2e).
- Web tests: `docker compose -f docker-compose.dev.yml run --rm web npm test`.
- New migration: `docker compose -f docker-compose.dev.yml run --rm api npx prisma migrate dev --name <name>`.
- After changing package.json: rebuild images (`docker compose -f docker-compose.dev.yml build`) and run `docker compose -f docker-compose.dev.yml down -v` first if node_modules volumes are stale. Regenerate lockfiles with `docker run --rm -v "${PWD}\api:/app" -w /app node:24-alpine npm install --package-lock-only --ignore-scripts`.

## Adding Project-Specific Rules

Stack/architecture conventions go in `.claude/rules/*.md`. Add `paths:` frontmatter to scope a rule to matching files (lazy-loaded); omit it for always-on rules. Follow `.claude/rules/documentation-style.md`.

## Implementation Status

- [x] Phase 1: Scaffold & plumbing
- [x] Phase 2: Exercise DB seed + muscle diagram
- [x] Phase 3: Workout templates
- [x] Phase 4: Session logging
- [x] Phase 5: Overload stats
- [x] Phase 6: Strength levels
- [x] Phase 7: Measurements
- [x] Phase 8: Dashboard, weekly recap, PWA
- [x] Dashboard Overview widget (2026-06-15)

All 8 phases complete (2026-06-12). Suites: 69 API unit + 8 API e2e (Jest), 53 web (Vitest).

Deferred TODOs (each its own spec): latest-workouts carousel; Statistics page + nav tab (most-trained muscle groups/exercises bar charts — needs a bar-chart component, only `LineChart` exists); during-workout bodyweight-relative loading (`BW ±kg`, needs an `Exercise` bodyweight flag + `SetLog` loading semantics) and an elapsed session timer. Reference screenshots live in gitignored `FeatureScreenshots/` (StrengthLog feature targets, not docs).
