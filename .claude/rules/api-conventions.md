---
paths:
  - API/src/**/*.php
---
# API Coding Conventions

## PHP Style
- `declare(strict_types=1);` in every file
- Constructor Property Promotion: always
- Readonly properties for immutable data
- `final` for classes not intended for inheritance (Doctrine entities are NEVER `final` — proxying)
- Always declare return types

## Method Parameters
- Non-primitive parameter names must match their type name (camelCase):
  - ✅ `function login(string $email, UserRole $userRole)`
  - ❌ `function login(string $email, UserRole $expectedRole)`
- Common types use descriptive names: `$from`, `$to`, `$ttlSeconds`
- No single-letter variable names, including in closures

## Collections
- Use `Doctrine\Common\Collections\ArrayCollection` instead of arrays
- Convert to array only at boundaries (API responses)

## Naming Conventions

| Type           | Pattern                    | Example                   |
|----------------|----------------------------|---------------------------|
| Handler        | `{Action}{Entity}Handler`  | `CreateOrderHandler`      |
| Input DTO      | `{Action}{Entity}InputDTO` | `CreateOrderInputDTO`     |
| Output DTO     | `{Entity}OutputDTO`        | `UserOutputDTO`           |
| Interface      | `{Name}Interface`          | `UserRepositoryInterface` |
| Custom DBAL    | `{VO}Type`                 | `EmailType`, `UserIdType` |

## Handlers
- One handler = one file = one public action via `__invoke()`
- `__invoke()` receives a single InputDTO parameter named `$dto` — exception: read-by-identifier use cases take the raw ID; no-arg lists take nothing. Don't create pointless DTOs.
- Controllers receive handlers as action-METHOD arguments; constructors hold only cross-cutting services
- Call explicitly: `$this->handler->__invoke(new FooInputDTO(...))`
  - ❌ `($this->handler)(new FooInputDTO(...))`

## DTOs
- Input DTOs: `DTO/Input/`, suffixed `InputDTO`
- Output DTOs: `DTO/Output/`, suffixed `OutputDTO`
- All DTOs are `final readonly class`
- Output DTOs include static `fromEntity()` factory and `toArray()` method

## Value Objects
- Static factories: `fromString()`, `create()`, `generate()`
- Private constructors, immutable (readonly), self-validating

## Routes
- Class-level `#[Route('/api/<segment>')]`; method-level always explicit `name: 'api_*'` (snake_case) and `methods: [...]`

## Validation (deliberate, do not "fix")
- NO `#[Assert]` attributes on DTOs, NO `#[MapRequestPayload]`. Controllers validate the decoded array via `ValidatesRequestTrait` (422, `details` = field → first message). Value Objects are the real guard.

## Errors (deliberate, do not "fix")
- NO kernel exception listener. Each action catches the specific domain exceptions it can produce.
- `DomainException` base carries a SCREAMING_SNAKE `errorCode` → response `code` field.
- Status map: not found 404 · invalid state transition/conflict 409 · bad credentials/inactive 401 · VO `InvalidArgumentException` 422 · malformed/expired token 400

## API Responses
- Use `ApiResponseTrait` for consistent envelope format
- Pagination: `?page=1&limit=20` (defaults: page=1, limit=20, max 100)
- Wire format is snake_case, code is camelCase; mapping is manual in `toArray()` — no Serializer, no naming strategy
