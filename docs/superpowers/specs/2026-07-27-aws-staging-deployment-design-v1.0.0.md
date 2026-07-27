# AWS Staging Deployment — Design Spec

> **Version:** 1.0.0 | **Date:** 2026-07-27 | **Status:** Awaiting approval
> **Scope:** Staging deployment of One Window Bayanihan to AWS, shaped for
> promotion to production.
> **Related:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md` (capability contract),
> `docs/EMAIL_DELIVERY_v2.0.0.md` (mail requirements)

## 1. Purpose and scope

Deploy the full application and its features to a publicly reachable AWS
environment that DMW stakeholders can access over HTTPS, without owning a
domain name yet.

**In scope:** container hosting, PostgreSQL, object storage, mail, queue,
scheduler, and the four optional feature groups (AI chatbot, Turnstile, Sentry,
plus core).

**Out of scope:** production go-live, custom domain, real OFW case data,
horizontal scaling, load testing.

**Environment classification:** staging. Synthetic and seeded data only. This
classification is a control, not a convenience — see §11.

## 2. Constraints that drove the design

| Constraint | Consequence |
|---|---|
| No domain owned yet | ACM cannot issue a certificate for an ALB's default `*.elb.amazonaws.com` hostname. This rules out the ECS Fargate + ALB architecture until a domain exists, because the app requires working HTTPS (`SESSION_ENCRYPT=true`, secure cookies). |
| Gmail SMTP required for now | The platform must permit outbound TCP 587. This eliminated Render, which blocks 25/465/587. |
| AWS App Runner unavailable | App Runner is closed to new customers as of this date, removing the one option that offered both a free HTTPS hostname and VPC-private database access. |
| Cost sensitivity for a pilot | NAT Gateway (~$32/mo) and ALB (~$17/mo) are avoided in the interim design. |

Only Lightsail Containers satisfies "public HTTPS with a valid certificate and
no domain" among the remaining AWS compute options.

## 3. Architecture

```
Internet
   │  HTTPS (AWS-managed certificate)
   │  https://bayanihan-staging.<guid>.ap-southeast-1.cs.amazonlightsail.com
   ▼
Lightsail Container Service   "bayanihan-staging"   power=Small  scale=1
   └─ single container from the project Dockerfile, port 8080
      supervisord: nginx + php-fpm + queue:work + schedule:work
        │                                  │
        │ TLS 5432 (forced)                │ HTTPS (no VPC peering required)
        ▼                                  ▼
   Amazon RDS PostgreSQL 17          Amazon S3  bayanihan-staging-files
   db.t4g.micro, encrypted            private, versioned
   publicly accessible (see §11)        ├─ case documents and referral attachments
        │                               └─ audit archive bundles
        │
        └── Gmail SMTP 587 STARTTLS ──► OTP / MFA / notification / feedback mail
