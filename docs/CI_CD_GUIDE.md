# CI/CD Pipeline Guide

This document covers the full GitHub Actions CI/CD setup for Bayanihan One Window.

---

## Overview

```
Developer → Push/PR → GitHub Actions → Tests Pass? → Auto Deploy → Live
```

| Workflow | Trigger | Purpose |
|---|---|---|
| `ci.yml` | PR to `main` | Lint, audit, test, E2E |
| `deploy-staging.yml` | Push/merge to `main` | Auto-deploy to staging |
| `deploy-production.yml` | Manual (workflow_dispatch) | Deploy to production with safeguards |
| `reset-staging-data.yml` | Manual | Reset staging database |

---

## Pipeline Architecture

### CI Pipeline (`ci.yml`)

Runs **4 parallel jobs** on every pull request to `main`:

```
┌─────────────────┐  ┌────────────────┐  ┌────────────────┐
│ lint-and-audit  │  │ backend-tests  │  │ frontend-tests │
│ - Pint (PHP CS) │  │ - Migration    │  │ - Vitest       │
│ - composer audit│  │   check        │  │                │
│ - npm audit     │  │ - PHPUnit      │  │                │
│ - Vite build    │  │                │  │                │
└─────────────────┘  └───────┬────────┘  └───────┬────────┘
                              │                    │
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
| `lint-and-audit` | Code style issues, vulnerable dependencies, broken builds |
| `backend-tests` | Broken migrations, PHP regressions, business logic bugs |
| `frontend-tests` | Broken React components, hook errors, utility bugs |
| `e2e-tests` | Full-stack integration issues, broken user flows |

### Staging Deploy (`deploy-staging.yml`)

```
Push to main → Run tests → Deploy to Render → Health check /up → Slack notify
```

Fully automatic. Every merge to `main` goes live on staging within minutes.

### Production Deploy (`deploy-production.yml`)

```
Manual trigger → Confirm "deploy-production" → Tests → Environment approval → Deploy → Health check → Slack notify
```

Safeguards:
- Must type `deploy-production` to confirm (prevents accidental clicks)
- Only runs from `main` branch
- Full test suite runs before deploy
- Uses GitHub environment `production` (optional reviewer approval gate)
- 3-minute health check window (12 attempts × 15s)

---

## GitHub Repository Secrets

Go to **GitHub → Settings → Secrets and variables → Actions → New repository secret**.

### Required for Staging

| Secret | Where to get it |
|---|---|
| `RENDER_STAGING_SERVICE_ID` | Render → Service → Settings → Service ID |
| `RENDER_API_KEY` | Render → Account Settings → API Keys |

### Required for Production

| Secret | Where to get it |
|---|---|
| `RENDER_PRODUCTION_SERVICE_ID` | Render → Production service → Settings → Service ID |
| `PRODUCTION_URL` | Your production URL, e.g. `https://bayanihan.onrender.com` |

### Optional

| Secret | Purpose |
|---|---|
| `SLACK_WEBHOOK` | Slack Incoming Webhook URL for deploy notifications |

---

## Branch Protection Rules

Go to **GitHub → Settings → Branches → Add branch protection rule**.

**Branch name pattern:** `main`

| Setting | Recommended |
|---|---|
| Require a pull request before merging | ✅ |
| Required approvals | 1 |
| Dismiss stale pull request approvals when new commits are pushed | ✅ |
| Require status checks to pass before merging | ✅ |
| Required status checks | `lint-and-audit`, `backend-tests`, `frontend-tests`, `e2e-tests` |
| Require branches to be up to date before merging | ✅ |
| Include administrators | ✅ |
| Allow force pushes | ❌ |
| Allow deletions | ❌ |

This ensures no code reaches `main` without passing all checks and getting reviewed.

---

## Production Environment Approval (Optional)

For an additional approval gate before production deploys:

1. Go to **GitHub → Settings → Environments → New environment**
2. Name it `production`
3. Check **Required reviewers** and add 1–2 team leads
4. Optionally set **Wait timer** (e.g., 5 minutes for a cooldown)

