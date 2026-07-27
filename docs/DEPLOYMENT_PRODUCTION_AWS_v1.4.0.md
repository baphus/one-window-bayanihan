# AWS Production Deployment Runbook

> **Version:** 1.4.0 | **Updated:** 2026-07-27
> **Supersedes:** `DEPLOYMENT_STAGING_AWS_v1.3.0.md` (and the v1.0–v1.2 chain)
> **Operative document.** Renamed from STAGING to PRODUCTION: there is no longer a
> staging environment. v1.1.0 §2 (custom domain cutover) still applies as procedure.
> **Platform-neutral contract:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md`

## 0. What changed from 1.3.0

Staging was decommissioned and a **production** environment was built in its place
with correctly-named resources. Two properties are materially better than any
earlier version of this runbook:

1. **The database is not on the public internet.** Verified, not assumed — see §3.
   This retires the deviation that every previous version called *blocking*.
2. **Search indexing is off until switched on**, so a provisioned-but-unlaunched
   public service cannot be indexed.

Production is **live and healthy** but **not launchable yet**: §7 lists two hard
blockers, both of which need action outside AWS.

---

## 1. Resource inventory

Account `677206905439`, region `ap-southeast-1`. No secrets in this document.

| Resource | Identifier |
|---|---|
| Container service | `bayanihan-production` — power `small` (0.5 vCPU / 1 GB), scale `1` |
| Platform URL | `https://bayanihan-production.m317gkz7tgsqm.ap-southeast-1.cs.amazonlightsail.com/` |
| Private domain | `bayanihan-production.service.local` |
| Intended hostname | `dmw7.owbap.app` (certificate `owbap-production`, **still PENDING_VALIDATION**) |
| Database | Lightsail `bayanihan-production-db` — PostgreSQL **17.10**, `micro_2_0`, encrypted, automated backups, **not publicly accessible** |
| Uploads bucket | S3 `bayanihan-production-files` — versioned, SSE-S3, public access blocked, **no** Object Lock |
| Audit archive bucket | S3 `bayanihan-production-audit-archives` — same, **plus Object Lock (GOVERNANCE, 365 days)** |
| Registry | `677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan` — immutable tags, scan on push |
| App runtime IAM user | `bayanihan-production-app` |
| CI role (OIDC) | `bayanihan-github-actions-ci` |
| Deploy role (OIDC) | `bayanihan-github-actions-deploy` |

**Deleted with staging:** container service `bayanihan-staging`, database
`bayanihan-staging-db`, buckets `bayanihan-staging-{files,audit-archives}`,
certificate `owbap-staging`, IAM user `bayanihan-staging-app`, and the `staging`
GitHub Environment. The `staging.owbap.app` DNS records at Name.com are now
dangling and should be removed there.

### Why two buckets

Object Lock is a bucket-wide setting. One shared bucket forces a choice between
immutable audit archives and a retention job (`storage:cleanup-orphans`,
`cases:purge-trashed`) that must be able to delete orphaned uploads. Two buckets
get both. The application already supported it — `AUDIT_ARCHIVE_BUCKET` is a
separate key from `STORAGE_BUCKET`.

**Object Lock can only be enabled at bucket creation.** If the audit bucket is
ever recreated without it, immutability is gone and the only remedy is a new
bucket plus a verified copy.

The app's IAM policy grants the audit bucket `PutObject`/`GetObject` but carries
an explicit **`Deny`** on `DeleteObject`, `DeleteObjectVersion`,
`PutObjectRetention`, `PutObjectLegalHold`, and `BypassGovernanceRetention`. The
application cannot weaken or delete its own audit evidence even with valid
credentials.

`GOVERNANCE` (not `COMPLIANCE`) at 365 days is deliberate: it blocks the
application absolutely while leaving an administrator able to correct a mistake.
Moving to `COMPLIANCE` is irreversible for the retention period and should follow
a confirmed records-retention policy rather than a guessed number.

---

## 2. Verified state

Everything below was measured, not assumed.

