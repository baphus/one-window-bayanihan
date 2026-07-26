# AWS Staging Deployment Runbook

> **Version:** 1.0.0 | **Updated:** 2026-07-27
> **Environment:** staging — **synthetic and seeded data only, no real OFW case data**
> **Design:** `docs/superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md`
> **Plan:** `docs/superpowers/plans/2026-07-27-aws-staging-deployment-v1.0.0.md`
> **Platform-neutral contract:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md`

## 1. What this environment is

A single-node AWS Lightsail container service running the project Dockerfile
image behind Lightsail's managed HTTPS endpoint, with state in Amazon RDS
PostgreSQL and Amazon S3. It exists so the application can be demonstrated and
validated on a public URL **before** a domain name is acquired.

It is deliberately **not** production. Section 6 lists what must change first.

## 2. Resource inventory

Account `677206905439`, region `ap-southeast-1` throughout. No secrets appear
in this document; they live in the project password manager.

| Resource | Identifier |
|---|---|
| Container service | `bayanihan-staging` (power `small`, scale `1`) |
| Public URL | `https://bayanihan-staging.m317gkz7tgsqm.ap-southeast-1.cs.amazonlightsail.com` |
| Container registry | `677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan` |
| Database | RDS `bayanihan-staging-db` — PostgreSQL 17.10, `db.t4g.micro`, 20 GB gp3, encrypted at rest |
| DB parameter group | `bayanihan-staging-pg17` (`rds.force_ssl=1`) |
| DB security group | `sg-06daf5a3e3d21b024` |
| Object storage | S3 `bayanihan-staging-files` — private, versioned, SSE-S3 |
| IAM (app runtime) | `bayanihan-staging-app` — S3 object access on that bucket only |
| IAM (CI push) | `bayanihan-ci-ecr` — ECR push to the `bayanihan` repository only |
| IAM (deploy) | `bayanihan-deploy` — Lightsail deployment only |
| GitHub secrets | `AWS_ECR_ACCESS_KEY_ID`, `AWS_ECR_SECRET_ACCESS_KEY` |

Bucket prefixes: `uploads/` for case documents and referral attachments,
`audit-archives/` for audit archive bundles.

Query volatile values rather than trusting a copy:

```powershell
# Public URL
aws lightsail get-container-services --service-name bayanihan-staging --region ap-southeast-1 --query "containerServices[0].url" --output text
# Database endpoint
aws rds describe-db-instances --db-instance-identifier bayanihan-staging-db --region ap-southeast-1 --query "DBInstances[0].Endpoint.Address" --output text
```

## 3. Architecture notes that constrain operations

Three properties are load-bearing. Breaking any of them causes data problems,
not just downtime.

| Constraint | Why |
|---|---|
| **`scale` must stay at `1`** | `docker/supervisord.conf` runs `schedule:work` inside the web container. A second node double-runs retention and archive jobs, corrupting retention evidence. Scaling horizontally requires extracting the scheduler into its own single-instance service first. |
| **`RUN_MIGRATIONS` must stay `false`** | `docker/php/docker-entrypoint.sh:32` runs migrations with `\|\| true`, so a failed migration is swallowed and the app serves traffic on a broken schema. Migrations are a deliberate release step (§4). |
| **`APP_KEY` must never change** | The app uses `EncryptedString` and `EncryptedDate` casts. Rotating the key makes existing encrypted column data permanently unreadable. |

There is **no Redis**. Cache, queue, and sessions all use the database driver —
the documented C5 fallback in `DEPLOYMENT_GUIDE_v3.0.0.md`, chosen because
Lightsail containers have no persistent volumes and a Redis sidecar would lose
queued jobs on every redeploy.

## 4. Deploying a new release

```
1. Push to a deploy/** branch or main  →  .github/workflows/build-image.yml
   builds the image and pushes it to ECR tagged with the git SHA.
2. If the release contains migrations, apply them FIRST and read the exit code:
      php artisan migrate --force --no-interaction
   Abort the deploy on any non-zero exit.
3. Update the image tag in the deployment JSON, then:
      aws lightsail create-container-service-deployment \
        --cli-input-json file://<path>/app-deployment.json --region ap-southeast-1
4. Health-gate before announcing:
      Invoke-WebRequest -Uri "<public-url>/up" -UseBasicParsing
   Expect HTTP 200.
```

