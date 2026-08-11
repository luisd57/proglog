---
paths:
  - web/src/**/*.ts
  - web/src/**/*.html
  - web/src/**/*.scss
---
# Angular Structure

## Domain-Based Folder Structure

Organize `src/app/` by domain, NOT by technical type. NEVER create top-level `components/`, `services/`, `directives/`, `pipes/`, or `models/` folders.

```
src/app/<domain>/
├── feature/        # smart/routed page components
├── ui/             # dumb/presentational components
├── data-access/    # services (API), stores (state)
└── utils/          # pure helpers, types, constants
```

Only create sub-folders you actually need. The `shared/` folder holds code reused across domains.

## Dependency Flow (STRICT)

```
feature  →  data-access  →  utils
   ↓
   ui     →  utils
```

- Cross-domain imports are FORBIDDEN. Shared needs go in `shared/`.
- `ui/` MUST NOT import from `feature/` or `data-access/`.
- `data-access/` MUST NOT import from `feature/` or `ui/`.

## Growing Domains
- Nesting is for routes (`:id/detail`), NOT folders. All pages are siblings under `feature/`.
- If a sub-feature grows complex enough to need its own `ui/` or `data-access/`, promote it to a top-level domain with full `feature/`, `ui/`, `data-access/`, `utils/`.
- Do NOT nest domains inside domains. Either stay flat or promote.

## Naming Conventions

| Type                     | File Pattern               | Export Pattern       |
|--------------------------|----------------------------|----------------------|
| Routed page              | `<n>.page.ts`              | `<N>Page`            |
| Presentational component | `<n>.component.ts`         | `<N>Component`       |
| Service                  | `<n>.service.ts`           | `<N>Service`         |
| Store                    | `<n>.store.ts`             | `<N>Store`           |
| Shell routes             | `<domain>-shell.routes.ts` | `<domain>Routes`     |
| Model / interface        | `<n>.model.ts`             | `<N>`                |

Kebab-case for all files/folders. One component/service/store per file. No `index.ts` barrel files.

## Smart Components (feature/)
- Standalone, no NgModules
- Inject services/stores via `inject()`
- Pass data to dumb components via signal `input()`, receive events via `output()`
- Minimal template logic - delegate display to `ui/` components

## Dumb Components (ui/)
- Standalone, receive data via signal `input()`, emit via `output()`
- MUST NOT inject services, stores, Router, ActivatedRoute, or any app-level dependency

## Shell Routes
- Every domain with multiple routes has a `<domain>-shell.routes.ts` in `feature/`
- Contains ONLY route definitions - no components, no services
- Use `loadComponent` for individual pages, `loadChildren` for domain route sets

## Data-Access
- Services: HTTP/API interaction (`<domain>.service.ts`)
- Stores (`<domain>.store.ts`) are OPTIONAL - most domains need only a service. Add a store only for state shared across pages. Don't scaffold one by default.
- Only smart components (feature/) may inject from data-access/
- Envelope unwrapping is a per-service `unwrap<T>()`; `ApiResponse<T>` lives in `shared/utils/api-response.model.ts`. No auth interceptor - this app has no auth.
- Consume API snake_case field names as-is - no camelCase mapping layer
