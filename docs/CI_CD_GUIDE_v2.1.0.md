# CI/CD Pipeline Guide

> **Version:** 2.1.0 | **Updated:** 2026-09-15 | **Supersedes:** `CI_CD_GUIDE_v2.0.0.md` (2026-07-27)
> **Source of truth:** `.github/workflows/ci.yml`, `.github/workflows/deploy.yml`, `.github/workflows/deploy-production.yml`, `.github/workflows/build-image.yml`, `composer.json`, `package.json`, `.npmrc`
> **Delta scope:** v2.0.0 remains the design reference (§0 provider-agnostic principle, §4 trigger contract, §5 environments, §9 troubleshooting shape, §10 standards check). This revision replaces the documented workflow set and job contents with the verified four-file reality. **There is no `deploy-staging.yml`, no `reset-staging-data.yml`, no daily 02:00 UTC staging reset** — any doc referencing them is stale.

## 0. What changed in v2.1.0 (delta)

| # | v2.0.0 said | Verified reality (2026-09-15) |
|---|---|---|
| D1 | 3 workflows + staging/deploy-staging/reset-staging-data file tree | **4 files:** `ci.yml` · `deploy.yml` (reusable) · `deploy-production.yml` (caller) · `build-image.yml` (manual). No staging caller exists; §7 file tree replaced |
| D2 | CI on "PR to main"; PHP 8.3 / Node 22; E2E Playwright job | CI on **PR to `main` AND push to `main`**; **PHP 8.4**, **Node 24**; **no E2E job** — jobs are `lint-and-audit` + `backend-tests` (PG17) + `frontend-tests`. TypeScript check is **blocking** (`npm run typecheck`, NOT continue-on-error) |
| D3 | `e2e-tests` required check, `npx playwright test` portable command | **No `playwright.config.ts`, no `tests/e2e`** — `npx playwright test` is **not runnable** in this repo. E2E history lives in `docs/E2E_TEST_FINDINGS_v1.0.0.md` (record, not a runnable suite) |
| D4 | Generic deploy trigger + `/up` health gate | `deploy.yml` is a concrete reusable workflow: **OIDC** (`id-token: write`), **ECR image-exists check**, **mail-coherence guard** (`resend` without `RESEND_API_KEY` fails pre-deploy), **pre-deploy snapshot**, **jq-rendered payload**, **ACTIVE-on-expected-image poll**, **`/up` + `/api/readyz` gates** |
| D5 | Deploy payload cache/queue/session unspecified | Payload **forces `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`** (deploy override vs local redis — see Deployment Guide v3.1.0 Annex 4A) |
| D6 | Production trigger "type deploy-production" | Trigger is `workflow_dispatch` with **`image_tag` (ECR SHA, doubles as rollback selector) + `confirm == PRODUCTION`** → environment `production`, service `bayanihan-production`, host `dmw7.owbap.app` |
| D7 | No image-build workflow documented | `build-image.yml` (manual): builds SHA-tagged ECR image + 8 verification gates incl. FreeType/JPEG, chart render, helpdesk corpus, nginx/supervisord parse, entrypoint fail-closed/open |

## 1. CI pipeline (`ci.yml`) — verified

Triggers: `pull_request` → `main` **and** `push` → `main`. Least-privilege (`contents: read`), ref-scoped concurrency (`ci-${{ github.ref }}`, cancel-in-progress), global `DB_SSLMODE: disable`.

| Job | Runner deps | Steps (in order) |
|---|---|---|
| `lint-and-audit` | PHP **8.4** (`pdo, pdo_pgsql, sockets`), Node **24**, Go 1.27.x | checkout → composer cache/install → node cache/`npm ci` → **`vendor/bin/pint --test`** → **`composer audit`** → **`npm audit --audit-level=high`** → install **Ward** `v0.4.2`, `ward scan . --output json,sarif --baseline .ward-baseline.json --fail-on high` → **`npm run typecheck` (BLOCKING)** → **`npm run build`** |
| `backend-tests` | Same PHP/Node + **PostgreSQL 17 service** (`bayanihan_test`/`postgres`/`postgres`, `pg_isready` health) | checkout → install → `cp .env.example .env && php artisan key:generate` → `npm run build` → **production cache-command verification** (`config/route/view/event:cache` then matching `:clear` — deliberately NOT `optimize:clear`, which would hit the redis-configured cache store with no Redis in the job) → `migrate --pretend --force` → `migrate --force` → `composer test` (all DB env pinned to `127.0.0.1:5432/bayanihan_test`) |
| `frontend-tests` | Node 24 | checkout → cache/`npm ci` → **`npm run test:run`** (`LARAVEL_BYPASS_ENV_CHECK=1`) |

