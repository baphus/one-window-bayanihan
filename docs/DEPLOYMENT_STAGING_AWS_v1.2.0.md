# AWS Deployment Runbook

> **Version:** 1.2.0 | **Updated:** 2026-07-27 | **Supersedes:** `DEPLOYMENT_STAGING_AWS_v1.1.0.md`
> **Operative document.** v1.1.0 documents the domain cutover procedure and the
> application changes; both still apply. This version corrects a load-bearing
> assumption both earlier versions make.
> **Platform-neutral contract:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md`

---

## 1. STOP — the environment described in v1.0.0 and v1.1.0 no longer exists

Verified against account `677206905439`, region `ap-southeast-1`, on 2026-07-27:

| Resource | v1.0.0 claim | Actual |
|---|---|---|
| Lightsail container service `bayanihan-staging` | running, power `small`, scale `1` | **does not exist** |
| RDS `bayanihan-staging-db` | PostgreSQL 17.10, `db.t4g.micro` | **does not exist** |
| S3 `bayanihan-staging-files` | private, versioned, SSE-S3 | **no S3 buckets in the account at all** |
| ECR repository `bayanihan` | present | present, `IMMUTABLE` tags, scan-on-push **enabled** |
| ECR images | image per commit SHA | **repository is empty** |
| Lightsail certificates | none (planned) | none |
| IAM users | 3 scoped users | present: `bayanihan-ci-ecr`, `bayanihan-deploy`, `bayanihan-staging-app` |

**Two RDS snapshots survive** and are the only remaining copy of any data:

| Snapshot | Created | Status |
|---|---|---|
| `bayanihan-staging-db-snapshot` | 2026-07-27 07:08 UTC | available |
| `database-1-snapshot` | 2026-07-26 21:25 UTC | available |

Do not delete either without a decision recorded. Restoring
`bayanihan-staging-db-snapshot` is the only path back to the seeded staging
dataset, including the `case_number_counters` state that v1.0.0 §6b verified.

**Consequence:** every command in v1.0.0 §5 and v1.1.0 §2 targets resources that
are absent. The cutover procedure in v1.1.0 §2 is correct but cannot run until a
container service exists again. Treat this as a **rebuild**, not a change.

---

## 2. What exists now: CI/CD

Replaced the previous mix of AWS and leftover Render automation.

### 2.1 Workflows

| Workflow | Trigger | Purpose |
|---|---|---|
| `ci.yml` | PR to `main`, push to `main` | Pint, `composer audit`, `npm audit`, asset build, `migrate --pretend` then `migrate`, backend tests on PostgreSQL 17, frontend tests |
| `build-image.yml` | push to `main` / `deploy/**`, manual | Build image, assert PHP extensions + FTS5 + nginx + supervisord parse, push to ECR tagged with the commit SHA, report scan findings |
| `deploy.yml` | `workflow_call` | Reusable: snapshot, migrate, deploy, wait for READY, `/up` gate, `/api/readyz` gate |
| `deploy-staging.yml` | `workflow_run` after a **successful** build on `main`, or manual | Calls `deploy.yml` for staging |
| `deploy-production.yml` | manual only, with `PRODUCTION` confirmation phrase | Calls `deploy.yml` for production |

**Deleted** (targeted Render, not AWS): `deploy-staging.yml`,
`deploy-production.yml`, `reset-staging-data.yml`. The last of these ran on a
**daily cron** issuing a destructive `clear_all`; it was inert only because the
Render variables were unset. Leaving a scheduled destructive job pointed at a
decommissioned platform is a live hazard, not dead code.

### 2.2 Ordering is the point

`build-image.yml` and the deploy workflows are deliberately separate. Migrations
run inside `deploy.yml` **before** the image is deployed, and a non-zero exit
aborts without touching the running service. A schema change must land before the
code that depends on it — v1.0.0 §6b records what happens otherwise.

`RUN_MIGRATIONS` stays `false` in the container. Migrations belong where the exit
code is read, not in a start hook.

Staging deploys via `workflow_run` rather than a second `push` trigger, so it can
never run without a verified image: if the build failed, there is nothing to
deploy.

### 2.3 OIDC replaced the static access keys

Created in AWS on 2026-07-27:

| Resource | ARN / value |
|---|---|
| OIDC provider | `arn:aws:iam::677206905439:oidc-provider/token.actions.githubusercontent.com` |
| CI role | `arn:aws:iam::677206905439:role/bayanihan-github-actions-ci` |
| Deploy role | `arn:aws:iam::677206905439:role/bayanihan-github-actions-deploy` |

GitHub repository variables set: `AWS_ACCOUNT_ID`, `AWS_CI_ROLE_ARN`,
`AWS_DEPLOY_ROLE_ARN`.

Trust conditions are scoped, not blanket:

- **CI role** — assumable only from `refs/heads/main` and `refs/heads/deploy/*`.
  Permissions: ECR auth token (account-wide, as the API requires) plus push and
  describe **on the `bayanihan` repository only**.
- **Deploy role** — assumable only from
  `repo:baphus/one-window-bayanihan:environment:staging` and
  `:environment:production`. Credentials are therefore unobtainable from a job
  that is not running in one of those Environments, which is what makes the
  Environment approval gate a real control rather than a UI convention.
  Permissions: Lightsail deployment, ECR read, and `rds:CreateDBSnapshot`.

This supersedes `AWS_ECR_ACCESS_KEY_ID` / `AWS_ECR_SECRET_ACCESS_KEY`. Static
keys never expire, cannot be scoped to a branch, and give no signal when leaked
(ISO 27001 A.5.17, SOC 2 CC6.1). **The three legacy IAM users still exist and
their access keys are still valid — delete them once a deploy has succeeded
through OIDC.** Until then they are an unmonitored second way in.

Lightsail actions are granted on `Resource: "*"` because Lightsail does not
support resource-level permissions for container-service APIs. That is a platform
limitation, not an oversight; record it as such.

### 2.4 Required before the first deploy

GitHub **Environment** secrets (`staging`, `production` — set per environment,
not repository-wide, so staging cannot read production's database password):

`APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`STORAGE_ACCESS_KEY`, `STORAGE_SECRET_KEY`, `MONITORING_READINESS_TOKEN`,
`RESEND_KEY`, `SENTRY_LARAVEL_DSN`.

GitHub Environment **variables**: `STORAGE_BUCKET`, `MAIL_FROM_ADDRESS`,
`RDS_INSTANCE_IDENTIFIER` (omit to skip the pre-deploy snapshot).

Configure **required reviewers** on the `production` Environment. Without that,
`deploy-production.yml` is only manual in the sense that someone clicked it.

`APP_KEY` must be the **same value the encrypted data was written with**. The
`EncryptedString` and `EncryptedDate` casts mean a new key makes existing columns
permanently unreadable — so if a database is restored from the surviving
snapshot, its original `APP_KEY` is required, not a fresh one.

---

## 3. Rebuilding the environment — the decision that comes first

The infrastructure is gone, so recreating it is a choice, not a restoration. Two
requirements are in direct tension and cannot both be satisfied:

| Requirement | Implication |
|---|---|
| "$200 of credits should last 5 months" | ≈ $40/month ceiling |
| "Everything production ready, best practices" | v1.0.0 deviation #1 calls the public-RDS design **blocking** for production and requires compute inside a VPC |

Costed options:

| Option | ~Monthly | Credits last | Deviation #1 |
|---|---|---|---|
| Lightsail `small` + RDS `db.t4g.micro` + S3 | ~$29–33 | ~6 months | **Unresolved** — Lightsail cannot join a VPC, so RDS stays internet-reachable |
| ECS Fargate (0.5 vCPU/1 GB) + ALB + private RDS, no NAT | ~$55–60 | ~3.4 months | Resolved |
| ECS Fargate (1 vCPU/2 GB) + ALB + private RDS + NAT | ~$100+ | ~2 months | Resolved |

Lightsail cannot be made compliant on this point at any price: the constraint is
architectural, not a sizing choice. So the real question is whether the
environment being rebuilt is a **demo** (Lightsail, cheap, keeps deviation #1 and
the no-real-data rule) or a **production candidate** (Fargate in a VPC, and the
credits do not last five months).

Deferring this is the expensive path. Deviation #12 is the reason: the audit
archive bucket needs **S3 Object Lock, which can only be enabled at bucket
creation**. Whatever bucket gets created during the rebuild is the one that
either can or cannot hold immutable audit evidence. Creating it without Object
Lock means a second bucket and a verified copy later.

No infrastructure has been provisioned. Nothing is billing.

---

## 4. Standards-readiness check

Applied per project policy — this is a project document, not a certification
artefact.

**Improved by this version:**

| Change | Standard |
|---|---|
| OIDC federation replaces long-lived CI access keys | A.5.17, CC6.1 |
| Deploy credentials bound to GitHub Environments, making approval a technical control | A.8.2, CC6.3 |
| Per-environment secrets, so staging cannot read production credentials | A.8.3 |
| Pre-deploy RDS snapshot in the pipeline | A.8.13 |
| Deploy gated on a deep readiness probe, not just `/up` | A.8.6, A.8.16 |
| Image scan findings surfaced per build; ECR tags immutable | A.8.8, A.8.30 |
| Destructive scheduled workflow removed | A.8.32 |
| Non-spoofable client IP resolution (v1.1.0 §3.2) | A.8.20 |

**Still requiring rework:**

| Finding | Standard | Note |
|---|---|---|
| Legacy IAM users and their access keys still active | A.5.17, CC6.1 | Delete after the first OIDC deploy succeeds |
| Account **root** used for administration — this session's CLI operations ran as root | A.8.2, CC6.1 | Enable MFA on root; move human access to IAM Identity Center |
| Publicly reachable RDS if Lightsail is chosen | A.8.20/A.8.22, CC6.6 | See §3 — architectural |
| Plaintext secrets in Lightsail container env | A.8.24, CC6.1 | Needs the VPC move |
| Object Lock on the audit archive bucket | A.8.15, CC7.2 | **Creation-time only** — decide before the rebuild |
| No restore drill | A.8.13 | Two snapshots exist; restoring one *is* the drill, so do it as part of the rebuild and record the result |
| Single node / no HA | A.5.29 | `RUN_SCHEDULER`/`RUN_QUEUE_WORKER` now make the split possible |
| Supplier register incomplete | A.5.19–A.5.22 | AWS, Resend, Sentry, OpenRouter, Cloudflare |

**Documents derivable from the standards rather than authored:** supplier
register (A.5.19–A.5.22), backup and restore procedure (A.8.13), logging and
monitoring policy (A.8.15–A.8.16). Each is a template-fill from the control text
plus this inventory and could be generated without design input — but none should
be *published* unreviewed, because each asserts organisational commitments
(retention periods, on-call rotas, processing terms) only the owner can make.

---

## 5. Changelog

| Version | Date | Change |
|---|---|---|
| 1.2.0 | 2026-07-27 | Recorded that the environment in v1.0.0/v1.1.0 no longer exists — no container service, no RDS instance, no S3 buckets, empty ECR — and that two RDS snapshots are the only surviving data. Replaced the CI/CD suite: deleted three Render workflows (one a daily destructive cron), added reusable `deploy.yml` with snapshot/migrate/deploy/health/readiness gating, plus staging and production callers. Migrated CI auth from static access keys to GitHub OIDC with branch- and Environment-scoped roles. Framed the rebuild cost-versus-compliance decision and flagged S3 Object Lock as creation-time-only. |
| 1.1.0 | 2026-07-27 | Turned v1.0.0 §6c's planned custom domain into an executable cutover procedure; shipped seven application changes including non-spoofable `X-Forwarded-For`, fail-fast entrypoint, `/api/readyz`, and the seeder password fix. |
| 1.0.0 | 2026-07-27 | Initial runbook for the AWS Lightsail staging environment. |
