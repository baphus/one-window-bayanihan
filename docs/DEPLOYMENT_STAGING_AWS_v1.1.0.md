# AWS Staging Deployment Runbook

> **Version:** 1.1.0 | **Updated:** 2026-07-27 | **Supersedes:** `DEPLOYMENT_STAGING_AWS_v1.0.0.md`
> **Environment:** staging — **synthetic and seeded data only, no real OFW case data**
> **Platform-neutral contract:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md`
> **Design:** `docs/superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md`
> **Plan:** `docs/superpowers/plans/2026-07-27-aws-staging-deployment-v1.0.0.md`

## 0. How this version differs from 1.0.0

v1.0.0 stood up the environment and left the custom domain as "planned, not yet
applied" (its §6c). This version turns that plan into an executable procedure
(§2) and ships the application changes that the cutover depends on (§3).

Everything in v1.0.0 that is not restated here still applies unchanged —
resource inventory, release and rollback procedure, one-off command pattern,
statement and lock timeouts, and cost. **Read v1.0.0 first.** This document is a
delta, not a replacement.

Three deviations recorded in v1.0.0 §6 are closed by this version (#8 partially,
plus the two open items from §6a). §4 restates the full deviation table.

---

## 1. Prerequisites for the cutover

| Requirement | Value |
|---|---|
| Domain | `owbap.app`, registered at Name.com |
| DNS authority | Stays at Name.com — no Route 53 or Lightsail DNS zone needed |
| Staging hostname | `staging.owbap.app` |
| Production hostname | `r7.owbap.app` (out of scope here) |
| AWS CLI | Required locally; **not installed on the authoring machine**, so every command below is unverified against the live account and must be run by an operator |
| Region | `ap-southeast-1` throughout |

A Lightsail certificate binds to a single container service, so staging and
production get **separate certificates**. Beyond hygiene, this stops staging from
being able to serve production's hostname (ISO 27001 A.8.31).

---

## 2. Custom domain cutover

### 2.1 Request the certificate

```powershell
$REGION = "ap-southeast-1"
$SERVICE = "bayanihan-staging"

aws lightsail create-certificate `
  --certificate-name owbap-staging `
  --domain-name staging.owbap.app `
  --region $REGION
```

No CSR is involved — Lightsail issues and renews the certificate. Ownership is
proved with a DNS record.

### 2.2 Read the validation record

```powershell
aws lightsail get-certificates --certificate-name owbap-staging --region $REGION `
  --query "certificates[0].certificateDetail.domainValidationRecords" --output json
```

This returns a `name` and `value` to create as a **CNAME**.

> **Name.com appends the domain to the record host.** If the returned name is
> `_abc123.staging.owbap.app`, enter the host as `_abc123.staging` — *not* the
> full name. Entering the full name produces
> `_abc123.staging.owbap.app.owbap.app`, and validation then never completes
> with no error surfaced anywhere. This is the single most common way this
> procedure fails.

Leave the validation record in place permanently. Lightsail renews the
certificate automatically, but only while the record still resolves.

### 2.3 Wait for validation

```powershell
aws lightsail get-certificates --certificate-name owbap-staging --region $REGION `
  --query "certificates[0].certificateDetail.status" --output text
```

Proceed when this reads `ISSUED`. Typically minutes; allow up to an hour for DNS
propagation.

### 2.4 Attach the certificate to the service

```powershell
aws lightsail update-container-service `
  --service-name $SERVICE `
  --public-domain-names '{\"owbap-staging\":[\"staging.owbap.app\"]}' `
  --region $REGION
```

The service transitions to `UPDATING`. Wait for `READY`:

```powershell
aws lightsail get-container-services --service-name $SERVICE --region $REGION `
  --query "containerServices[0].state" --output text
```

### 2.5 Point DNS at the service

Get the platform hostname:

```powershell
aws lightsail get-container-services --service-name $SERVICE --region $REGION `
  --query "containerServices[0].url" --output text
```

At Name.com create a **CNAME** with host `staging` whose target is that hostname
(without `https://` and without a trailing slash).

A subdomain is used deliberately: a bare `owbap.app` cannot be a CNAME, and
Lightsail hands out a hostname rather than a static IP. Subdomains sidestep the
apex problem entirely — no ALIAS record and no URL forwarding.

### 2.6 Update `APP_URL` and redeploy

`APP_URL` is baked into the config cache by `docker-entrypoint.sh` at container
start, so it must be set in the deployment payload **before** the container
boots — it cannot be corrected afterwards without a redeploy.

