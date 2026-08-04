---
paths:
  - API/src/**/*.php
---
# API Architecture: Hexagonal (Ports & Adapters)

## Layer Structure & Dependency Rule

Infrastructure → Application → Domain (never the reverse)

- `src/Domain/` — core business logic, zero framework dependencies
- `src/Application/` — use cases, orchestration
- `src/Infrastructure/` — external concerns, adapters

## ORM Pragmatism (deliberate, do not "fix")

- ORM attributes (`#[ORM\...]`) live directly on Domain entities. No separate mapping layer (XML/annotations-elsewhere) — we don't plan to swap ORMs.
- NO Doctrine relation attributes (`OneToMany`, `ManyToOne`, `mappedBy`, `inversedBy`). Entities reference other aggregates by ID value objects; repositories resolve them. Never introduce bidirectional mappings.
- This is an ORM-mapping rule, NOT a schema rule. Migrations still declare `FOREIGN KEY` constraints with explicit `ON DELETE CASCADE` / `SET NULL`: referential integrity belongs in the database, and hand-rolled cascades in repositories fail silently when they miss a row.
- VO persistence: single-column VO → custom DBAL type extending the NEAREST base type (e.g. `GuidType`), with `public const string NAME`. Multi-field VO → `#[ORM\Embeddable]` on the VO + `#[ORM\Embedded(columnPrefix: false)]` on the entity, and the `ValueObject/` dir registered as its own doctrine.yaml mapping entry.
- ORM attributes go on promoted constructor params for immutable fields; mutable state is declared as class properties.

## Domain Layer

- Entities: `src/Domain/{Domain}/Entity/` — never read time: factories/mutators take an explicit `DateTimeImmutable $now`
- IDs: `src/Domain/{Domain}/Id/` (UUID v7 via symfony/uid) — separate folder from `ValueObject/`
- Value Objects: immutable, self-validating (IDs, Email, status/state enums, time ranges — anything with validation or identity semantics)
- Repository Interfaces (driven ports): `src/Domain/{Domain}/Repository/`
- Service Interfaces (driven ports): e.g. `EmailSenderInterface`, `JwtTokenGeneratorInterface`, `PasswordHasherInterface`
- Domain Services: pure business computations spanning multiple entities
- Parameter Objects: bundle related inputs to domain services
- Exceptions: domain-specific, per subdomain

## Application Layer

- Handlers: one per use case, `__invoke()` entry point; inject `ClockInterface` when time is needed and pass `$now` down
- DTOs: `DTO/Input/` and `DTO/Output/`
- Application Services: orchestration across handlers/domain services when a single handler isn't enough

## Infrastructure Layer

- `Persistence/Doctrine/Type/` — custom DBAL types for VO↔DB mapping
- `Persistence/Doctrine/Repository/` — repository implementations. `save()` = contains-guard → persist → flush; handlers NEVER call flush or manage transactions (no transaction middleware — accepted trade-off). Unwrap VOs before Doctrine (`$id->getValue()`, `$enum->value`); return `ArrayCollection`
- `Security/` — password hasher, JWT, token revocation (Redis)
- `Email/` — mailer adapter. No Twig/templates dir: senders build html+text via private heredocs, `htmlspecialchars(ENT_QUOTES | ENT_HTML5)` on every interpolated value. Callers try/catch `\Throwable` + log — email failure never fails the use case
- `Http/Controller/` — thin controllers, delegate to handlers
- `Http/EventSubscriber/` — rate limiting, security headers
- `Console/` — CLI commands

## File Patterns

### New Use Case
1. Input DTO in `src/Application/{Domain}/DTO/Input/`
2. Handler in `src/Application/{Domain}/Handler/`
3. Output DTO in `src/Application/{Domain}/DTO/Output/` if needed

### New Entity
1. Entity in `src/Domain/{Domain}/Entity/` with `#[ORM\Entity]`, `#[ORM\Table]`, `#[ORM\Column]`
2. Repository Interface in `src/Domain/{Domain}/Repository/`
3. Custom DBAL Type in `src/Infrastructure/Persistence/Doctrine/Type/`
4. Register type in `config/packages/doctrine.yaml`
5. Repository impl in `src/Infrastructure/Persistence/Doctrine/Repository/`
6. Migration via `php bin/console doctrine:migrations:diff`

### New API Endpoint
1. Route method in controller in `src/Infrastructure/Http/Controller/Api/`
2. Input DTO + Handler if new use case
3. Update `config/packages/security.yaml` if new access rules needed