```

### 3.1 `scale=1` is a correctness requirement, not a cost choice

`docker/supervisord.conf` runs `schedule:work` inside the web container. At
scale ≥2 the scheduler runs on every node, double-executing retention and
archive jobs and corrupting retention evidence — the failure
`DEPLOYMENT_GUIDE_v3.0.0.md` §8 and §13 explicitly warn against. Horizontal
scaling requires extracting the scheduler into a separate single-instance
service first. Until then, scale is pinned to 1.

### 3.2 Why RDS rather than the Lightsail managed database

RDS costs about the same and is strictly better here:

- Encryption at rest included; the $15 Lightsail database tier has none
- PostgreSQL 17, matching the capability contract exactly
- Automated backups with point-in-time recovery
- **It is the database that survives promotion.** When compute moves to ECS
  Fargate, this instance stays and only the security group changes. Choosing
  the Lightsail database would mean migrating data later.

## 4. Capability contract mapping

Against `DEPLOYMENT_GUIDE_v3.0.0.md` §1:

| # | Capability | Satisfied by | Notes |
|---|---|---|---|
| C1 | Container runtime | Lightsail Small (0.5 vCPU / 1 GB) | Below the ≥1 vCPU baseline; acceptable for staging traffic, upgradeable to Medium with no downtime |
| C2 | HTTPS ingress | Lightsail default domain | HTTPS only; forwards `X-Forwarded-*`; routes to 8080 |
| C3 | Relational database | RDS PostgreSQL 17 | `pgcrypto`, `pg_trgm` to be verified (§12) |
| C4 | Object storage | S3, private, versioned | Serves both case files and audit archives |
| C5 | Key-value store | **Not provisioned** — documented C5 fallback | `CACHE_STORE=database`, `QUEUE_CONNECTION=database`. Lightsail containers have no persistent volumes, so a Redis sidecar would lose queued jobs on every redeploy |
| C6 | Outbound mail | Gmail SMTP 587 | Staging only, see §9 |
| C7 | Scheduled execution | In-container `schedule:work` | Safe at scale=1 only |
| C8 | Secret management | Lightsail deployment environment variables | Runtime injection; never baked into the image |
| C9 | Backup and recovery | RDS automated backups + PITR | Restore drill required, see §11 |
| C10 | Log egress | Container stdout/stderr → Lightsail container logs | 500 GB/mo transfer quota included |
| C11 | Error/APM ingest | Sentry (free tier) | Enabled |
| C12 | Avatar CDN | Not used | Falls back to object storage |
| C13 | Bot protection | Turnstile with documented test keys | Swap to real keys at promotion |

## 5. Resource inventory

Region **ap-southeast-1** (Singapore) throughout.

| Resource | Identifier | Configuration |
|---|---|---|
| Container service | `bayanihan-staging` | power Small, scale 1, public endpoint on the app container, health check `/up` |
| RDS instance | `bayanihan-staging-db` | PostgreSQL 17, db.t4g.micro, 20 GB gp3, encrypted, publicly accessible, `rds.force_ssl=1`, 7-day backup retention |
| S3 bucket | `bayanihan-staging-files` | Private, versioning on, SSE-S3, no public access |
| IAM user (app) | `bayanihan-staging-app` | Least privilege: `s3:GetObject`, `PutObject`, `DeleteObject`, `ListBucket` scoped to the bucket ARN only |
| IAM user (deploy) | `bayanihan-deploy` | Lightsail container push/deploy permissions. Created so deployment stops using the account root |

### 5.1 Root account remediation

The account currently authenticates as root (`arn:aws:iam::677206905439:root`).
This design creates two scoped IAM users so that neither deployment nor the
running application uses root credentials. Enabling MFA on root and moving
human access to IAM Identity Center remains an open action outside this spec's
scope.

## 6. Environment variable contract

Deltas from `.env.example` for this environment. Everything else keeps its
documented default.

```env
APP_ENV=staging
APP_DEBUG=false
APP_KEY=<generated>
APP_URL=https://bayanihan-staging.<guid>.ap-southeast-1.cs.amazonlightsail.com

# Database (C3)
DB_CONNECTION=pgsql
DB_HOST=<rds-endpoint>
DB_PORT=5432
DB_DATABASE=one_window
DB_USERNAME=<role>
DB_PASSWORD=<32+ char secret>
DB_SSLMODE=require

# Cache / queue / session (C5 fallback — no Redis)
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true

# Object storage (C4)
FILESYSTEM_DISK=object-storage
STORAGE_DRIVER=s3
STORAGE_ACCESS_KEY=<iam-key>
STORAGE_SECRET_KEY=<iam-secret>
STORAGE_REGION=ap-southeast-1
STORAGE_BUCKET=bayanihan-staging-files
STORAGE_ENDPOINT=https://s3.ap-southeast-1.amazonaws.com
STORAGE_ROOT=uploads

# Audit archive (C4) — MUST be s3 or archives land on ephemeral disk
AUDIT_ARCHIVE_DISK=audit-archives
AUDIT_ARCHIVE_DRIVER=s3
AUDIT_ARCHIVE_ROOT=audit-archives
AUDIT_RETENTION_DAYS=365

# Mail (C6) — staging only
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<gmail-address>
MAIL_PASSWORD=<16-char app password>
MAIL_FROM_ADDRESS=<same gmail address>

# Migrations run as a release step, never at boot
RUN_MIGRATIONS=false

# Edge
TRUSTED_PROXIES=*

