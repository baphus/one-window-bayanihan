# CI/CD Pipeline Guide

> **Version:** 2.0.0 | **Updated:** 2026-07-27 | **Supersedes:** `CI_CD_GUIDE.md` (v1.0.0)
> **Source of truth:** `.github/workflows/*.yml`, `composer.json`, `package.json`, `playwright.config.ts`

## 0. Design principle

The pipeline is **provider-agnostic except for one step**: the call that tells
the hosting platform to roll out a new release. Everything before it (install,
lint, audit, test, build) and everything after it (health gate, notify) uses only
the project's own tooling and works on any CI runner.

That one step is isolated behind a small variable contract (§4), so changing
hosting platform is a settings change in the repository — not a pipeline rewrite.
Deployment targets and their capability requirements are specified in
`docs/DEPLOYMENT_GUIDE_v3.0.0.md`.

```
Developer → Push/PR → CI (lint · audit · test · E2E) → Deploy trigger → Health gate → Notify
```

| Workflow | Trigger | Purpose |
|---|---|---|
| `ci.yml` | PR to `main` | Lint, dependency audit, backend/frontend tests, E2E |
| `deploy-staging.yml` | Push/merge to `main` + manual | Tests, then automatic staging rollout |
| `deploy-production.yml` | Manual (`workflow_dispatch`) | Guarded production rollout |
| `reset-staging-data.yml` | Scheduled daily + manual | Rebuild staging data (fresh migrate + seed) |

The workflows currently run on the repository's hosted CI. The stage definitions
are portable: any runner that provides PHP 8.3+, Node 22+, and a PostgreSQL 17
service can execute the same commands (§8).

---

## 1. CI pipeline (`ci.yml`)

Four jobs, three of them parallel, on every pull request to `main`:

```
┌─────────────────┐  ┌────────────────┐  ┌────────────────┐
│ lint-and-audit  │  │ backend-tests  │  │ frontend-tests │
│ - Pint (PHP CS) │  │ - Migration    │  │ - Vitest       │
│ - composer audit│  │   check        │  │                │
│ - npm audit     │  │ - PHPUnit      │  │                │
│ - Vite build    │  │                │  │                │
└─────────────────┘  └───────┬────────┘  └───────┬────────┘
                             └─────────┬──────────┘
                                       ▼
                             ┌────────────────┐
                             │   e2e-tests    │
                             │ - Playwright   │
                             │ - Upload report│
                             └────────────────┘
```

| Job | What it catches |
|---|---|
| `lint-and-audit` | Code style drift, vulnerable dependencies, broken production build |
| `backend-tests` | Broken migrations, PHP regressions, business-logic defects |
| `frontend-tests` | Broken React components, hook errors, utility bugs |
| `e2e-tests` | Full-stack integration failures, broken user journeys |

### What each step validates

| Step | Failure means… |
|---|---|
| `composer install` | Broken or missing PHP dependencies |
| `npm ci` | Broken or missing JS dependencies |
| `npm run build` | TypeScript errors, broken imports, JSX issues |
| `vendor/bin/pint --test` | Formatting does not match the team standard |
| `composer audit` | Known vulnerability in a PHP package |
| `npm audit --audit-level=high` | High-severity JS vulnerability |
| `php artisan migrate --pretend` | Migration SQL would fail against a real database |
| `composer test` | PHP tests failing (routes, services, models) |
| `npm run test:run` | React component/hook/utility tests failing |
| `npx playwright test` | User-facing flow broken in the browser |

---

## 2. Database in CI

CI runs a **PostgreSQL 17 service container** — never the staging or production
database. This is intentional:

- Isolated: a fresh database every run, no cross-run contamination
- Fast: no network latency to a managed endpoint
- Free: no quota consumption on the production data platform
- Faithful: same engine major version as production, which is what migration and
  query validation actually depends on

Staging and production point at their own PostgreSQL endpoints through the
environment contract in `DEPLOYMENT_GUIDE_v3.0.0.md` §4.

---

## 3. Deploy workflows

### Staging (`deploy-staging.yml`)

```
Push to main → tests → trigger platform rollout → health-gate /up → notify
```

Fully automatic: every merge to `main` reaches staging within minutes. The deploy
job is skipped (not failed) when the deploy variables are unset, so a fork or a
platform-less checkout still runs green.

### Production (`deploy-production.yml`)

```
Manual trigger → type "deploy-production" → tests → environment approval → rollout → health gate → notify
```

Safeguards:

- Typed confirmation string prevents accidental runs
- Runs only from `main`
- Full test suite runs before the deploy step
- Protected CI environment (`production`) with optional required reviewers
- Health gate polls `/up` for ~3 minutes (12 attempts × 15 s) before declaring success