ECR tags are **immutable**, so a tag always identifies exactly one image. That
is what makes "redeploy the previous tag" a trustworthy rollback.

The deployment JSON contains secrets and must never be committed. Keep it
outside the repository.

## 5. Operations

### Logs

```powershell
aws lightsail get-container-log --service-name bayanihan-staging --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text
```

Application logs go to stdout (`LOG_CHANNEL=stderr`) and are collected by
Lightsail. The in-app viewer at `/admin/system/logs` reads the container's
ephemeral file and will not survive a redeploy — prefer the command above.

### Running a one-off artisan command

Lightsail has no `exec`. Run the command as a temporary deployment: copy the
deployment JSON, remove the `publicEndpoint` block, set

```json
"command": ["sh","-c","php artisan <command> && echo TASK_DONE && sleep 600"]
```

deploy it, read the log, then **redeploy the real application**. Used for
`audit:verify`, `audit:archive`, and `migrate` when local access is unavailable.

### Rollback

```powershell
aws lightsail get-container-service-deployments --service-name bayanihan-staging --region ap-southeast-1 --query "deployments[].[version,state,containers.app.image]" --output text
```

Lightsail retains the last 50 deployment versions. Redeploy a previous
version's image tag. If the release included a destructive or backfilling
migration, restore the database from a snapshot instead — `migrate:rollback` is
a schema tool, not an application rollback.

### Database

```powershell
# Snapshot before any risky change
aws rds create-db-snapshot --db-instance-identifier bayanihan-staging-db --db-snapshot-identifier bayanihan-staging-pre-<change> --region ap-southeast-1
# Confirm automated backups
aws rds describe-db-instances --db-instance-identifier bayanihan-staging-db --region ap-southeast-1 --query "DBInstances[0].[BackupRetentionPeriod,LatestRestorableTime]" --output text
```

Connections require TLS (`rds.force_ssl=1`); `DB_SSLMODE=require` is mandatory
and a plaintext connection attempt is rejected by the server.

### Queue and scheduler

Both run inside the app container under supervisord. Confirm via the log for
`queue-worker` and `scheduler` entering RUNNING. Queue depth lives in the
`jobs` table and failures in `failed_jobs` — a persistently non-zero `jobs`
count means the worker is not consuming.

## 6. Known deviations and what must change before production

Every item here is tracked in the design spec's §11 standards-readiness check.

| # | Item | Standard | Required change |
|---|---|---|---|
| 1 | **RDS reachable from `0.0.0.0/0`.** Lightsail container services cannot join a VPC and their egress IPs are neither static nor published, so no narrower source is expressible. Mitigated by forced TLS, a 40-character password, and the no-real-data rule. | ISO 27001 A.8.20/A.8.22 | **Blocking.** Move compute to ECS Fargate in a VPC and make RDS private. |
| 2 | **No SPF/DKIM/DMARC**, and mail sends from a personal mailbox rather than an institutional identity. | A.8.24 | **Blocking.** Acquire a domain and a verified transactional transport. |
| 3 | **Backup retention is 1 day, not 7.** `create-db-instance` rejected 7 with `FreeTierRestrictionError`; the account is on the AWS Free Tier plan. Still meets the ≤24 h RPO in capability C9. | A.8.13 | Raise to 7+ once the account plan is upgraded. |
| 4 | **Upload malware scanning absent** (`MALWARE_SCANNER=null`; ClamAV is not in the image). | A.8.7 | Add a scanning sidecar or an API-based scanner. |
| 5 | **Account root still in use** for administration. Scoped IAM users exist for CI, the app runtime, and deployment, but not for humans. | A.8.2, SOC 2 CC6.1 | Enable MFA on root; move human access to IAM Identity Center. |
| 6 | **No restore drill performed.** Automated backups alone are not recovery evidence. | A.8.13 | Run `scripts/restore-test.sh` into a scratch database and record the result. |
| 7 | **No alert thresholds or on-call routing** for queue depth, `/up` latency, or error rate. | A.8.6 | Define thresholds and routing. |
| 8 | **`TRUSTED_PROXIES=*`** is broader than the CIDR list policy prescribes, because Lightsail's ingress range is unpublished. | A.8.20 | Narrow to the ALB subnet CIDRs after the Fargate move. |
| 9 | **Lightsail environment variables are not a secret store.** They are readable by anyone with console access or `lightsail get-container-services`, and are not KMS-encrypted. The database password and mail credentials sit there in plaintext. | A.8.24, CC6.1 | Move to AWS Secrets Manager with the Fargate migration. |
| 10 | **Supplier register incomplete.** AWS, Google (mail), Sentry, OpenRouter, and Cloudflare all process project data. Note that the chatbot transmits helpdesk queries to OpenRouter. | A.5.19–A.5.22, CC9.2 | Record each with processing terms and data residency. |

