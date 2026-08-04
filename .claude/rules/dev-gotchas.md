# Dev Gotchas

Hard-won quirks that aren't obvious from the code. Terse on purpose.

## Docker / build

- `API/vendor/` is not in git. Until `make composer c=install` has run, every Symfony/Doctrine symbol shows as an undefined type/method in the editor — that is expected, not a broken port.
- Postgres timestamps are `TIMESTAMP WITHOUT TIME ZONE` and the container runs `Europe/Paris`. Stats day-bucketing depends on it; `date.timezone` in `API/docker/php/php.ini` and `APP_TZ` in `API/bin/generate-legacy-import.py` must stay in sync.
- `.gitignore` uses `/data/` (root-anchored) so `API/data/` — the 873-exercise seed catalog — stays tracked. An unanchored `data/` silently excludes it and breaks seeding on a fresh clone.

## Testing

- Web tests touching `@angular/common/http` must run via `ng test`, not raw vitest (AOT linker).
- After flushing an rxResource request, `await fixture.whenStable()` before reading `.value()`.
- `ApiTestCase::freezeClock()` must be called BEFORE the request that resolves the clock-using handler, or the frozen time is ignored.

## Angular

- chart.js is used directly (no ng2-charts wrapper) for Angular 22 compat; `LineChart` is the only chart component (no bar chart yet).
- `rxResource.value()` collapses to `undefined` mid-fetch — hold the last result in a `linkedSignal` and dim on `isLoading()` instead of `@if`-gating on `value()`, or the view unmounts on every param change.
- Date inputs must commit on `(change)`, never `(input)` — partial dates report `value=""` and the `[value]` write-back wipes the user's typing.
- `tsconfig.json` has neither `strict` nor `strictTemplates`, so template expressions inside `@for` are unchecked — a stale field name in a loop body compiles silently.

## Feature notes

- Exercise search (`ListExercisesHandler`) is tokenized then ranked. Tokenize: query split into words, each lightly de-pluralized, every token must appear as a substring of the name (ANDed, any order). Rank: exact-name match > fewest words > whole-word matches over mid-word coincidences > shorter name; relies on a stable sort over alphabetically-ordered rows. No-search listing stays alphabetical.
- Muscle filtering happens in PHP after the SQL pass — muscles are JSON array columns.
- Measurements entry is one form for all 15 fixed types (`GET /api/measurements/latest` feeds placeholders; only filled fields POST).
- Body-fat estimate = US Navy method in `web/src/app/measurements/utils/body-fat.ts` (needs sex + `profile.height_cm`, neck + waist, and hips for women).
- Volume/Heaviest stats read 0 for bodyweight sets (`weight_kg = 0`) until bodyweight loading lands.
