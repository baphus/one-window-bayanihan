# AWS Staging Deployment Runbook

> **Version:** 1.3.0 | **Updated:** 2026-07-27 | **Supersedes:** `DEPLOYMENT_STAGING_AWS_v1.2.0.md`
> **Operative document.** v1.2.0 recorded that the environment had been torn down;
> this version records the environment that replaced it. v1.1.0 §2 (custom domain
> cutover) and §3 (application changes) still apply unchanged.
> **Platform-neutral contract:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md`

## 0. What changed from 1.2.0

The environment was rebuilt from scratch on 2026-07-27. Two decisions differ from
every earlier version of this runbook:

1. **The database is a Lightsail managed database, not Amazon RDS.** This makes
   the whole environment a single product with one flat bill, and it escapes the
   `FreeTierRestrictionError` that capped RDS automated backups at one day.
2. **Data was NOT restored.** The surviving RDS snapshots were left untouched and
   the database was seeded fresh, because the `APP_KEY` the encrypted columns were
   written with did not survive the teardown. See §6.

## 1. Resource inventory

Account `677206905439`, region `ap-southeast-1` throughout. No secrets appear in
this document.

| Resource | Identifier / value |
|---|---|
| Container service | `bayanihan-staging` — power `small` (0.5 vCPU / 1 GB), scale `1` |
| Default URL | `https://bayanihan-staging.m317gkz7tgsqm.ap-southeast-1.cs.amazonlightsail.com/` |
| Private domain | `bayanihan-staging.service.local` |
| Database | Lightsail `bayanihan-staging-db` — PostgreSQL **17.10**, bundle `micro_2_0`, encrypted, automated backups on, backup window `18:19-18:49` UTC |
| DB endpoint | `ls-a749a021ef0a5e80d1614eb8137dbe46323768c9.cv6oi46464o5.ap-southeast-1.rds.amazonaws.com:5432` |
| DB master user / database | `bayanihan_admin` / `bayanihan` |
| Uploads bucket | S3 `bayanihan-staging-files` — versioned, SSE-S3 + bucket keys, public access fully blocked, **no Object Lock** |
| Audit archive bucket | S3 `bayanihan-staging-audit-archives` — same, **plus Object Lock (GOVERNANCE, 30 days)** |
| Container registry | `677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan` — immutable tags, scan on push |
| Certificate | Lightsail `owbap-staging` for `staging.owbap.app` |
| App runtime IAM user | `bayanihan-staging-app` (access key rotated 2026-07-27) |
| CI role (OIDC) | `bayanihan-github-actions-ci` |
| Deploy role (OIDC) | `bayanihan-github-actions-deploy` |

**Two buckets, deliberately.** Object Lock is a bucket-wide setting. One shared
bucket would force a choice between immutable audit archives and a retention job
(`storage:cleanup-orphans`, `cases:purge-trashed`) that must be able to delete
orphaned uploads. The application already supports this: `AUDIT_ARCHIVE_BUCKET` is
a separate configuration key from `STORAGE_BUCKET`.

Object Lock **can only be enabled when a bucket is created**. If the audit archive
bucket is ever recreated without it, immutability is lost and the only remedy is
another new bucket plus a verified copy.

The app's IAM policy grants the audit bucket `PutObject` and `GetObject` but
carries an **explicit `Deny` on `DeleteObject`, `DeleteObjectVersion`,
`PutObjectRetention`, `PutObjectLegalHold`, and `BypassGovernanceRetention`**, so
the application cannot weaken or delete its own audit evidence.

## 2. Database settings applied out-of-band

Role-level PostgreSQL defaults do not travel with the codebase and must be
reapplied to every new environment. Applied and verified on a fresh connection on
2026-07-27:

```
lock_timeout      = 10s
statement_timeout = 2min
```

TLS was confirmed in use (`SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()`
returned true).

Why these are not in `config/database.php`: Laravel's `PostgresConnector`
configures only `isolationLevel`, `timezone`, `searchPath`, and
`synchronousCommit`. A key for either timeout would be **silently ignored**, which
reads as configured while doing nothing.