### Staging data reset (`reset-staging-data.yml`)

Triggers a staging rollout with a fresh migrate + seed, then health-checks the
staging URL. Never point this workflow at production credentials.

---

## 4. Deploy trigger contract

The rollout step needs exactly four values. Keep them as repository
variables/secrets so the platform can be swapped without touching workflow logic.

| Variable | Kind | Meaning |
|---|---|---|
| `DEPLOY_API_URL` | variable | Endpoint that starts a rollout — a platform deploy API or a plain deploy webhook |
| `DEPLOY_SERVICE_ID` | variable | Identifier of the target service/app on that platform (staging and production each have their own) |
| `DEPLOY_API_TOKEN` | **secret** | Credential presented to `DEPLOY_API_URL` |
| `HEALTH_CHECK_URL` | variable | `https://<env-domain>/up` — polled by the health gate |

Optional:

| Variable | Kind | Purpose |
|---|---|---|
| `NOTIFY_WEBHOOK_URL` | secret | Chat/incident webhook for deploy notifications |

The deploy step reduces to a single authenticated request plus the health gate:

```bash
# Start the rollout (shape of the request depends on the platform; the contract does not)
curl -fsS -X POST "$DEPLOY_API_URL/$DEPLOY_SERVICE_ID/deploys" \
  -H "authorization: Bearer $DEPLOY_API_TOKEN" \
  -H "content-type: application/json" \
  -d '{"clearCache":"do_not_clear"}'

# Gate on the application's own readiness endpoint
for i in $(seq 1 12); do
  curl -fsS "$HEALTH_CHECK_URL" && exit 0
  sleep 15
done
exit 1
```

Platforms that offer a **plain deploy webhook** need only `DEPLOY_API_URL` plus
its embedded token — omit `DEPLOY_SERVICE_ID`. Platforms deployed by pushing an
image instead of calling an API replace the `curl` with a registry push and a
rollout command (`kubectl rollout restart`, `docker service update`, …); the
health gate stays identical.

> **Current state:** the committed workflows still call one specific hosting
> provider's deploy REST API using provider-named secrets. That is the single
> remaining platform binding in the repository — see
> `DEPLOYMENT_GUIDE_v3.0.0.md` §12. Renaming those secrets to the neutral names
> above is a settings + workflow-edit change and is tracked as follow-up work;
> no application code depends on it.

---

## 5. Repository configuration

### Secrets and variables checklist

Set per environment (staging, production) when provisioning or rotating:

- [ ] `DEPLOY_API_URL` — rollout endpoint
- [ ] `DEPLOY_SERVICE_ID` — target service identifier
- [ ] `DEPLOY_API_TOKEN` — rollout credential *(secret; rotate on personnel change)*
- [ ] `HEALTH_CHECK_URL` — e.g. `https://bayanihan.dmw.gov.ph/up`
- [ ] `NOTIFY_WEBHOOK_URL` — deploy notifications *(optional secret)*

Application runtime configuration (database, storage, Redis, mail) belongs in the
**platform's** environment/secret store, not in CI. CI only needs to *start* the
rollout and *verify* the result.

### Branch protection on `main`

| Setting | Recommended |
|---|---|
| Require a pull request before merging | ✅ |
| Required approvals | 1 |
| Dismiss stale approvals on new commits | ✅ |
| Require status checks to pass | ✅ |
| Required checks | `lint-and-audit`, `backend-tests`, `frontend-tests`, `e2e-tests` |
| Require branches up to date before merging | ✅ |
| Include administrators | ✅ |
| Allow force pushes | ❌ |
| Allow deletions | ❌ |

### Production approval gate

Create a protected CI environment named `production`, require 1–2 reviewers, and
optionally set a wait timer. The production deploy job pauses at the deploy step
until a reviewer approves — this is the change-authorisation evidence auditors
ask for (§9).

---

## 6. How to use

### Day to day

```bash
git checkout -b feat/my-feature
git add . && git commit -m "feat: add new feature"
git push -u origin feat/my-feature
# Open a PR to main → CI runs → review → merge → staging deploys automatically
```

### Deploy to production

1. Open the CI **Deploy to Production** workflow
2. **Run workflow**, branch `main`
3. Type `deploy-production` in the confirmation field
4. Run; approve if a reviewer gate is configured
5. Rollout → health gate → notification

### Inspect a failed E2E run

1. Open the failed run
2. Download the `playwright-report` artifact
3. Open `index.html` locally for screenshots and traces

---

## 7. Workflow files