# Features
MFA_LOGIN_CHALLENGE_ENABLED=true
AI_CHATBOT_ENABLED=true
OPENROUTER_API_KEY=<key>
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
SENTRY_LARAVEL_DSN=<dsn>
BROADCAST_CONNECTION=log
MALWARE_SCANNER=null
```

Notes:

- `TRUSTED_PROXIES=*` because the Lightsail ingress IP range is not published
  and not static. Client IPs arrive via `X-Forwarded-For` from an AWS-managed
  proxy. This is looser than the CIDR list in `.env.example` and is recorded as
  an accepted staging deviation (§11).
- `MAIL_FROM_ADDRESS` must equal `MAIL_USERNAME`; Gmail rejects or rewrites
  mismatched senders.
- `BROADCAST_CONNECTION=log` because nothing in `app/` broadcasts — the Pusher
  configuration is vestigial and needs no credentials.

### 6.1 Object-storage configuration hazards

Three properties of `config/filesystems.php` make the values above mandatory
rather than stylistic. All three were found during spec self-review and would
have caused silent failures in production.

| Line | Behaviour | Consequence if defaults are used |
|---|---|---|
| `filesystems.php:105` | `audit-archives` driver defaults to `local` | **Audit archives would be written to the container's ephemeral disk and lost on every redeploy**, voiding the immutability claim that `DEPLOYMENT_GUIDE_v3.0.0.md` §13 states cannot be retrofitted. `AUDIT_ARCHIVE_DRIVER=s3` is required. |
| `filesystems.php:70` | `object-storage` root defaults to `storage_path('app/storage')` | Under the S3 driver, `root` is a key prefix, so every object would be stored beneath a literal `/var/www/html/storage/app/storage/` prefix. `STORAGE_ROOT` is set explicitly to avoid this. |
| `filesystems.php:72` and `:114` | `use_path_style_endpoint` is hardcoded `true` | Path-style addressing requires the **regional** S3 endpoint. `STORAGE_ENDPOINT` is therefore mandatory, not optional, even for real AWS S3. Path-style remains functional on AWS S3 but is a deprecated addressing mode; if AWS ever completes its deprecation, these two disks need `use_path_style_endpoint` made configurable. |

Both disks resolve `bucket` from `STORAGE_BUCKET`, so case files and audit
archives share one bucket, separated by the `uploads/` and `audit-archives/`
prefixes. The `audit-archives` disk sets `'throw' => true`, so archive write
failures surface loudly rather than silently — verification item 7 in §10
depends on that.

## 7. Feature configuration

| Feature | State | Rationale |
|---|---|---|
| Core: auth/OTP/MFA, cases, referrals, uploads, reports, audit chain, queue, scheduler | On | No third-party dependency |
| MFA login challenge | On | Fresh database, so the "revoke enrolled sessions first" caveat in `.env.example` does not apply |
| AI chatbot | On | SQLite FTS5 index on ephemeral container disk; `docker-entrypoint.sh` rebuilds it at every boot, which is correct for a stateless container |
| Turnstile | On, test keys | Documented always-pass keys avoid needing a Cloudflare account for staging |
| Sentry | On | Primary diagnostic channel for first deploy; ISO 27001 A.8.16 expects monitoring |
| Broadcasting | `log` | Unused by the application |
| Malware scanning | Off | ClamAV is not in the image. Recorded as a control gap, §11 |

## 8. Deployment procedure

Migrations are deliberately removed from container boot. `docker-entrypoint.sh:32`
runs `migrate --force ... || true`, which swallows failures and would serve
traffic on a broken schema.

```
1. Verify prerequisites (§12) before provisioning anything billable
2. Provision RDS, S3, and both IAM users
3. Apply migrations from a controlled shell against RDS:
      php artisan migrate --force
   Read the exit code. Do not continue on failure.
   Seed reference data:  php artisan db:seed
4. docker build -t bayanihan:<git-sha> .
5. aws lightsail push-container-image  (requires the lightsailctl plugin)
6. Create the container service deployment:
      public endpoint = app container, port 8080, health check path /up