## 6a. Bugs found during this deployment

Six defects surfaced only when the application ran as a non-root container behind
a real proxy. Three were fixed in application code; they are **platform-independent**
and would recur on any host using this image and nginx config.

| # | File | Defect | Status |
|---|---|---|---|
| 1 | `Dockerfile` | `HOME` unset, so supervisord's `user=www-data` children inherited `HOME=/root` (mode 700). libpq probes for an optional client certificate under `$HOME/.postgresql/` on every connection; the traversal returned EACCES and libpq treated it as fatal. `queue-worker` and `scheduler` crash-looped inside their `startsecs` window and **every queued notification and scheduled job silently never ran**. php-fpm was unaffected because it takes `HOME` from the pool user's passwd entry. | Fixed — `ENV HOME=/tmp` (`c056a21`) |
| 2 | `docker/nginx/conf.d/default.conf` | No `fastcgi_buffer_size`/`fastcgi_buffers`, so nginx used its 4k default for upstream headers. Laravel's long `Content-Security-Policy` plus `Set-Cookie` for session, remember-me, and XSRF overflow it, producing `upstream sent too big header` and **502 on every authenticated page**. Self-reinforcing: the 502 aborts the response before `Set-Cookie` arrives, so the session never establishes and reloading cannot recover. | Fixed — 32k × 16 buffers (`8f4d1d3`) |
| 3 | `routes/auth.php` | GET and POST `/login` both named `login`. Tolerated at runtime, but `route:cache` cannot serialize it and fails with `Unable to prepare route [login] for serialization`. `docker-entrypoint.sh` swallows it with `\|\| true`, so the app ran **without cached routes on every boot** and the build-pipeline step in `DEPLOYMENT_GUIDE_v3.0.0.md` §6 could never succeed. | Fixed — POST renamed `login.store` (`c056a21`). `route:cache` exits 0; 34 login tests pass, 160 assertions |
| 4 | `database/seeders/ProductionSeeder.php:26` | Hardcodes `Hash::make('P@ssw0rd!')` for `admin@bayanihan.gov.ph`. On a publicly reachable URL this is an open ADMIN account, and **every environment seeded from this file shares the password**. | Password rotated in this environment to a 32-character random value; **the seeder itself is unchanged and still needs fixing** — it should read an env var or generate and print a random password |
| 5 | `Dockerfile:53` | `pecl install redis` is unpinned. A build failed with `No releases available for package "pecl.php.net/redis"` on a line that had succeeded minutes earlier; a rerun passed. | Open — pin the version for reproducible builds (ISO 27001 A.8.30) |
| 6 | Monitoring assumption | `/up` does not touch the database. It returned 200 continuously while the queue worker and scheduler could not connect, and while every authenticated page returned 502. **Health checks gated only on `/up` will report this environment healthy when it is not.** | Open — alert on queue depth, `failed_jobs`, and scheduler last-run, not just `/up` |

The common thread: defects 1, 2, and 6 are all invisible to an unauthenticated
health check. Any future environment should be validated by signing in and
exercising a queued job, not by polling `/up`.

## 7. Cost

| Item | Monthly (USD) |
|---|---|
| Lightsail container service, Small | 15 |
| RDS `db.t4g.micro`, 20 GB gp3, encrypted | ~13, potentially covered by Free Tier credits |
| S3 storage and requests | ~1 |
| Sentry, OpenRouter, Turnstile | 0 (free tiers) |
| **Total** | **~29** |

Delete the container service to stop its charge — it bills whether enabled or
disabled, and whether or not a deployment exists.

## 8. Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-07-27 | Initial runbook for the AWS Lightsail staging environment: resource inventory, release and rollback procedures, one-off command pattern, and the ten known deviations with their required production changes. Records the Free Tier backup-retention constraint and the Lightsail-environment-variable secret-storage limitation, neither of which was anticipated in the design spec. |