| Check | Result |
|---|---|
| `/up` | HTTP 200 |
| `/api/readyz` | HTTP 200 — `database: ok`, `scheduler: ok` (heartbeat 33 s), `queue_backlog: 0`, `failed_jobs: 0` |
| `/login` | HTTP 200, ~97 KB (Inertia + Vite assets render) |
| `X-Robots-Tag` | `noindex, nofollow` |
| Database TLS | in use (`pg_stat_ssl`), `rds.force_ssl=1` |
| `lock_timeout` / `statement_timeout` | `10s` / `2min`, verified on a fresh connection |
| Seeded rows | `users` 1 (ADMIN), `agencies` 9, everything else 0 |

The scheduler heartbeat matters more than it looks: it is the only evidence that
`schedule:work` can reach the database. `/up` answers 200 without touching the
database at all, which is exactly how a previous environment reported healthy
while the worker and scheduler were dead.

---

## 3. The database is not reachable from the internet

Measured from a machine outside AWS, before and after:

| | public ON | public OFF |
|---|---|---|
| DNS resolves to | `13.251.66.68` (public) | `172.26.7.196` (RFC1918) |
| TCP 5432 from WAN | connected | blocked |
| Read `users` table | authenticated | connect failed |

With public access **off**, the deployed container still reports
`{"database":{"status":"ok"}}` from `/api/readyz`, which runs a real `SELECT 1`.
So a Lightsail container service *can* reach a Lightsail managed database on its
private endpoint.

This retires the finding every earlier version listed as blocking — *"reachable
from `0.0.0.0/0` … no narrower source is expressible"* — without moving to ECS
Fargate or a VPC.

**Moving the database to RDS would not have achieved this.** The constraint was
never the database engine. It was that Lightsail compute has no VPC presence and
no static egress address, so any database it talks to must accept the world, and a
security group has no usable source to permit. Removing the public endpoint is the
only lever Lightsail offers, and it is sufficient.

**Consequence:** no external runner can reach the database, so migrations run
inside the container (`RUN_MIGRATIONS=true`, `migrate --force --isolated`) before
nginx accepts traffic. `--isolated` holds an atomic lock so concurrent container
starts cannot migrate twice. A failure refuses the boot, the previous deployment
keeps serving, and the release fails visibly.

Migrations must stay backward compatible with the version being replaced — the old
container serves while the new one migrates. **Expand first, contract in a later
release.**

---

## 4. Client IP resolution is not spoofable — verified

nginx passes only the right-most `X-Forwarded-For` entry (the address the platform
load balancer observed) to PHP. Confirmed against live production:

- request carrying `X-Forwarded-For: 203.0.113.9` → that address appears **nowhere**
  in the container log
- the real client address `143.44.164.17` **is** recorded

This is what makes login rate limiting, the `IpWhitelist` middleware, and the
client IP in audit records trustworthy. Re-run this check after any platform
change — the procedure is in v1.1.0 §3.5.

---

## 5. CI/CD

| Workflow | Trigger |
|---|---|
| `ci.yml` | PR + push to `main`. Pint, `composer audit`, `npm audit`, asset build, **all four production cache commands**, `migrate --pretend`, migrate, backend + frontend tests |
| `build-image.yml` | push to `main` / `deploy/**`, manual. Build, assert PHP extensions + FTS5 + nginx + supervisord parse, assert the entrypoint fails closed **and** starts when configured, push to ECR, report scan findings |
| `deploy-production.yml` | **manual only**, `PRODUCTION` confirmation phrase, `production` Environment |

There is deliberately **no automatic deployment**. Production is the only
environment, and a government case-management service should not deploy as a side
effect of a merge.

The `production` Environment requires reviewer `baphus`, and the OIDC deploy role
is assumable **only** from that environment — which makes the approval a technical
control rather than a UI convention.

`deploy-staging.yml` and the three original Render workflows are deleted. One of
those Render workflows ran a **daily destructive `clear_all` cron**, inert only
because its variables were unset.

### First pipeline deploy

`workflow_dispatch` requires the workflow to exist on the **default branch**, so
`deploy-production.yml` becomes dispatchable only once this branch merges. Until
then deployment is by CLI, which is the documented procedure anyway.

---

## 6. Defects found and fixed during this work

Each was pre-existing and platform-independent; each would recur on any host.

