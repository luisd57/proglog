# ProgLog API Contract (Symfony rewrite)

Source of truth for the PHP backend and the Angular client. Derived from the retired
NestJS services (removed in the same commit that landed this backend; see git history
for `api/src/modules/`) — same paths, behavior ported faithfully unless a deviation is
listed. All endpoints prefixed `/api`.

## Conventions

- **Envelope** (every endpoint except `GET /api/health`):
  - Success: `{"success": true, "data": <payload>}` — payload objects keyed by resource
    (`{"exercise": {...}}`, `{"sessions": [...]}`).
  - Error: `{"success": false, "error": {"code": "SCREAMING_SNAKE", "message": "..."}}`;
    validation errors add `"details": {field: "first message"}` with code `VALIDATION_ERROR`.
- **Wire format**: snake_case everywhere (fields, query params, request bodies).
  Old camelCase names are renamed: `weightKg`→`weight_kg`, `primaryMuscles`→`primary_muscles`,
  `isCustom`→`is_custom`, `excludeSession`→`exclude_session`, etc.
- **IDs**: UUID v7 strings (old API used cuid — all IDs are new after data migration).
  Malformed UUID in a path/body → 422.
- **Dates**: ATOM / RFC 3339 (`2026-08-04T10:00:00+00:00`). Exceptions: `profile.birth_date`
  and `cumulative_volume[].date` are plain `YYYY-MM-DD`.
- **Status map** (kit): not found 404 · conflict/invalid state 409 · validation (incl.
  malformed UUID) 422 · success 200 · created 201 · deleted 204 (empty body, no envelope).
- **Route names**: `api_*` snake_case, listed per endpoint.
- **Error codes**: `VALIDATION_ERROR`, `NOT_FOUND`, `EXERCISE_NOT_FOUND`,
  `DUPLICATE_EXERCISE_NAME`, `BUILT_IN_EXERCISE_IMMUTABLE`, `EXERCISE_IN_USE`,
  `TEMPLATE_NOT_FOUND`, `SESSION_NOT_FOUND`, `SESSION_EXERCISE_NOT_FOUND`,
  `MEASUREMENT_NOT_FOUND`.
- **No auth** of any kind (deliberate: single-user LAN tool).
- **No pagination anywhere** — the old API paginates nothing (the exercises list filters
  but returns the full result set; catalog is ~870 rows). Deliberate.

### Deviations from the NestJS API (all deliberate)

1. Envelope added (old API returned bare payloads).
2. Validation errors are 422 (old: 400). Modifying/deleting a built-in exercise and
   duplicate names are 409 (old: 400/500).
3. `DELETE` endpoints return 204 (old: 200 with empty/`{ok:true}` body).
4. `PUT .../sets` body is wrapped: `{"sets": [...]}` (old: bare array).
5. Referencing a non-existent `exercise_id` in templates/sessions → 404
   `EXERCISE_NOT_FOUND` (old: raw FK error / 500).
6. `GET /api/measurements` requires `type` (old: silently returned all types if absent).
7. Exercise create/update enforces name uniqueness explicitly → 409 (old: DB error 500).

---

## Shared shapes

**Exercise**
```json
{
  "id": "uuid",
  "name": "Barbell Bench Press",
  "primary_muscles": ["chest"],
  "secondary_muscles": ["shoulders", "triceps"],
  "equipment": "barbell" | null,
  "category": "strength" | null,
  "instructions": "..." | null,
  "is_custom": false
}
```

**Set**
```json
{
  "id": "uuid",
  "set_number": 1,
  "weight_kg": 80.0,
  "reps": 8,
  "is_warmup": false,
  "notes": "..." | null
}
```

---

## Health

### GET /api/health — `api_health`
No envelope (probe-friendly). 200 `{"status": "ok", "timestamp": "<ATOM>"}`;
503 `{"status": "unhealthy", ...}` if DB unreachable.

---

## Exercises

