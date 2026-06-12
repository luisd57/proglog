1. Ask, don't assume. If something is unclear, ask before writing a single line. Never make silent assumptions about intent, architecture, or requirements.

2. Simplest solution first. Always implement the simplest thing that could work. Do not add abstractions or flexibility that weren't explicitly requested.

3. Don't touch unrelated code. If a file or function is not directly part of the current task, do not modify it, even if you think it could be improved.

4. Flag uncertainty explicitly. If you are not confident about an approach or technical detail, say so before proceeding. Confidence without certainty causes more damage than admitting a gap.

# ProgLog

Single-user strength training tracker (personal StrengthLog replacement, no auth, no monetization). Workout templates ("splits"), session logging with rest timer and warm-up sets, progressive overload stats (e1RM/top set/volume/PRs), strength-level ratings from standards tables, body measurements, and muscle highlighting (primary/secondary) on an SVG body diagram. Units: kg only. iOS path is a PWA over LAN/Tailscale — no native build.

Full design + phase plan: `C:\Users\luisr\.claude\plans\i-use-the-ios-wobbly-sprout.md`

## Workflow Rules

When the user says "/done" or indicates a feature/milestone is complete:
1. Update the "Implementation Status" section at the bottom of this file
2. If the work revealed reusable patterns or gotchas, suggest updating auto-memory (but ask first)
3. Do NOT update .claude/rules/ files unless explicitly asked

Development is TDD: nontrivial logic (stats, PR detection, strength levels) is written test-first with Jest in `api/`. All tooling runs in Docker — never run npm/node on the host.

## Project Structure

- `api/` — NestJS 11 + Prisma 6 + SQLite. Modules under `src/modules/`. Schema: `api/prisma/schema.prisma`. Jest unit tests (`*.spec.ts` next to source) + e2e in `api/test/`.
- `web/` — Angular 22 (standalone components, signals). Vitest for unit tests. Dev server proxies `/api` → api container (`proxy.conf.json`).
- `Dockerfile` — multi-stage prod build: Angular build + Nest build → one `node:24-alpine` container; Nest serves the SPA from `/app/public` and the API under `/api`.
- `data/` — SQLite database (gitignored). Backup = copy `data/proglog.db`.

## Dev Environment

- Dev: `docker compose -f docker-compose.dev.yml up` → web on :4200 (hot reload via polling), api on :3000.
- Prod: `docker compose up --build` → everything on :8080.
- API tests: `docker compose -f docker-compose.dev.yml run --rm api npm test` (`test:e2e` for e2e).
- Web tests: `docker compose -f docker-compose.dev.yml run --rm web npm test`.
- New migration: `docker compose -f docker-compose.dev.yml run --rm api npx prisma migrate dev --name <name>`.
- After changing package.json: rebuild images (`docker compose -f docker-compose.dev.yml build`) and run `docker compose -f docker-compose.dev.yml down -v` first if node_modules volumes are stale. Regenerate lockfiles with `docker run --rm -v "${PWD}\api:/app" -w /app node:24-alpine npm install --package-lock-only --ignore-scripts`.
- Prisma is pinned to v6 (v7 exists; do not upgrade without asking).

## Domain Terminology

- **Split / workout template**: reusable workout definition (user alternates two). Logging a session against it pre-fills last session's sets.
- **Working set vs warm-up set**: warm-ups are excluded from all stats and PRs.
- **e1RM**: estimated one-rep max, Epley formula `weight × (1 + reps/30)`, working sets only.
- **Strength level**: beginner/novice/intermediate/advanced/elite from hardcoded bodyweight×sex standards tables (squat, bench, deadlift, OHP, row).
- **Muscle highlighting**: primary muscles full color, secondary lighter — shown per exercise, per template (aggregate), and weekly recap.

## Implementation Status

- [x] Phase 1: Scaffold & plumbing — Nest+Angular scaffolds, Prisma schema + init migration, dev compose (hot reload) and prod single-container image both verified, test suites green in Docker.
- [x] Phase 2: Exercise DB seed + muscle diagram — 873 exercises seeded (seed runs via dev compose; prod image has no ts-node), exercises module with filters (TDD, 13 unit + 4 e2e tests), `MuscleDiagram` component with vendored MIT SVG data (`web/src/app/components/muscle-diagram/`), browser/detail/new pages. Note: web tests touching @angular/common/http must run via `ng test`, not raw `vitest` (AOT linker).
- [x] Phase 3: Workout templates — templates module (TDD: 7 unit + 1 e2e), full-replace PUT semantics, `/templates/:id/muscles` aggregate (primary wins over secondary), editor page with search picker + live muscle coverage diagram. Gotcha fixed: tsc watch needs `watchOptions` polling in tsconfig.json for new files to be seen through Windows bind mounts — restart the api container after adding new files if routes 404.
- [x] Phase 4: Session logging — sessions module (TDD: 9 unit tests; start-from-template, previous-set prefill from latest *finished* session, replace-sets, finish, history), stats module begun (`epley1Rm`, `exerciseBest` PR baseline; 6 tests), log page with per-exercise cards (warm-up toggle, debounced saves, live PR badges, notes), `RestTimer` (4 tests, WebAudio beep), history page, Start buttons. Web-test gotcha: after flushing an rxResource request, `await fixture.whenStable()` before reading `.value()`.
- [x] Phase 5: Overload stats — `exerciseSeries` (per-finished-session topSet/volume/e1RM + chronological PR events; TDD, 3 more tests), chart.js (no ng2-charts wrapper — used directly for Angular 22 compat), `LineChart` component, exercise detail page shows 3 progress charts + PR table when history exists.
- [ ] Phase 6: Strength levels
- [ ] Phase 7: Measurements
- [ ] Phase 8: Dashboard, weekly recap, PWA