| # | Defect | Consequence |
|---|---|---|
| 1 | `config/sentry.php` set `traces_sampler` to a **closure**, which cannot be serialized | `config:cache` aborted on **every boot in every environment** since the file was written. The old entrypoint's `\|\| true` hid it, so the app always ran with uncached config. |
| 2 | nginx passed `$proxy_add_x_forwarded_for`, which *prepends the client's own* value | Any request could set its own apparent IP, defeating login rate limiting, `IpWhitelist`, and audit-log client IPs. |
| 3 | `ProductionSeeder` used `updateOrInsert` with a hardcoded `Hash::make('P@ssw0rd!')` | Re-running the seeder silently reset an already-rotated administrator to a credential published in this repository. |
| 4 | Entrypoint swallowed cache and migration failures with `\|\| true` | A failed migration served traffic on a broken schema. |
| 5 | Entrypoint did not check required configuration | None of the cache commands need `APP_KEY`, so a container with no credentials started cleanly and failed on the first request touching encrypted data. "It booted" was never evidence it was configured. |
| 6 | `X-Robots-Tag` gated on `APP_ENV` | Setting `APP_ENV=production` silently made an unlaunched site indexable. |
| 7 | HSTS sent twice (nginx + middleware), nginx's copy lacking `preload` | Ambiguous to scanners; `.app` is on the HSTS preload list. Fix awaits the next image build. |
| 8 | `pecl install redis` unpinned | A build failed on a line that had succeeded minutes earlier. |

An unplanned bonus verification: a deployment with a non-existent image tag failed,
and the previous deployment **kept serving** (`/up` 200 throughout). Rollback
safety is real, not theoretical.

---

## 7. Blockers before this can serve real users

Both are outside AWS.

### 7.1 Mail does not deliver — BLOCKING

`MAIL_MAILER=log`. OTP and MFA codes are written to the container log instead of
being sent, so **no user can complete sign-in**, and any code is readable by anyone
with console access. Resend refuses to send from an unverified domain.

Required: publish SPF, DKIM, and DMARC for `owbap.app` at Name.com, set
`RESEND_KEY` as a `production` Environment secret, then set the `MAIL_MAILER`
variable to `resend` and redeploy.

Until this is done the no-real-data rule still applies, despite `APP_ENV=production`.

### 7.2 The custom domain is not validated — BLOCKING for `dmw7.owbap.app`

Certificate `owbap-production` is `PENDING_VALIDATION`. Add at Name.com:

| Purpose | Type | Host | Answer |
|---|---|---|---|
| Validation | CNAME | `_0e80ec2bb14a2b3caa98dfe7781e0bba.dmw7` | `_896f48b916167ad91c3749917afee72f.jkddzztszm.acm-validations.aws` |
| Traffic | CNAME | `dmw7` | `bayanihan-production.m317gkz7tgsqm.ap-southeast-1.cs.amazonlightsail.com` |

Name.com's **Host field is relative** — it appends `.owbap.app`. Entering the full
name produces `…dmw7.owbap.app.owbap.app` and validation then never completes,
with no error surfaced anywhere. This is the most common way this step fails.

Leave the validation record in place permanently; renewal depends on it.

Then attach the certificate, add the domain assignment, and redeploy with
`APP_URL=https://dmw7.owbap.app` — Inertia and Ziggy build absolute URLs from it.

`.app` is on the HSTS preload list, so browsers refuse plain HTTP: the hostname
will not load *at all* until the certificate is attached. That is expected.

### 7.3 Go-live switch

`SEARCH_INDEXING_ENABLED` is `false`. Set the `production` Environment variable to
`true` and redeploy when the service should become publicly discoverable. This is
deliberately separate from `APP_ENV`.

---

## 8. Cost

| Item | Monthly (USD) |
|---|---|
| Container service, `small` | 15 |
| Lightsail PostgreSQL, `micro_2_0` | 15 |
| S3, two buckets at low volume | ~1 |
| Certificate, ECR repository | 0 |
| **Total** | **~$31** |

About **six months** on $200 of credits. Both the container service and database
bill whether enabled or not and whether a deployment exists or not — they must be
**deleted**, not stopped, to stop charging.

---

## 9. Standards-readiness check

**Closed:**