### GET /api/exercises — `api_exercises_list`
Query (all optional): `search`, `muscle`, `equipment`.
- `search` is tokenized: lowercase, split on whitespace, strip non-alphanumerics,
  trim trailing `s` from tokens longer than 2 chars. Every token must appear in the
  name (case-insensitive substring, any order) — "chin ups" finds "Chin-Up".
- `muscle`: exact element match against `primary_muscles` OR `secondary_muscles`.
- `equipment`: exact match.
- Results ordered by name ASC; when searching, re-ranked by: exact normalized-name
  match first, then fewest words in name, then all-tokens-match-whole-words (prefix
  of a word counts) over mid-word coincidences ("chin" in "machine"), then shorter
  name; stable sort keeps name ASC for ties.

200: `{"exercises": [Exercise]}`

### GET /api/exercises/{id} — `api_exercises_show`
200: `{"exercise": Exercise}` · 404 `EXERCISE_NOT_FOUND` · 422 malformed UUID

### POST /api/exercises — `api_exercises_create`
Body:
```json
{
  "name": "My Custom Press",            // required, non-blank, <=255
  "primary_muscles": ["shoulders"],     // required, non-empty list of non-empty strings
  "secondary_muscles": ["traps"],       // optional, default []
  "equipment": "machine",               // optional string|null
  "category": "strength",               // optional string|null
  "instructions": "..."                 // optional string|null
}
```
Always creates `is_custom: true`. Name is trimmed.
201: `{"exercise": Exercise}` · 422 validation · 409 `DUPLICATE_EXERCISE_NAME`

### PATCH /api/exercises/{id} — `api_exercises_update`
Patch semantics: only provided keys are applied; `equipment`/`category`/`instructions`
accept explicit `null` to clear. Only custom exercises may be updated.
Same field rules as create (if provided).
200: `{"exercise": Exercise}` · 404 · 409 `BUILT_IN_EXERCISE_IMMUTABLE` ·
409 `DUPLICATE_EXERCISE_NAME` · 422

### DELETE /api/exercises/{id} — `api_exercises_delete`
Only custom exercises, and only when unreferenced: an exercise used by any template line
or logged session exercise is refused (the FK is RESTRICT; the handler checks first).
204 · 404 · 409 `BUILT_IN_EXERCISE_IMMUTABLE` · 409 `EXERCISE_IN_USE` · 422

---

## Templates

**TemplateSummary**: `{"id", "name", "sort_order", "exercise_count"}`
**Template**:
```json
{
  "id": "uuid",
  "name": "Push Day",
  "sort_order": 0,
  "exercises": [
    {
      "id": "uuid",                 // template-exercise id
      "sort_order": 0,
      "target_sets": 3 | null,
      "target_reps": 8 | null,
      "rest_seconds": 120 | null,
      "exercise": Exercise
    }
  ]
}
```

### GET /api/templates — `api_templates_list`
Non-archived templates ordered by `sort_order` ASC. 200: `{"templates": [TemplateSummary]}`

### GET /api/templates/{id} — `api_templates_show`
Exercises ordered by `sort_order` ASC. 200: `{"template": Template}` · 404 `TEMPLATE_NOT_FOUND` · 422

### GET /api/templates/{id}/muscles — `api_templates_muscles`
Union of muscles across the template's exercises; muscles in `primary` are removed
from `secondary`.
200: `{"primary": ["chest", ...], "secondary": ["triceps", ...]}` · 404 · 422

### POST /api/templates — `api_templates_create`
```json
{
  "name": "Push Day",                       // required, non-blank
  "exercises": [                            // required, may be empty list
    {"exercise_id": "uuid", "target_sets": 3, "target_reps": 8, "rest_seconds": 120}
  ]
}
```
`target_*`/`rest_seconds` optional (null). `sort_order` of the template = max existing + 1
(0 for the first); exercise `sort_order` = array index.
201: `{"template": Template}` · 422 · 404 `EXERCISE_NOT_FOUND` (unknown `exercise_id`)