```
.github/
├── workflows/
│   ├── ci.yml                  # PR checks (lint, audit, tests, E2E)
│   ├── deploy-staging.yml      # Auto-deploy to staging on merge
│   ├── deploy-production.yml   # Guarded manual production deploy
│   └── reset-staging-data.yml  # Rebuild staging data
└── ISSUE_TEMPLATE/
    └── staging-bug-report.md
```

---

## 8. Porting the pipeline to another CI system

The stages are ordinary commands, so a port is mechanical:

| Requirement | Detail |
|---|---|
| Runner image | Linux with PHP 8.3+ (`pdo_pgsql`, `bcmath`, `gd`, `intl`, `mbstring`, `zip`), Node 22+, Composer 2 |
| Service | PostgreSQL 17 reachable at `127.0.0.1:5432` |
| Env | Copy `.env.example` → `.env`, then `php artisan key:generate` |
| Commands | `composer install` · `npm ci` · `npm run build` · `vendor/bin/pint --test` · `composer audit` · `npm audit --audit-level=high` · `php artisan migrate --pretend` · `composer test` · `npm run test:run` · `npx playwright test` |
| Artifacts | Upload `playwright-report/` on failure |
| Deploy | Implement the §4 contract |
| Gates | Manual approval for production; typed confirmation optional |

---

## 9. Troubleshooting

### Tests pass locally but fail in CI

- **Database host:** CI uses `127.0.0.1`; service containers expose on localhost. Local setups often use a different host or port.
- **Missing env:** CI copies `.env.example` — it must carry sane test defaults.
- **Runtime versions:** compare the runner's PHP/Node versions with yours (`php -v`, `node -v`).

### E2E tests are flaky

- Playwright uses `retries: 2` in CI.
- Check the uploaded `playwright-report` for traces.
- Common cause: the dev server is slow to boot; `webServer.timeout` is 30 s.

### Deploy succeeds but the health gate fails

- Cold starts on managed platforms can take 30–60 s; the gate retries with backoff. Widen the window before assuming a bad release.
- Confirm `/up` is routable without authentication.
- Read the platform's container/boot logs: the usual causes are a missing environment variable, an unreachable database, or a failed migration in a boot-time hook.

### Production deploy stuck on "waiting for review"

A required reviewer must approve the pending run. If no approval gate is wanted,
remove the `environment: production` binding from the workflow — and record that
decision, since it removes a change-control gate (§10).

---

## 10. Standards-readiness check

| Requirement | Standard reference | Status | Action if gapped |
|---|---|---|---|
| Changes tested before release | ISO 9001 8.5.1; SOC 2 CC8.1 | ✅ Full suite runs before every deploy job | — |
| Authorised change to production | ISO 27001 A.8.32; SOC 2 CC8.1 | ✅ Manual trigger + typed confirmation + reviewer environment | Keep the reviewer gate enabled; do not remove without a recorded decision |
| Segregation of duties | ISO 27001 A.5.3 | ⚠️ Reviewer gate is optional per repository settings | Require a reviewer who is not the author |
| Secure development pipeline | ISO 27001 A.8.25/A.8.28 | ✅ Lint, dependency audit, build, tests as gates | Fail the build on high-severity audit findings rather than reporting them |
| Vulnerability management | ISO 27001 A.8.8 | ✅ `composer audit` + `npm audit` per run | Track and remediate findings on a defined SLA |
| Protection of pipeline credentials | ISO 27001 A.8.24; SOC 2 CC6.1 | ✅ Secrets store only, never in the repository | Rotate deploy tokens on personnel change; log rotation |
| Traceability of releases | ISO 9001 7.5.3; ISO 27001 A.8.32 | ✅ Run history + PR trail per release | Retain run logs per the retention policy |
| Separation of environments | ISO 27001 A.8.31 | ✅ Distinct deploy variables and databases per environment | Never point the staging-reset workflow at production values |

---

## 11. Changelog

| Version | Date | Change |
|---|---|---|
| 2.0.0 | 2026-07-27 | Structural overhaul to a platform-neutral pipeline. Replaced all hosting-provider names, dashboard instructions, and provider-named secrets with the §4 deploy-trigger contract (`DEPLOY_API_URL`, `DEPLOY_SERVICE_ID`, `DEPLOY_API_TOKEN`, `HEALTH_CHECK_URL`) plus an explicit note that the committed workflows still bind to one provider's API. Added §2 rationale for the CI database, §8 guide to porting the pipeline to any CI runner, §10 standards-readiness check. Generalised troubleshooting away from provider-specific behaviour. |
| 1.0.0 | — | Previous revision (`CI_CD_GUIDE.md`): provider-specific deploy instructions, provider-named secrets, provider dashboard steps. |