`.npmrc` (`ignore-scripts=true`, `audit=true`) applies to every `npm ci`.

### Step → failure meaning (updated)

`pint --test` = style drift · `composer audit` / `npm audit --audit-level=high` = known vuln · Ward high = static-security finding · **`typecheck` = TypeScript error, blocks merge** · `build` = broken import/JSX · `migrate --pretend` = migration SQL would fail on PG17 · cache-command verification = non-serializable config value that would abort every container boot · `composer test` = PHP regression · `test:run` = React regression.

## 2. Database in CI — re-affirmed

PostgreSQL **17 service container**, fresh per run, never staging/production. `DB_SSLMODE=disable` globally; per-step `DB_*` pin to `127.0.0.1`. Staging/production endpoints arrive via deploy payload secrets.

## 3. Deploy workflows — verified

### Staging

No staging environment, no staging caller, no reset workflow. Do not add one until container service, database, object-storage buckets, secrets, DNS, and the GitHub Environment exist and are verified. `reset-staging-data.yml` and any "daily 2AM" schedule **do not exist**.

### Image (`build-image.yml`, manual `workflow_dispatch`)

Builds the deployable artefact separately from deploying it (schema must apply before the image that depends on it). OIDC → ECR login → Buildx (`cache-from/to type=gha`, `provenance: false`, build-args `VITE_SENTRY_DSN_PUBLIC` / `VITE_SENTRY_RELEASE=<sha>` / `VITE_APP_ENV`) → `load: true` as `bayanihan:<sha>` → verifications against the exact artefact:

1. PHP extensions present: `pdo_pgsql pdo_sqlite redis bcmath gd intl pcntl exif zip`
2. GD FreeType + JPEG (`imagettftext`/`imagettfbbox` exist, `gd_info` flags) — guards the `/reports/export-pdf` 500 class
3. `PdfChartRenderer::pieChart` renders `data:image/png;base64,` through the real code path
4. Helpdesk corpus packaged (`resources/js/data/helpdesk/content/*.ts`)
5. `nginx -t` parses (with `--entrypoint` bypass — the image entrypoint would otherwise demand full prod env)
6. Supervisord parses via configparser: sections `php-fpm/nginx/queue-worker/scheduler` present, `RUN_SCHEDULER` + `RUN_QUEUE_WORKER` guards present, worker `--timeout=` explicit
7. Entrypoint **fail-closed**: with `APP_ENV=production` and no creds → non-zero exit naming `APP_KEY DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD`
8. Entrypoint **opens** with valid config (`docker-entrypoint.sh echo STARTED`)

Then pushes `$REGISTRY/bayanihan:<sha>` (ECR tags **immutable** — rollback = redeploy previous tag) and reports image-scan findings (continue-on-error).

### Deploy (`deploy.yml`, reusable — called by `deploy-production.yml`)

Inputs: `environment` · `service_name` · `hostname` · `image_tag` (commit SHA) · `app_env` (default `production`). Perms `contents: read` + `id-token: write`; region `ap-southeast-1`, repo `bayanihan`.