### PUT /api/templates/{id} — `api_templates_update`
Same body as create. Full replace: name updated, exercise list deleted and recreated
(existing template-exercise IDs change). `sort_order` of the template unchanged.
200: `{"template": Template}` · 404 · 422

### DELETE /api/templates/{id} — `api_templates_delete`
204 · 404 · 422. Sessions that referenced it keep running: `sessions.template_id`
is set NULL (ON DELETE SET NULL semantics, as in the old schema).

---

## Sessions

**SessionSummary**:
```json
{
  "id": "uuid",
  "template_name": "Push Day" | null,
  "started_at": "<ATOM>",
  "finished_at": "<ATOM>" | null,
  "exercise_count": 5,
  "set_count": 14
}
```

**Session** (detail):
```json
{
  "id": "uuid",
  "template_id": "uuid" | null,
  "template_name": "Push Day" | null,
  "started_at": "<ATOM>",
  "finished_at": "<ATOM>" | null,
  "notes": "..." | null,
  "exercises": [
    {
      "id": "uuid",                  // session-exercise id
      "sort_order": 0,
      "notes": "..." | null,
      "exercise": Exercise,
      "sets": [Set],                 // ordered by set_number ASC
      "target_sets": 3 | null,       // from the session's template entry for this exercise
      "target_reps": 8 | null,
      "rest_seconds": 120,           // template value, else profile.default_rest_seconds (default 120)
      "previous_sets": [Set]         // sets of this exercise in the most recent OTHER finished session, [] if none
    }
  ]
}
```

### POST /api/sessions — `api_sessions_start`
Body: `{"template_id": "uuid"}` or `{}`/absent for a free session. When a template is
given, its exercises are copied as session exercises (sort_order = index), with no sets.
`started_at` = now (server clock via ClockInterface).
201: `{"session": Session}` · 404 `TEMPLATE_NOT_FOUND` · 422

### GET /api/sessions — `api_sessions_list`
All sessions (finished or not) ordered by `started_at` DESC. 200: `{"sessions": [SessionSummary]}`

### GET /api/sessions/{id} — `api_sessions_show`
200: `{"session": Session}` · 404 `SESSION_NOT_FOUND` · 422

### PUT /api/sessions/{id}/exercises/{session_exercise_id}/sets — `api_sessions_replace_sets`
Replaces ALL sets of the session exercise:
```json
{"sets": [{"weight_kg": 80, "reps": 8, "is_warmup": false, "notes": null}]}
```
`is_warmup` defaults false, `notes` defaults null; `set_number` assigned from array
index (1-based). Empty list allowed (clears sets). `weight_kg` numeric >= 0, `reps` int >= 0.
200: `{"success": true, "data": null}` · 404 `SESSION_EXERCISE_NOT_FOUND` (also when the
session exercise belongs to another session) · 422

### POST /api/sessions/{id}/exercises — `api_sessions_add_exercise`
Body: `{"exercise_id": "uuid"}`. Appended with next `sort_order`.
200: `{"session": Session}` · 404 `SESSION_NOT_FOUND` / `EXERCISE_NOT_FOUND` · 422

### DELETE /api/sessions/{id}/exercises/{session_exercise_id} — `api_sessions_remove_exercise`
Deletes the session exercise and its sets. 200: `{"session": Session}` · 404 · 422

### PATCH /api/sessions/{id} — `api_sessions_update_notes`
Body: `{"notes": "..."}` (required string, may be empty).
200: `{"success": true, "data": null}` · 404 · 422

### PATCH /api/sessions/{id}/exercises/{session_exercise_id} — `api_sessions_update_exercise_notes`
Body: `{"notes": "..."}`. 200: `{"success": true, "data": null}` · 404 · 422

### POST /api/sessions/{id}/finish — `api_sessions_finish`
Sets `finished_at` = now (idempotent overwrite, like the old API).
200: `{"session": Session}` · 404 · 422