7. Health-gate: poll https://<endpoint>/up until HTTP 200
8. Run the smoke path (§10)
```

The `case_category` pivot migration is pending. On a fresh database it has
nothing to backfill, so the quiesced-window runbook in
`DEPLOYMENT_GUIDE_v3.0.0.md` §7 reduces to "apply before serving traffic",
which step 3 satisfies by construction.

### 8.1 Redeployment

Repeat steps 4–7. Migrations only when the release contains new ones, always as
step 3 before the new image is deployed. Lightsail retains the last 50
deployment versions, satisfying the guide's rollback requirement without a
separate registry retention policy.

## 9. Mail: Gmail SMTP as a deliberate temporary measure

`EMAIL_DELIVERY_v2.0.0.md` §2 prohibits consumer-mailbox SMTP for production.
This design uses it for staging only, with the limitations recorded rather than
discovered later:

| Limitation | Impact |
|---|---|
| Requires 2-Step Verification and a 16-character App Password | Account password will not authenticate |
| ~500 messages/day (2,000 on Workspace) | Adequate for pilot OTP volume; will fail a load test |
| Sender must be the Gmail address | Mail appears to come from a person, not the institution |
| No bounce, complaint, or suppression handling | `email_logs` is the only delivery evidence |
| No SPF/DKIM/DMARC for the sending identity | Higher spam placement risk |

Switching to a real transport once a domain exists is a `MAIL_*` change plus a
redeploy. No code change. `config/mail.php` already defines `smtp`, `ses`, and
`resend` mailers; note that `resend/resend-php` is **not** in `composer.json`,
so the Resend path would require adding that package, whereas SES works through
the AWS SDK already present via `league/flysystem-aws-s3-v3`.

## 10. Verification — definition of done

Derived from `DEPLOYMENT_GUIDE_v3.0.0.md` §10 and §12.

1. `GET /up` returns 200 from outside AWS
2. Log in, receive the OTP **by email**, complete MFA enrolment
3. Create a case with a file upload; confirm the object lands in S3
4. Create a referral; confirm the notification job drains from the `jobs` table
5. Export a report (exercises PostgreSQL-specific reporting queries)
6. `php artisan audit:verify` passes
7. `php artisan audit:archive` writes a bundle to the S3 audit archive prefix
8. `email_logs` records send and failure events
9. Scheduler last-run timestamp advances within one minute
10. Chatbot answers one question from indexed helpdesk content
11. Turnstile challenges on login
12. A deliberately triggered exception appears in Sentry

Deployment is not "done" until all twelve pass. Items 2, 3, and 7 are the ones
that most commonly fail first (SMTP egress, S3 credentials, archive disk).

## 11. Standards-readiness check

This is not a certification artefact, so the compliance check applies. Items
below are what an ISO 9001 / ISO 27001 / SOC 2 / DPTM assessor would test.

| Requirement | Standard | Status | Action required before production |
|---|---|---|---|
| Network segregation of the database | ISO 27001 A.8.20/A.8.22 | ⚠️ RDS is publicly accessible. Lightsail container egress IPs are not static, so the security group cannot be narrowed to a source. Mitigated by forced TLS and a 32+ character password | **Blocking.** Move compute into the VPC (ECS Fargate) and make RDS private |
| No production data in test environments | A.8.31 | ✅ Enforced: synthetic and seeded data only | Maintain the rule; it is what makes the row above tolerable |
| Encryption in transit | A.8.24, DPTM | ✅ HTTPS ingress, `DB_SSLMODE=require`, `rds.force_ssl=1` | None |
| Encryption at rest | A.8.24, DPTM | ✅ RDS encrypted, S3 SSE-S3 | None |
| Sender authentication (SPF/DKIM/DMARC) | A.8.24 | ⚠️ Not possible without a domain | **Blocking.** Domain plus verified transactional transport |
| Malware scanning of uploads | A.8.7 | ⚠️ Absent — `MALWARE_SCANNER=null`, no ClamAV in the image | Add a scanning sidecar or API-based scanner |
| Privileged access / no root usage | A.8.2, SOC 2 CC6.1 | ⚠️ Account is root-only today; this design adds two scoped IAM users | Enable MFA on root, move humans to IAM Identity Center |
| Backup, restore, tested recovery | A.8.13 | ⚠️ RDS backups and PITR configured; **no restore drill yet** | Schedule and evidence a restore drill via `scripts/restore-test.sh` |
| Audit log immutability | A.8.15/A.8.16 | ✅ Archives on versioned S3 | Consider Object Lock for true immutability |
| Documented repeatable deployment | ISO 9001 8.5.1, CC8.1 | ✅ This spec, versioned with changelog | Increment on every change |
| Change authorisation | A.8.32, CC8.1 | ✅ Manual production trigger already in CI | Retain approval evidence per run |
| Secrets outside source control | A.8.24, CC6.1 | ✅ Runtime env injection only | Rotate on personnel change and record it |
| Capacity/performance monitoring | A.8.6 | ⚠️ Lightsail metrics available; no thresholds or alert routing defined | Define thresholds and on-call routing |
| Trusted proxy configuration | A.8.20 | ⚠️ `TRUSTED_PROXIES=*` is broader than policy because Lightsail ingress ranges are unpublished | Narrow to ALB subnets after moving to Fargate |
| Supplier assurance | A.5.19–A.5.22, CC9.2 | ⚠️ AWS, Google (mail), Sentry, OpenRouter, Cloudflare are all processors | Record each in the supplier register with processing terms and residency. Note the chatbot sends helpdesk queries to OpenRouter |

**Decisions taken here specifically to avoid audit-time rework:**

- Audit archives go to S3 from day one; a local-disk fallback would void the
  immutability claim and cannot be retrofitted onto already-archived periods.
- Sessions and queue state are external (database) from day one, so scaling
  past one instance later is a configuration change, not a data-loss event.
- The database is RDS from day one, so promotion moves compute only. Data never
  migrates, which removes the highest-risk step from the production cutover.

## 12. Pre-provisioning verification

Each must be settled **before** creating billable resources. Two could change
the design.

| # | Question | Why it matters | If it fails |
|---|---|---|---|
| V1 | Is outbound TCP 587 permitted from a Lightsail container service? | Login is email-gated; if SMTP egress is blocked the environment is unusable | Switch to Amazon SES, verifying a single email address as the identity (works without a domain, sandbox restricts recipients to verified addresses) |
| V2 | Can `pgcrypto` and `pg_trgm` be created on RDS PostgreSQL 17? | Required by C3 | Both are standard RDS-supported extensions; failure would be unexpected and would need investigation before migrating |
| V3 | Does the image include `pdo_sqlite` with FTS5? | AI chatbot retrieval depends on it; the Dockerfile does not explicitly install it | Add `pdo_sqlite` to the Dockerfile extension list, or disable the chatbot |
| V4 | Is Docker Desktop available locally, and the `lightsailctl` plugin installed? | Needed to build and push the image | Build in GitHub Actions and push to ECR, then deploy from ECR |
| V5 | Does the account qualify for RDS Free Tier? | `db.t4g.micro` is free for 750 h/month in the first 12 months | Cost rises by ~$13/mo; no design change |

V1 is the highest-risk item. It is testable with a single connection attempt
from a throwaway deployment before RDS exists.

## 13. Cost

| Item | Monthly (USD) |
|---|---|
| Lightsail container service, Small | 15 |
| RDS `db.t4g.micro`, 20 GB gp3, encrypted | ~13 (0 if Free Tier applies) |
| S3 storage and requests | ~1 |
| Sentry, OpenRouter, Turnstile | 0 (free tiers) |
| **Total** | **~29** (~16 with RDS Free Tier) |

Upgrading the container to Medium (1 vCPU / 2 GB) adds $25.

## 14. Promotion path to production

Triggered by acquiring a domain. Nothing below requires application changes.

```
Route 53 (domain) ──► ALB (ACM certificate) ──► ECS Fargate (same image)
                                                   │           │
                                          RDS (made private)  S3 (unchanged)
                                                   │
                                             SES (verified domain, SPF/DKIM/DMARC)
