# Dev Gotchas

Hard-won quirks that aren't obvious from the code. Terse on purpose.

## Docker / build

- Prisma is pinned to v6 (v7 exists; do not upgrade without asking).
- After a Prisma schema change, regenerate the client in the *running* dev api container (`docker compose -f docker-compose.dev.yml exec api npx prisma generate`, then restart) — `run --rm` migrations don't touch the up container's node_modules volume.
- tsc watch needs `watchOptions` polling in tsconfig.json for new files to be seen through Windows bind mounts — restart the api container after adding new files if routes 404.

## Testing

- Web tests touching `@angular/common/http` must run via `ng test`, not raw vitest (AOT linker).
- After flushing an rxResource request, `await fixture.whenStable()` before reading `.value()`.

## Angular

- chart.js is used directly (no ng2-charts wrapper) for Angular 22 compat; `LineChart` is the only chart component (no bar chart yet).
- `rxResource.value()` collapses to `undefined` mid-fetch — hold the last result in a `linkedSignal` and dim on `isLoading()` instead of `@if`-gating on `value()`, or the view unmounts on every param change.
- Date inputs must commit on `(change)`, never `(input)` — partial dates report `value=""` and the `[value]` write-back wipes the user's typing.

## Feature notes

- Exercise search (`ExercisesService.list`) is tokenized then ranked. Tokenize: query split into words, each lightly de-pluralized, every token must appear as a substring of the name (ANDed, any order). Rank (`rankBySearch`): exact-name match > fewest words > whole-word matches over mid-word coincidences > shorter name; relies on a stable sort over alphabetically-ordered rows. No-search listing stays alphabetical.
- Measurements entry is one form for all 15 fixed types (`GET /api/measurements/latest` feeds placeholders; only filled fields POST).
- Body-fat estimate = US Navy method in `web/src/app/utils/body-fat.ts` (needs sex + `Profile.heightCm`, neck + waist, and hips for women).
- Volume/Heaviest stats read 0 for bodyweight sets (`weightKg = 0`) until bodyweight loading lands.