### DELETE /api/sessions/{id} — `api_sessions_delete`
Cascades to session exercises and sets. 204 · 404 · 422

---

## Measurements

Valid `type` values (exactly, camelCase L/R suffixes preserved as-is on the wire):
`weight`, `bodyfat`, `neck`, `shoulders`, `chest`, `waist`, `hips`, `bicepL`, `bicepR`,
`forearmL`, `forearmR`, `thighL`, `thighR`, `calfL`, `calfR`.

**Measurement**: `{"id": "uuid", "type": "weight", "value": 82.5, "measured_at": "<ATOM>"}`

### GET /api/measurements?type=weight — `api_measurements_series`
Full series for one type, ordered `measured_at` ASC.
200: `{"measurements": [Measurement]}` · 422 missing/unknown `type`

### GET /api/measurements/latest — `api_measurements_latest`
Latest value per type (types never measured are absent).
200: `{"latest": {"weight": 82.5, "waist": 78.0}}`

### POST /api/measurements — `api_measurements_create`
Body: `{"type": "weight", "value": 82.5, "measured_at": "<ATOM>"}`; `measured_at`
optional (default now). `value` must be > 0.
201: `{"measurement": Measurement}` · 422

### DELETE /api/measurements/{id} — `api_measurements_delete`
204 · 404 `MEASUREMENT_NOT_FOUND` · 422

---

## Profile

Singleton (single user). **Profile**:
```json
{
  "sex": "male" | "female" | null,
  "birth_date": "1995-04-12" | null,
  "default_rest_seconds": 120,
  "height_cm": 178.0 | null
}
```
(The internal singleton row id is not exposed.)

### GET /api/profile — `api_profile_show`
Creates the default row on first access. 200: `{"profile": Profile}`

### PATCH /api/profile — `api_profile_update`
Patch semantics; `sex`, `birth_date`, `height_cm` accept explicit null to clear.
`sex` must be `male`/`female`/null; `default_rest_seconds` int > 0.
200: `{"profile": Profile}` · 422

---

## Stats

All stats consider **only finished sessions** (`finished_at` not null) and **exclude
warmup sets** (`is_warmup: true`). e1RM uses the Epley formula:
`e1rm = weight_kg * (1 + reps / 30)` (0 when reps <= 0). Port `e1rm.ts` and
`strength-standards.ts` (thresholds tables + interpolation + level walk) verbatim.

### GET /api/stats/exercise/{id}/best — `api_stats_exercise_best`
Query: `exclude_session` (optional session UUID — used to exclude the in-progress
session when showing PRs during a workout).
Best across all qualifying sets of the exercise. Unknown exercise id → 200 with nulls
(no existence check, as in the old API).
```json
{"best_weight_kg": 100.0 | null, "best_e1rm": 113.3 | null}
```
200 · 422 malformed UUID

### GET /api/stats/exercise/{id}/series — `api_stats_exercise_series`
One point per finished session containing the exercise (sessions ordered `started_at`
ASC; sessions where the exercise has 0 non-warmup sets are skipped). Top set = set with
highest e1rm (first set wins ties). Volume = sum of `weight_kg * reps` over the
session's sets for this exercise. PR events: emitted when the session's best set weight
exceeds all previous session-best weights OR the session's top e1rm exceeds all previous
top e1rms (first qualifying session always emits one).
```json
{
  "points": [
    {
      "session_id": "uuid",
      "date": "<ATOM>",                       // session started_at
      "top_set": {"weight_kg": 100.0, "reps": 5},
      "volume": 1500.0,
      "e1rm": 116.7
    }
  ],
  "prs": [
    {"date": "<ATOM>", "weight_kg": 100.0, "reps": 5, "e1rm": 116.7}
  ]
}
```
200 · 422 malformed UUID

