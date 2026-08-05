# Implementation Status

- **Symfony API (`API/`)**: all 6 domains ported (exercises, templates, sessions, measurements, profile, stats). Booted, migrated, seeded (873 exercises); 414/414 PHPUnit green (2026-08-05); endpoints smoke-tested against the contract (2026-08-04).
- **Web**: restructured to domain layout, adapted to the new contract. `ng build` clean, 54/54 Vitest passing. Dev proxy wired (`serve.options.proxyConfig`).
- **Data migration**: executed against Postgres — 1 template / 3 lines / 2 sessions / 11 session exercises / 31 sets / 6 measurements. Verified in the browser: history, session detail, measurements, strength, workouts and dashboard all render the imported data, no console errors.
- **NestJS backend**: removed (2026-08-04).
- **Legacy `data/proglog.db`**: deleted (2026-08-04) — history lives on in `API/data/legacy-import.sql`.
- **Known gap**: volume/heaviest stats read 0 until bodyweight loading lands (all legacy sets are bodyweight).
- **Prod packaging**: the single-container build was NestJS-specific and was deleted. The dev stack already serves the PWA over LAN, so rebuild it only if you want a prod image.
