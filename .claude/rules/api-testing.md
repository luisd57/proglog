---
paths:
  - API/tests/**/*.php
---
# API Testing Conventions

## Test Infrastructure
- **DomainTestHelper**: Factory methods for domain objects in controlled states. Use instead of calling constructors directly.
- **IntegrationTestCase**: Extends KernelTestCase with automatic transaction wrapping. Use for repository tests.
- **ApiTestCase**: Extends WebTestCase with transaction isolation and `jsonRequest()`. Use for controller tests. No auth helpers — this project has no auth.

## Layout & Naming
- Suites: `Unit` and `Integration` ONLY — no "Functional". Controller/HTTP tests live under `tests/Integration/`, mirroring `src/` path-for-path.
- Unit tests: plain `TestCase` (not KernelTestCase), `createMock()`, properties typed `Interface&MockObject`. Classes `final`; methods `test<Scenario><ExpectedOutcome>`.
- Date-dependent controller tests: `freezeClock()` (ApiTestCase) swaps the container clock for a `MockClock`.

## Key Rules
- All integration tests run in transactions that rollback in `tearDown()` — no data persists.
- Kernel reboot disabled in API tests for transaction isolation across multiple HTTP requests.
- `reconstitute()` is for test helpers ONLY — never in handlers or controllers.

## Running Tests
```bash
make test              # all
make test-unit         # unit only (no DB)
make test-integration
```