Set `"APP_URL": "https://staging.owbap.app"` in the deployment JSON (see
`deploy/lightsail/README.md`), then deploy per v1.0.0 §4. Skipping this leaves
Inertia and Ziggy generating absolute links back to the Lightsail hostname.

### 2.7 Verify

```powershell
# TLS and reachability
Invoke-WebRequest -Uri "https://staging.owbap.app/up" -UseBasicParsing

# Deep check (see §3.4)
Invoke-WebRequest -Uri "https://staging.owbap.app/api/readyz" -UseBasicParsing `
  -Headers @{ "X-Monitoring-Token" = "<token>" }

# Confirm generated links use the custom hostname, not the Lightsail one
(Invoke-WebRequest -Uri "https://staging.owbap.app" -UseBasicParsing).Content `
  -match "cs\.amazonlightsail\.com"   # expect False
```

> **`.app` is on the HSTS preload list, subdomains included.** Browsers refuse
> plain HTTP to `staging.owbap.app` outright, so the hostname will not load *at
> all* until the certificate is attached and DNS resolves. A connection error
> before §2.4 completes is expected, not a misconfiguration.

### 2.8 After cutover: staging becomes discoverable

The Lightsail default URL is effectively unguessable. `staging.owbap.app` is
not. With the database still reachable from `0.0.0.0/0` (§4 deviation #1) and
seeded data present, treat discoverability as a real change in exposure:

- Non-production responses now carry `X-Robots-Tag: noindex, nofollow` (§3.3).
- Keep the no-real-OFW-data rule firm.
- Consider edge authentication in front of staging.

---

## 3. Application changes shipped with this version

All five are platform-independent and would recur on any host running this
image.

### 3.1 The entrypoint no longer swallows failures

`docker/php/docker-entrypoint.sh` ran `config:cache`, `route:cache`,
`view:cache`, `event:cache`, and `migrate` each terminated with `|| true`. That
is how the duplicate `login` route name (v1.0.0 §6a defect 3) went unnoticed:
`route:cache` aborted on every boot and the app silently ran uncached.

The cache steps and the migration step now `exit 1` on failure. A container that
cannot cache its own configuration is misconfigured and must not proceed to
serve traffic.

`RUN_MIGRATIONS` still stays `false` — but the reason is now "migrations are a
deliberate release step" rather than "the entrypoint would hide the failure."

### 3.2 X-Forwarded-For can no longer be spoofed

**This closes a live vulnerability, not a theoretical one.**

`docker/nginx/conf.d/default.conf` passed `$proxy_add_x_forwarded_for` to PHP,
which *prepends the client's own* `X-Forwarded-For` value. Combined with
`trustProxies(at: '*')` (`bootstrap/app.php:46`) and `TRUSTED_PROXIES=*`, Laravel
trusts every hop and resolves the client to the **left-most** entry — the
attacker-supplied one.

A request to the still-public Lightsail URL carrying
`X-Forwarded-For: 203.0.113.9` therefore made the application believe that was
the client, defeating:

- login and API rate limiting (`RateLimiter` keys on the resolved IP),
- the `IpWhitelist` middleware,
- the client IP recorded in audit records by `LogContext`.

nginx now resolves a `$client_addr` variable to the **right-most** entry — the
address the platform load balancer itself observed and appended — and passes only
that. Client-supplied entries are discarded. The rate-limit zone key moved to the
same variable so nginx and Laravel agree on who the caller is.

This is deliberately independent of the load balancer's IP range, which Lightsail
does not publish. **Verify it after any platform change** — see §3.5.

### 3.3 Non-production environments are no longer indexable

`SecurityHeaders` now emits `X-Robots-Tag: noindex, nofollow` when
`APP_ENV !== production`. `robots.txt` cannot express this because one image
serves every environment; the header can, and it suppresses indexing rather than
merely requesting no crawl.

### 3.4 A readiness probe that can actually see failure

v1.0.0 §6a defect 6: `/up` does not touch the database. It returned 200
continuously while the queue worker and scheduler could not connect and every
authenticated page returned 502. **A health check gated only on `/up` reports
this environment healthy when it is not.**

`GET /api/readyz` checks the database, the scheduler heartbeat, the `jobs`
backlog, and `failed_jobs`, returning 503 when any threshold is crossed.
`routes/console.php` writes a scheduler heartbeat every minute so a dead
scheduler is detectable at all.

It requires an `X-Monitoring-Token` header and **404s when
`MONITORING_READINESS_TOKEN` is unset** — fail closed, so a forgotten secret
cannot expose backlog counts anonymously.

Keep the *platform* health check on `/up`. Pointing Lightsail's health check at a
deep probe turns a transient database blip into a container restart loop.

### 3.5 Verify client IP resolution

Run after the cutover, and again after any platform change:

```powershell
# 1. Baseline: what does the app think your IP is? Sign in, then read the
#    ip_address column of the newest audit row. It must be your real public IP.

# 2. Attempt a spoof against the Lightsail default URL (still public).
Invoke-WebRequest -Uri "https://<lightsail-url>/login" -UseBasicParsing `
  -Headers @{ "X-Forwarded-For" = "203.0.113.9" }

# 3. Re-read the newest audit row. It must STILL be your real IP.
#    If it reads 203.0.113.9, $client_addr is not taking effect — stop and fix
#    before treating rate limiting or IpWhitelist as controls.
```

Residual risk: this relies on the platform load balancer appending the observed
peer to `X-Forwarded-For`. If a future platform sets no such header, the value
arrives empty, Laravel falls back to `REMOTE_ADDR`, and every client collapses
into one rate-limit bucket — degraded but **not** spoofable. Step 3 detects both
outcomes.

### 3.6 The production seeder no longer resets rotated passwords

`database/seeders/ProductionSeeder.php` used `updateOrInsert` with a hardcoded
`Hash::make('P@ssw0rd!')`. v1.0.0 §6a defect 4 recorded the credential as
published in the repository and shared across environments, which was correct —
but it understated the consequence: because the write was an *upsert*,
**re-running the seeder silently reset the already-rotated staging administrator
password back to the published value.**

The seeder now updates only non-credential columns for an existing
administrator, and on first creation uses `ADMIN_SEED_PASSWORD` or mints a random
32-character secret and prints it once.

### 3.7 phpredis is pinned

`pecl install redis` was unpinned; a build failed with `No releases available for
package "pecl.php.net/redis"` on a line that had succeeded minutes earlier
(v1.0.0 §6a defect 5). Now `ARG PHPREDIS_VERSION=6.1.0`. The CI image build
asserts `redis` is present in `php -m`, so a bad pin fails the pipeline rather
than shipping.

---

## 4. Known deviations — updated

Renumbered from v1.0.0 §6, with status against this version.

| # | Item | Standard | Status |
|---|---|---|---|
| 1 | **RDS reachable from `0.0.0.0/0`.** Lightsail container services cannot join a VPC and their egress IPs are neither static nor published. Mitigated by forced TLS and a 40-character password. | A.8.20/A.8.22 | **Open — blocking for production.** Move compute into a VPC and make RDS private. |
| 2 | **No SPF/DKIM/DMARC.** | A.8.24 | **Partly unblocked.** The domain now exists; publish the records for the Resend sending identity. |
| 3 | Backup retention 1 day, not 7 (`FreeTierRestrictionError`). Still meets C9's ≤24 h RPO. | A.8.13 | Open — raise once the account plan is upgraded. |
| 4 | Upload malware scanning absent (`MALWARE_SCANNER=null`). | A.8.7 | Open. |
| 5 | Account root still used for administration. | A.8.2, CC6.1 | Open — enable MFA on root, move humans to IAM Identity Center. |
| 6 | No restore drill performed. | A.8.13 | Open — `scripts/restore-test.sh` exists on `deploy/aws-staging`; run it and record the result. |
| 7 | No alert thresholds or on-call routing. | A.8.6 | **Partly closed.** `/api/readyz` now exposes thresholds for queue backlog, failed jobs, scheduler staleness, and database reachability. Still needs an external monitor and routing. |
| 8 | `TRUSTED_PROXIES=*` broader than the CIDR policy prescribes. | A.8.20 | **Materially reduced.** The spoofing consequence is closed at nginx (§3.2); the setting itself remains `*` because the LB range is unpublished. |
| 9 | **Lightsail environment variables are not a secret store** — readable via `lightsail get-container-services`, not KMS-encrypted. DB password and mail credentials sit there in plaintext. | A.8.24, CC6.1 | Open — needs Secrets Manager, which needs the VPC move (#1). |
| 10 | Supplier register incomplete (AWS, Resend, Sentry, OpenRouter, Cloudflare). The chatbot transmits helpdesk queries to OpenRouter. | A.5.19–A.5.22, CC9.2 | Open. |
| 11 | **Single node, `scale=1`.** No HA, and contradicts `DEPLOYMENT_GUIDE_v3.0.0.md` §2 ("Production: ≥2 instances"). | A.5.29, A.8.14 | Open by design at this cost point. `RUN_SCHEDULER` / `RUN_QUEUE_WORKER` (§5) now make the split possible without an image change. |
| 12 | **Audit archive immutability not enforced.** The `audit-archives` disk implies WORM storage; the S3 bucket is versioned but has no Object Lock. | A.8.15, CC7.2 | Open. Object Lock must be enabled **at bucket creation** — retrofitting means a new bucket and a copy. |
| 13 | **Stale Render CI workflows.** `deploy-staging.yml`, `deploy-production.yml`, and `reset-staging-data.yml` target Render, not AWS. `reset-staging-data.yml` runs on a **daily cron** and issues a destructive `clear_all`. They no-op only because the Render vars are unset. | A.8.32 | Open — delete or repoint. Left in place here because removing deployment automation is an owner's decision. |

## 5. Scaling past one node

`scale` must stay at `1` while the scheduler lives in the web container. To scale
the web tier:

1. Web service: `RUN_SCHEDULER=false`, `RUN_QUEUE_WORKER=false`, scale freely.
2. A second single-instance service from the **same image**:
   `RUN_SCHEDULER=true`, `RUN_QUEUE_WORKER=true`, scale `1`, no public endpoint.

Both flags default to `true`, so existing single-node deployments are unaffected.
Running two services with `RUN_SCHEDULER=true` double-runs retention and audit
archiving, which corrupts retention evidence rather than merely duplicating work.

---

## 6. Standards-readiness check

Applied per project policy: this is a project document, not a certification
artefact, so it is checked for decisions that would need rework at audit.

**Would need rework before certification:**

| Finding | Standard | Rework cost if deferred |
|---|---|---|
| Publicly reachable RDS (#1) | A.8.20/A.8.22, CC6.6 | **High.** Compute must move into a VPC; that also unblocks #9. |
| Plaintext secrets in platform env (#9) | A.8.24, CC6.1 | Medium — coupled to #1. |
| No Object Lock on audit archives (#12) | A.8.15, CC7.2 | **High if deferred.** Object Lock is creation-time only; later means a new bucket and a verified copy. |
| Single node (#11) | A.5.29 | Low — cost, not architecture, now that the flags exist. |
| No restore drill (#6) | A.8.13 | Low, but it is *evidence*, and evidence cannot be back-dated. |
| Root account administration (#5) | A.8.2, CC6.1 | Low. |

**Built in, needs no retrofit:** RDS encryption at rest; forced TLS to the
database; scoped IAM users per function; immutable ECR tags; audit hash chain;
role-level statement and lock timeouts; fail-closed readiness probe;
non-spoofable client IP resolution.

**Documentation derivable from standards rather than authored:** the supplier
register (#10) follows ISO 27001 A.5.19–A.5.22 directly; the backup and restore
procedure (#3, #6) follows A.8.13; the logging and monitoring policy (#7) follows
A.8.15–A.8.16. Each is a template-fill from the control text plus this
environment's inventory, and could be generated without design input — but none
should be *published* without an owner's review, because each asserts
organisational commitments (retention periods, on-call rotas, processing terms)
that only the owner can make.

---

## 7. Changelog

| Version | Date | Change |
|---|---|---|
| 1.1.0 | 2026-07-27 | Turned v1.0.0 §6c's planned custom domain into an executable cutover procedure (§2), including the Name.com host-suffix trap, the `.app` HSTS-preload consequence, and post-cutover discoverability. Shipped seven application changes (§3): fail-fast entrypoint, non-spoofable `X-Forwarded-For`, `X-Robots-Tag` on non-production, `/api/readyz` deep probe with scheduler heartbeat, seeder no longer resetting rotated passwords, pinned phpredis, and `RUN_SCHEDULER`/`RUN_QUEUE_WORKER` flags. Added deviations #11–#13; #7 and #8 partly closed. Added `deploy/lightsail/` payload template and `build-image.yml`. |
| 1.0.0 | 2026-07-27 | Initial runbook for the AWS Lightsail staging environment: resource inventory, release and rollback procedures, one-off command pattern, and the ten known deviations with their required production changes. |
