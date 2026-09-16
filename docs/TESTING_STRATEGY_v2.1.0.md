# Testing Strategy

> **Version:** 2.1.0 | **Updated:** 2026-09-15 | **Source:** `phpunit.xml`, `vitest.config.ts`, `.github/workflows/ci.yml`, `tests/`, `database/seeders/StagingSeeder.php`
> **Supersedes:** `TESTING_STRATEGY_v2.0.1.md` (2026-07-27)
> **Delta scope:** structure, patterns, PostgreSQL rationale, and storage-fake conventions from v2.0.1 carry forward. This revision corrects the suite inventory, E2E status, and CI gates.

## 0. What changed in v2.1.0 (delta)

| # | v2.0.1 said | Verified reality (2026-09-15) |
|---|---|---|
| D1 | E2E via `playwright.config.ts`, `npm run test:e2e` | **No `playwright.config.ts`, no `tests/e2e`** — `npx playwright test` / `npm run test:e2e` are **not runnable**. E2E history is the record `docs/E2E_TEST_FINDINGS_v1.0.0.md`, not a suite |
| D2 | ~103 files / ~465 tests, fixed tree | Tree has grown; new suites: `Feature/Audit/AuditLogQueryTest.php`, `Feature/Cache/CacheLockSmokeTest.php`, `Feature/Client/ClientCaseQueryTest.php`, `Feature/Console/ScheduleOverlapTest.php`, `Feature/Console/ToolkitNamespaceTest.php` (+ mail-transport contract test `DeploymentMailEnvContractTest` referenced by `deploy.yml`) |
| D3 | Typecheck mentioned only as build step | **`npm run typecheck` (`tsc --noEmit`) is a BLOCKING CI gate** in `lint-and-audit` (not continue-on-error) |
| D4 | No staging-data coverage | **`StagingSeeder` (6-month deterministic demo dataset)** documented in `docs/STAGING_DATA_v1.0.0.md` — the staging-data story, not a test fixture |
| D5 | `SUPABASE_S3_*` overrides noted | Re-affirmed: `phpunit.xml` sets legacy `SUPABASE_S3_*` fakes; canonical `STORAGE_*` names resolve via the same fallback chain. Storage fakes: `Storage::fake('object-storage')` (also `'private'`, `'public'`, `'audit-archives'`) |

## Overview (re-affirmed)

| Layer | Tool | Test DB | Config |
|-------|------|---------|--------|
| PHP Feature Tests | PHPUnit 12 | PostgreSQL `bayanihan_test` | `phpunit.xml` |
| PHP Unit Tests | PHPUnit 12 | PostgreSQL `bayanihan_test` | `phpunit.xml` |
| Frontend Unit Tests | Vitest 4 + Testing Library | JSDOM (no DB) | `vitest.config.ts` |
| E2E | — (none configured) | — | Record only: `docs/E2E_TEST_FINDINGS_v1.0.0.md` |
| Static gates | Pint · `tsc --noEmit` · Ward | — | `ci.yml` `lint-and-audit` |

## Commands (corrected)

```bash
composer run test              # config:clear, then php artisan test
php artisan test tests/Feature/CaseServiceTest.php
php artisan test --filter test_case_manager_can_create_case
npm run test:run               # Vitest one-shot (CI)
npm test                       # Vitest watch mode (local)
npm run typecheck              # tsc --noEmit — BLOCKING in CI, run before pushing
./vendor/bin/pint --test       # style check
./vendor/bin/pint              # style fix
```

Do **not** document `npx playwright test` as runnable — the config and suite do not exist.

## Test Database Configuration (re-affirmed + pinned)

`phpunit.xml` (PostgreSQL, **not** SQLite):

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="bayanihan_test"/>
<env name="DB_SSLMODE" value="disable"/>
```

Full override table: `APP_ENV=testing` · `CACHE_STORE=array` · `QUEUE_CONNECTION=sync` · `SESSION_DRIVER=array` · `MAIL_MAILER=array` · legacy `SUPABASE_S3_*` fakes (driver `local`, fake key/secret/region/bucket/endpoint) · `BCRYPT_ROUNDS=4` · `LOG_CHANNEL=null` (test-only; silences intentional rejection-path noise) · `MFA_ENROLLMENT_ENFORCEMENT_ENABLED=false` · `PULSE/TELESCOPE/NIGHTWATCH_ENABLED=false` · `memory_limit 512M`.

Prerequisite: database `bayanihan_test` exists, role has DDL/DML. PostgreSQL rationale unchanged: `to_char()` / `EXTRACT()` / `age()`, JSONB + `jsonb_path_ops` GIN, partial unique indexes, CHECK constraints, `pg_trgm`, RLS, append-only `audit_logs` trigger.

## Test Structure (delta to v2.0.1 tree)

v2.0.1 tree carries forward; additions since:

```
tests/Feature/
├── Audit/AuditLogQueryTest.php        # NEW — audit query scopes/filters
├── Cache/CacheLockSmokeTest.php       # NEW — atomic lock behaviour (backs --isolated)
├── Client/ClientCaseQueryTest.php     # NEW — client↔case query paths
├── Console/ScheduleOverlapTest.php    # NEW — scheduler overlap guards
└── Console/ToolkitNamespaceTest.php   # NEW — console tooling namespaces
```

`deploy.yml` additionally references `DeploymentMailEnvContractTest` (mail env artefacts agree with the app; structurally cannot see GitHub Environment state — the workflow's mail-coherence guard covers that half).

Patterns unchanged: `RefreshDatabase` · factories (`->caseManager()`, `->agency()`, `->admin()`) · `actingAs` · `assertDatabaseHas/Missing` · Inertia assertions · `Notification::fake()` · `Storage::fake('object-storage')` · sync queue.

## CI/CD Integration (corrected)

```bash
composer test        # backend-tests job (PG17 service, pretend + force + cache-verify first)
npm run test:run     # frontend-tests job
npm run typecheck    # lint-and-audit job, BLOCKING
vendor/bin/pint --test
```

No E2E step exists in CI. `E2E_TEST_FINDINGS_v1.0.0.md` is the E2E record.

## Coverage Expectations (re-affirmed)

Auth/Security High · Case CRUD High · Referral lifecycle High · Reports Medium · Admin CRUD Medium · Chatbot Medium (see `docs/CHATBOT_AGENT.md`) · UI Components Low (manual). New lock/scheduler/mail-contract suites join the High-priority regression set (deploy-path protection).

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 2.1.0 | 2026-09-15 | Removed runnable-E2E claims (no config/suite; record is `E2E_TEST_FINDINGS_v1.0.0.md`); added 5 new suites; marked `typecheck` blocking; linked `STAGING_DATA_v1.0.0.md`; re-affirmed `phpunit.xml` overrides incl. legacy storage fakes. |
| 2.0.1 | 2026-07-27 | Storage-fake disk correction (`object-storage`; `SUPABASE_S3_*` as legacy aliases). |
| 2.0.0 | 2026-07-11 | Previous revision (`TESTING_STRATEGY.md`). |