Rationale is unchanged from v1.0.0 §5 — `lock_timeout` bounds the
`pg_advisory_xact_lock` that serialises all case creation; `statement_timeout` is
set at 120s rather than 30s because report exports and audit archiving run on the
same role.

## 3. Seeded state

| Table | Rows |
|---|---|
| `users` | 1 (`admin@bayanihan.gov.ph`, role `ADMIN`, active) |
| `agencies` | 9 |
| `cases`, `audit_logs`, `jobs`, `failed_jobs`, `case_number_counters` | 0 |

`case_number_counters` being empty is correct for a database with no cases — the
counter seeds on first allocation. v1.0.0 §6b's warning applies only when
migrating a database that already holds issued case numbers.

The administrator password was **generated at seed time** and printed once. It is
not stored in the repository, in this document, or anywhere recoverable. Rotate it
after first sign-in.

## 4. Mail

`MAIL_MAILER=log` for now. Resend refuses to send from an unverified sender
domain, and no SPF/DKIM/DMARC records exist for `owbap.app` (deviation #2). Under
`log`, OTP and MFA codes are written to the container log rather than silently
dropped, so sign-in remains possible:

```powershell
aws lightsail get-container-log --service-name bayanihan-staging --container-name app `
  --region ap-southeast-1 --query "logEvents[].message" --output text
```

**This is a staging-only accommodation.** It means every OTP is readable by anyone
with console access to the log, so the no-real-OFW-data rule is load-bearing, not
advisory. Publish the sender records and set `MAIL_MAILER=resend` before any real
user account exists.

## 5. CI/CD

Unchanged from v1.2.0 §2 apart from three corrections found while provisioning:

| Correction | Why it mattered |
|---|---|
| Pre-deploy snapshot uses `lightsail create-relational-database-snapshot` | The database is Lightsail, not RDS. Lightsail and RDS snapshots are not interchangeable in either direction, so the RDS call would simply have failed and skipped the safety net. |
| `AUDIT_ARCHIVE_BUCKET` is its own variable | It previously reused `STORAGE_BUCKET`, which would have written audit archives into the non-Object-Lock bucket. |
| `MAIL_MAILER` is configurable, default `log` | It was hardcoded to `resend`; mail would have been accepted and dropped, making sign-in impossible with nothing shown in the UI. |

The image build additionally asserts that the entrypoint **fails closed**. This
came out of a real CI failure: `docker run <image> php -m` executes the entrypoint,
which now exits non-zero when `config:cache` fails, so every inspection step needs
`--entrypoint`. Weakening the entrypoint to suit CI would have restored the silent
failure it was written to remove, so the behaviour is pinned by a test instead.

### GitHub Environment configuration

`staging` — secrets: `APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`, `STORAGE_ACCESS_KEY`, `STORAGE_SECRET_KEY`,
`MONITORING_READINESS_TOKEN`. Variables: `STORAGE_BUCKET`,
`AUDIT_ARCHIVE_BUCKET`, `LIGHTSAIL_DB_NAME`, `MAIL_MAILER`, `MAIL_FROM_ADDRESS`.

`production` — exists but is **not configured**. It has no secrets and no required
reviewers, so `deploy-production.yml` cannot succeed yet, which is the safe state.

## 6. The untouched snapshots — a decision that is still open

Two RDS snapshots remain and are billed as backup storage with no live RDS
instance to absorb them (roughly 40 GB total, on the order of **$4/month** —
material against a ~$31/month budget):

| Snapshot | Created | Engine |
|---|---|---|
| `bayanihan-staging-db-snapshot` | 2026-07-27 07:08 UTC | postgres 17.10 |
| `database-1-snapshot` | 2026-07-26 21:25 UTC | postgres 18.3 |

They were not restored: their `EncryptedString` / `EncryptedDate` columns can only
be read with the `APP_KEY` they were written with, and that key did not survive the
container teardown. Without it those columns are permanently unreadable, so a
restore would recover schema and plaintext columns while silently corrupting
encrypted ones — worse than starting clean.

**Nothing has been deleted.** Deleting them saves ~$4/month and discards the only
remaining copy of that data. That is an owner's decision and is recorded here as
outstanding rather than taken.

## 7. Cost

| Item | Monthly (USD) |
|---|---|
| Container service, `small` | 15 |
| Lightsail PostgreSQL, `micro_2_0` | 15 |
| S3, two buckets at low volume | ~1 |
| Certificate, ECR repository | 0 |
| RDS snapshot storage (§6, removable) | ~4 |
| **Total** | **~$31–35** |

Against $200 of credits that is roughly **six months**, or about 5.7 months if the
snapshots are kept.

Both the container service and the database bill whether enabled or not, and
whether a deployment exists or not. They must be **deleted**, not stopped, to stop
charging.

## 8. Standards-readiness check

**Closed or materially improved since v1.2.0:**

| Item | Standard |
|---|---|
| Audit archives now on Object Lock, with the app explicitly denied delete and bypass | A.8.15, CC7.2 |
| Database encrypted at rest, TLS verified in use, automated backups enabled | A.8.24, A.8.13 |
| Buckets versioned, encrypted, public access fully blocked | A.8.24 |
| Backup retention no longer capped at 1 day | A.8.13 |
| Orphaned app access key (secret lost, still Active) deleted and rotated | A.5.17 |
| Statement and lock timeouts applied and verified | A.8.6 |
| Seeded administrator password random and unpublished | A.5.17, CC6.1 |
| Deploy role denied every delete/destroy action | A.8.2, CC6.1 |

**Still requiring rework:**

| Finding | Standard | Note |
|---|---|---|
| **Database publicly reachable.** Required by the pipeline, which runs migrations from a GitHub Actions runner, and Lightsail container services cannot join a VPC. Mitigated by TLS, a 40-character password, and no real data. | A.8.20/A.8.22, CC6.6 | Architectural. Resolving it means moving compute to ECS in a VPC and running migrations as an in-VPC task. |
| **Account root used for administration.** Every CLI action provisioning this environment ran as root. | A.8.2, CC6.1 | Enable MFA on root; move humans to IAM Identity Center. |
| Legacy IAM users `bayanihan-ci-ecr` and `bayanihan-deploy` still exist | A.5.17 | Superseded by the OIDC roles — delete them. |
| Plaintext secrets in Lightsail container environment variables | A.8.24, CC6.1 | Readable via `lightsail get-container-services`; needs the VPC move for Secrets Manager. |
| `MAIL_MAILER=log` puts OTP codes in a console-readable log | A.8.15 | Staging only — see §4. |
| Object Lock is GOVERNANCE, not COMPLIANCE, at 30 days | A.8.15 | Deliberate for staging. Production wants COMPLIANCE at the real retention period. |
| Single node, no HA | A.5.29 | Cost decision. `RUN_SCHEDULER` / `RUN_QUEUE_WORKER` make the split possible without an image rebuild. |
| No restore drill performed | A.8.13 | Lightsail automated backups are on but untested. |
| No alert routing | A.8.6 | `/api/readyz` exposes the thresholds; an external monitor still has to consume it. |
| Supplier register incomplete | A.5.19–A.5.22 | AWS, Resend, Sentry, OpenRouter, Cloudflare. |
| Upload malware scanning absent | A.8.7 | `MALWARE_SCANNER=null`. |

## 9. Changelog

| Version | Date | Change |
|---|---|---|
| 1.3.0 | 2026-07-27 | Recorded the rebuilt environment: Lightsail `small` container service, Lightsail PostgreSQL 17.10 `micro_2_0` in place of RDS, and **two** S3 buckets so audit archives can carry Object Lock while uploads stay deletable. Applied and verified role-level timeouts; confirmed TLS. Seeded fresh with a generated administrator password; snapshots left untouched because the original `APP_KEY` was lost. Set `MAIL_MAILER=log` with the reasoning and its consequence. Logged three CI corrections found during provisioning and the outstanding snapshot-retention decision. |
| 1.2.0 | 2026-07-27 | Recorded that the previous environment no longer existed; replaced the CI/CD suite; migrated CI auth to GitHub OIDC. |
| 1.1.0 | 2026-07-27 | Custom domain cutover procedure; seven application changes including non-spoofable `X-Forwarded-For` and `/api/readyz`. |
| 1.0.0 | 2026-07-27 | Initial runbook for the original AWS Lightsail staging environment. |