1. OIDC AWS credentials (`vars.AWS_DEPLOY_ROLE_ARN`).
2. **ECR check**: `ecr describe-images --image-ids imageTag=<tag>` — refuse before touching anything.
3. **Mail-coherence guard**: `MAIL_MAILER=resend` (case-insensitive) with empty `RESEND_API_KEY` → fail with remediation (`gh secret set … --env <env>`). Catches the outage class where a renamed secret ships `resend` with no key.
4. **Snapshot**: `lightsail create-relational-database-snapshot` named `<db>-predeploy-<UTC>`; `LIGHTSAIL_DB_NAME` missing → refuse. (Lightsail ≠ RDS snapshots — moving engines means changing this step.)
5. **Render payload** (jq, never logged): image `$REGISTRY/bayanihan:<tag>`, port `8080`, `APP_ENV/APP_URL/APP_TIMEZONE=Asia/Manila`, `LOG_CHANNEL=stderr`, `DB_*` + `DB_SSLMODE=require`, **`CACHE_STORE/QUEUE_CONNECTION/SESSION_DRIVER=database`**, `FILESYSTEM_DISK/STORAGE_*/R2_*`, separate `AUDIT_ARCHIVE_BUCKET`, `RUN_MIGRATIONS/RUN_SCHEDULER/RUN_QUEUE_WORKER=true`, `TRUSTED_PROXIES=*`, mail (`log` default; `resend` only post-verification), Sentry (`SENTRY_RELEASE=<tag>` asserted == `IMAGE_TAG`), OpenRouter/Turnstile, readiness token, `SEARCH_INDEXING_ENABLED=false` default, plus all GitHub Environment `vars` merged. Health check `GET /up`, 30 s interval.
6. Submit deployment → **poll for `currentDeployment.state == ACTIVE` on the expected image** (40 × 15 s; poll the deployment, not the service — `READY` means "no deployment"). On `FAILED`, dump last 40 container-log lines.
7. **`/up` gate** (10 × 15 s) then **`/api/readyz` gate** (`X-Monitoring-Token` required; refuse without it; 75 s settle for scheduler heartbeat; 8 × 20 s; body appended to summary). `/up` green + `readyz` red = serving but degraded → fail.

### Production (`deploy-production.yml`)

Manual only. `workflow_dispatch` inputs `image_tag` (required) + `confirm` (must equal `PRODUCTION`) → `guard` job → calls reusable `deploy.yml` with `environment: production`, `service_name: bayanihan-production`, `hostname: dmw7.owbap.app`, `app_env: production`. Concurrency `deploy-production`, no cancel. Configure the `production` GitHub Environment with required reviewers — without it, "manual" only means "someone clicked".

## 7. Workflow files — verified

```
.github/
├── workflows/
│   ├── ci.yml                # PR+push: lint/audit/Ward/typecheck/build, PG17 backend, Vitest
│   ├── build-image.yml       # Manual: SHA-tagged ECR build + 8 image verifications
│   ├── deploy.yml            # Reusable: OIDC → ECR/mail/snapshot/payload/ACTIVE → /up → /readyz
│   └── deploy-production.yml # Manual: confirm==PRODUCTION → production dmw7.owbap.app
```

`deploy-staging.yml` and `reset-staging-data.yml` do not exist. Branch protection should require `Code Quality & Security`, `Backend Tests`, `Frontend Tests` (there is no E2E check to require).

## 8. Porting the pipeline (floors corrected)

Runner: Linux, **PHP >= 8.4.1** (`pdo_pgsql`, `sockets`, plus app extensions), **Node 24**, Composer 2, Go 1.27 (Ward) — or drop Ward with a recorded decision. Service: PostgreSQL 17 at `127.0.0.1:5432`, `DB_SSLMODE=disable`. Commands: `composer install` · `npm ci` (`.npmrc` honour) · `vendor/bin/pint --test` · `composer audit` · `npm audit --audit-level=high` · `ward scan` · **`npm run typecheck` (blocking)** · `npm run build` · cache-command verify loop · `migrate --pretend` · `composer test` · `npm run test:run`. Deploy: implement the §4 contract + snapshot + `/up` + deep readiness.

## 11. Changelog

| Version | Date | Change |
|---|---|---|
| 2.1.0 | 2026-09-15 | Reconciled with the four-file reality: `ci.yml` triggers/versions/jobs (PHP 8.4, Node 24, Ward, blocking typecheck, PG17, cache-command verification, no E2E); `deploy.yml` OIDC/ECR/mail-guard/snapshot/jq-payload/`database`-trio override/ACTIVE-poll/`/up`+`/readyz` gates; `deploy-production.yml` `image_tag`+`PRODUCTION` confirm → `dmw7.owbap.app`; `build-image.yml` manual SHA build + 8 verifications. Marked `deploy-staging.yml`/`reset-staging-data.yml`/daily-reset as non-existent; E2E as record-only (`E2E_TEST_FINDINGS_v1.0.0.md`); `.npmrc` noted. |
| 2.0.0 | 2026-07-27 | Platform-neutral overhaul (see its §11). |
| 1.0.0 | — | Previous revision (`CI_CD_GUIDE.md`). |