| Item | Standard |
|---|---|
| Database not reachable from the internet (§3) | A.8.20/A.8.22, CC6.6 |
| Client IP non-spoofable, verified live (§4) | A.8.20 |
| Audit archives immutable; app denied delete and retention-bypass | A.8.15, CC7.2 |
| Database encrypted at rest, TLS verified in use, automated backups | A.8.24, A.8.13 |
| Buckets versioned, encrypted, public access blocked | A.8.24 |
| CI/CD via OIDC; deploy credentials bound to an approval-gated Environment | A.5.17, A.8.2, CC6.1, CC6.3 |
| Pre-deploy database snapshot in the deploy path | A.8.13 |
| Deploy gated on a deep readiness probe, not just `/up` | A.8.6, A.8.16 |
| Immutable image tags, scan-on-push, pinned phpredis | A.8.8, A.8.30 |
| Seeded administrator password random and unpublished | A.5.17 |
| Destructive scheduled workflow removed | A.8.32 |
| Unlaunched service not indexable | — |

**Still requiring action:**

| Finding | Standard | Note |
|---|---|---|
| **Account root used for administration.** Every provisioning action here ran as root. | A.8.2, CC6.1 | Highest remaining risk. Enable MFA on root; move humans to IAM Identity Center. |
| Legacy IAM users `bayanihan-ci-ecr` and `bayanihan-deploy` still exist with live keys | A.5.17 | Superseded by OIDC, which is proven working. Not deleted here because their other consumers are unknown — verify, then remove. |
| No SPF/DKIM/DMARC; mail undeliverable | A.8.24 | §7.1 |
| Plaintext secrets in Lightsail container environment | A.8.24, CC6.1 | Readable via `lightsail get-container-services`. Lightsail has no secret store; needs a VPC move for Secrets Manager. |
| Single node, no HA | A.5.29 | Cost decision. `RUN_SCHEDULER`/`RUN_QUEUE_WORKER` allow the split without an image rebuild. |
| No restore drill | A.8.13 | Automated backups are on but untested. Backups are not recovery evidence. |
| No alert routing | A.8.6 | `/api/readyz` exposes the thresholds; an external monitor must consume it. |
| Object Lock is GOVERNANCE/365d, not COMPLIANCE | A.8.15 | Deliberate — see §1. Escalate once a records-retention policy is confirmed. |
| Upload malware scanning absent | A.8.7 | `MALWARE_SCANNER=null`. |
| Supplier register incomplete | A.5.19–A.5.22 | AWS, Resend, Sentry, OpenRouter, Cloudflare. The chatbot transmits helpdesk queries to OpenRouter. |
| `TRUSTED_PROXIES=*` | A.8.20 | Consequence closed at nginx (§4); the value stays `*` because the load balancer range is unpublished. |

**Derivable from the standards rather than authored:** supplier register
(A.5.19–A.5.22), backup and restore procedure (A.8.13), logging and monitoring
policy (A.8.15–A.8.16). Each is a template-fill from the control text plus this
inventory and could be generated without design input — but none should be
*published* unreviewed, because each asserts organisational commitments (retention
periods, on-call rotas, processing terms) only the owner can make.

---

## 10. Changelog

| Version | Date | Change |
|---|---|---|
| 1.4.0 | 2026-07-27 | Decommissioned staging; built production with correctly-named resources. **Took the database off the public internet** and verified both halves empirically, retiring the blocking deviation without a VPC move. Verified client-IP spoofing is rejected on the live service. Migrations moved into the container with `--isolated`. Added an explicit search-indexing go-live switch. Fixed the `config/sentry.php` closure that had broken `config:cache` on every boot since the file was written, and made the entrypoint validate required configuration. Production Environment gated on a named reviewer. Documented mail and domain validation as the two remaining launch blockers. |
| 1.3.0 | 2026-07-27 | Recorded the rebuilt Lightsail staging environment, Lightsail managed database, and two-bucket Object Lock split. |
| 1.2.0 | 2026-07-27 | Recorded that the original environment no longer existed; replaced the CI/CD suite; migrated CI auth to OIDC. |
| 1.1.0 | 2026-07-27 | Custom domain cutover procedure and seven application changes. |
| 1.0.0 | 2026-07-27 | Initial runbook for the original AWS Lightsail staging environment. |
