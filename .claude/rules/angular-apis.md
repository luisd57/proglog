---
paths:
  - web/src/**/*.ts
  - web/src/**/*.html
  - web/src/**/*.scss
---
# Angular APIs & Style

## Style

Nothing enforces these — `web/` has no ESLint and `tsconfig.json` sets neither `strict` nor
`strictTemplates`. Uphold them by hand.

- Explicit types on every declaration, member and parameter; don't rely on inference at API boundaries
- Template-only members `protected`; exposed signals `readonly`; selector prefix `app`

## Angular 22 Standards
- All components standalone — NO NgModules, no `.module.ts` files
- Signal APIs: `input()`, `output()`, `model()` — NOT `@Input()`, `@Output()`
- `inject()` function — NOT constructor injection
- State: `signal()`, `computed()`, `linkedSignal()`, and `rxResource()` — this app uses `rxResource`, not `httpResource`. Stay consistent; see the `rxResource` gotchas in `dev-gotchas.md`
- Control flow: `@if`, `@for`, `@switch` — NOT `*ngIf`, `*ngFor`, `*ngSwitch`
- Zoneless by default — no Zone.js
- Functional providers in `app.config.ts`: `provideRouter()`, `provideHttpClient()`
- Vitest for testing — NOT Karma/Jasmine
- No forms library in use — not reactive forms, not Signal Forms. Inputs are bound by hand (see the date-input gotcha in `dev-gotchas.md`). Introducing one is a decision to raise, not to make silently
- Prefer `[class]` / `[style]` bindings over `NgClass` / `NgStyle`