The production deploy job will pause at the `deploy` step and wait for a reviewer to approve before proceeding.

---

## How to Use

### Day-to-Day Development

```bash
# Create feature branch
git checkout -b feat/my-feature

# Work, commit, push
git add .
git commit -m "feat: add new feature"
git push -u origin feat/my-feature

# Open PR to main on GitHub → CI runs automatically
# Get review → Merge → Staging auto-deploys
```

### Deploy to Production

1. Go to **GitHub → Actions → Deploy to Production**
2. Click **Run workflow**
3. Select branch: `main`
4. Type `deploy-production` in the confirmation field
5. Click **Run workflow**
6. If environment approval is configured, a reviewer must approve
7. Deployment proceeds → health check → Slack notification

### View E2E Test Reports

If E2E tests fail:
1. Go to the failed workflow run
2. Scroll to **Artifacts**
3. Download `playwright-report`
4. Open `index.html` in a browser to see screenshots and traces

---

## Workflow Files

```
.github/
├── workflows/
│   ├── ci.yml                  # PR checks (lint, audit, tests, E2E)
│   ├── deploy-staging.yml      # Auto-deploy to staging on merge
│   ├── deploy-production.yml   # Manual production deploy
│   └── reset-staging-data.yml  # Reset staging database
└── ISSUE_TEMPLATE/
    └── staging-bug-report.md
```

---

## What Each CI Step Validates

| Step | Failure means... |
|---|---|
| `composer install` | Broken/missing PHP dependencies |
| `npm ci` | Broken/missing JS dependencies |
| `npm run build` | TypeScript errors, broken imports, JSX issues |
| `vendor/bin/pint --test` | Code formatting doesn't match team standard |
| `composer audit` | Known security vulnerabilities in PHP packages |
| `npm audit --audit-level=high` | High-severity JS vulnerabilities |
| `php artisan migrate --pretend` | Migration SQL would fail on a real DB |
| `composer test` | PHP tests failing (routes, services, models) |
| `npm run test:run` | React component/hook/utility tests failing |
| `npx playwright test` | User-facing flows broken in the browser |

---

## Troubleshooting

### CI tests pass locally but fail in GitHub Actions

- **DB issues:** CI uses `127.0.0.1` as DB host (service containers expose on localhost). Locally you might use `localhost` or a different port.
- **Missing env vars:** CI copies `.env.example` — make sure it has sensible defaults for testing.
- **Node version:** CI uses Node 20. Check your local version with `node -v`.

### E2E tests are flaky

- The Playwright config uses `retries: 2` in CI to handle flakiness.
- Check the uploaded `playwright-report` artifact for screenshots and traces.
- Common cause: `php artisan serve` takes too long to start. The `webServer.timeout` is set to 30s.

### Staging deploy succeeds but health check fails

- Render cold-starts can take 30–60s. The health gate retries 8 times (2 minutes).
- Check if `/up` route exists and is accessible without auth.
- Check Render logs for boot errors.

### Production deploy is stuck on "Waiting for review"

- A required reviewer needs to approve in GitHub → Actions → the pending run.
- If no environment is configured, remove `environment: production` from the workflow.

---

## Database in CI

CI uses a **local PostgreSQL 17 service container** — not Supabase. This is intentional:

- ✅ Free (no quota usage)
- ✅ Fast (no network latency)
- ✅ Isolated (fresh DB every run)
- ✅ Same engine as Supabase (PostgreSQL 17)

Your staging/production `.env` on Render points to the real Supabase instance. CI just needs PostgreSQL compatibility to validate migrations and queries.

---

## Adding New Secrets Checklist

When you set up a new environment or rotate keys:

- [ ] `RENDER_API_KEY` — Render account API key
- [ ] `RENDER_STAGING_SERVICE_ID` — Staging service ID from Render
- [ ] `RENDER_PRODUCTION_SERVICE_ID` — Production service ID from Render
- [ ] `PRODUCTION_URL` — Full production URL (e.g., `https://bayanihan.dmw.gov.ph`)
- [ ] `SLACK_WEBHOOK` — Slack incoming webhook for notifications