### GET /api/stats/strength-levels — `api_stats_strength_levels`
Requires a bodyweight measurement and profile sex.
- No `weight` measurement → `{"ready": false, "reason": "no-bodyweight", "levels": []}`
- No profile sex → `{"ready": false, "reason": "no-profile", "levels": []}`
- Otherwise, for each of the 5 standards (squat, bench, deadlift, ohp, row): find the
  first seeded exercise matching the standard's `exerciseNames` list, compute its best
  e1rm, interpolate thresholds for the latest bodyweight (values rounded to 1 decimal
  during interpolation; clamped to the min/max table row), and walk levels.
```json
{
  "ready": true,
  "bodyweight_kg": 82.5,
  "levels": [
    {
      "lift": "squat",
      "label": "Squat",
      "exercise_id": "uuid" | null,          // null if the seeded exercise is missing
      "e1rm": 120.5 | null,                  // null if never performed
      "level": "intermediate" | "untrained" | null,   // null when e1rm is null
      "next_level": "advanced" | null,       // null at elite or when e1rm is null
      "progress": 0.42 | null,               // 0..1 toward next level; 1 at elite
      "thresholds": [66.0, 89.0, 115.0, 145.0, 177.0]  // beginner..elite, always present
    }
  ]
}
```
Levels: `untrained` (below beginner), `beginner`, `novice`, `intermediate`, `advanced`,
`elite`. 200 always.

### GET /api/stats/weekly-muscles — `api_stats_weekly_muscles`
Finished sessions started within the last 7 days (rolling window from now); only session
exercises with at least one non-warmup set count. Muscles unioned; `primary` wins over
`secondary`. `session_count` = distinct qualifying sessions.
```json
{"primary": ["chest"], "secondary": ["triceps"], "session_count": 3}
```
200

### GET /api/stats/overview?period=7d — `api_stats_overview`
`period` ∈ `7d`, `30d`, `90d`, `365d`, `all`; anything else silently falls back to `7d`.
Current window = last N days from now; previous window = the N days before that
(null for `all`). Totals over finished sessions started in the window:
```json
{
  "period": "7d",
  "current": {
    "workouts": 4,
    "volume_kg": 12500.0,
    "reps": 320,
    "sets": 48,
    "heaviest_kg": 120.0,
    "time_seconds": 14400
  },
  "previous": { ...same shape... } | null,
  "cumulative_volume": [
    {"date": "2026-07-28", "value": 3200.0}
  ]
}
```
`time_seconds` = sum of `finished_at - started_at` (clamped >= 0).
`cumulative_volume`: volume bucketed by server-local calendar day, one running-sum
point per day from the window start (for `all`: from the first session's day, today
only if no sessions) through today inclusive.
200

---

## Implementation notes for the porting agents

- Domain layout, envelope, error mapping and repository/handler patterns: follow the
  Exercise slice in `API/src/` (reference implementation).
- Entities reference other aggregates by ID value objects only (TemplateExercise →
  ExerciseId, SessionExercise → SessionId + ExerciseId, SetLog → SessionExerciseId);
  no Doctrine relations. That is an ORM-mapping choice, not a schema one: the migrations
  DO declare foreign keys, and the repositories/handlers still perform the cascade in PHP
  (keeping the identity map consistent) with the constraint as the database-level backstop.
  - `template_exercises.template_id` → `workout_templates` CASCADE
  - `session_exercises.session_id` → `sessions` CASCADE
  - `set_logs.session_exercise_id` → `session_exercises` CASCADE
  - `sessions.template_id` → `workout_templates` SET NULL (orchestrated in
    `DeleteTemplateHandler`)
  - `template_exercises.exercise_id` / `session_exercises.exercise_id` → `exercises`
    RESTRICT; `DeleteExerciseHandler` guards this first and returns 409 `EXERCISE_IN_USE`.
- Time: inject `Symfony\Component\Clock\ClockInterface` into handlers
  (`started_at`, `finished_at`, `measured_at`, stats windows); entities receive `$now`.
- `weight_kg`, `value`, `height_cm` are floats (DOUBLE PRECISION); `reps`, counts and
  seconds are ints.