```

Steps, in order:

1. Register the domain; create the hosted zone
2. Request an ACM certificate; validate by DNS
3. Create the ECS cluster, task definition (same image, port 8080), and service
   in public subnets with a public IP and no inbound security-group rules
4. Create the ALB with the ACM certificate; target the Fargate service; health
   check `/up`
5. **Extract the scheduler** from `supervisord.conf` into its own single-task
   service before raising the web service above one task
6. Make RDS private; restrict its security group to the Fargate task security
   group; remove public accessibility
7. Verify the domain in SES; publish SPF, DKIM, and DMARC; switch `MAIL_*`
8. Narrow `TRUSTED_PROXIES` to the ALB subnet CIDRs
9. Replace Turnstile test keys with real keys
10. Add malware scanning for uploads
11. Run the §10 smoke path against the new environment, then cut DNS
12. Close every ⚠️ row in §11

Estimated production cost: ~$48–55/mo, or ~$35 with RDS Free Tier.

## 15. Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-07-27 | Pre-release self-review corrected three object-storage defects before first commit: added the missing `AUDIT_ARCHIVE_DRIVER=s3` (archives would otherwise be written to ephemeral disk), set `STORAGE_ROOT` explicitly (the default resolves to a local filesystem path used as an S3 key prefix), and documented that the hardcoded `use_path_style_endpoint` makes the regional `STORAGE_ENDPOINT` mandatory. Recorded as §6.1. |
| 1.0.0 | 2026-07-27 | Initial design. Selected Lightsail Containers + RDS PostgreSQL 17 + S3 for staging after eliminating Render (blocks SMTP egress), AWS App Runner (closed to new customers), and ECS Fargate + ALB (ACM cannot issue for a default ALB hostname, so it requires a domain). Redis omitted in favour of the documented C5 database fallback because Lightsail containers have no persistent volumes. Migrations moved out of container boot. Gmail SMTP accepted as an explicitly temporary staging transport. Documented five pre-provisioning verifications and a full promotion path to ECS Fargate. |
