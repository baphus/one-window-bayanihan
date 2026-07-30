# AWS Production Deployment & Operations Runbook

> **Version:** 1.6.0 | **Updated:** 2026-07-28 | **Supersedes:** `DEPLOYMENT_PRODUCTION_AWS_v1.5.0.md`
> **Operative document.** v1.4.0 remains the reference for how the environment was
> verified and the defect log (its §2, §3, §4, §6). v1.5.0 added the operations
> manual — §3 onward. This version corrects the Resend secret name and records the
> release gate added after it caused a production outage.
> **Platform-neutral contract:** `docs/DEPLOYMENT_GUIDE_v3.0.0.md`

## 0. What changed from 1.5.0

**A live outage, and the gate that should have caught it.**

`MAIL_MAILER` was flipped to `resend` and the API key was set as a secret named
`RESEND_KEY` — the name **this runbook and the deploy workflow both used**. The
application reads `RESEND_API_KEY` (`config/services.php`). The container
therefore held a valid 36-character key under a name nothing read,
`config('services.resend.key')` resolved to `null`, and
`MailManager::createResendTransport()` called `Resend::client(null)`.

`Mail::to()` resolves the transport **eagerly**, so this threw a `TypeError`
before anything reached the queue. Every intake OTP, MFA challenge and password
reset returned **500** to real users:

```text
production.ERROR: Resend::client(): Argument #1 ($apiKey) must be of type string,
null given, called in .../Illuminate/Mail/MailManager.php on line 323
  url: https://dmw7.owbap.app/intake/verify-email  method: POST
```

Two things made this worse than a typo:

1. **Every gate passed.** `/up` never touches mail. `/api/readyz` did not either.
   The deployment was reported healthy while outbound mail was completely dead.
2. **No test did catch it.** The suite calls `Mail::fake()` throughout, and the
   fake intercepts the send path — so no functional test ever reached the
   transport construction that threw. (`MailFake` does still forward unknown
   calls to the real manager, so a test that deliberately asked for the
   transport *would* have seen it. Nothing did.)

Fixed by standardising on `RESEND_API_KEY` — the name the application, the
`.env.example`, `mail:verify-transport` and `EMAIL_DELIVERY` all already used —
and by adding four gates at different layers:

| Layer | Gate | Catches |
|---|---|---|
| CI | `DeploymentMailEnvContractTest` | The deploy artefacts and `config/services.php` naming the variable differently |
| Pipeline | "Verify the mail configuration is coherent" step in `deploy.yml`, before the snapshot | `MAIL_MAILER=resend` with an empty/unset `RESEND_API_KEY` **secret** — the half of the drift a file-to-file test cannot see |
| Container | `mail:verify-transport --no-send` in the entrypoint, before migrations | Any mailer that cannot be constructed; refuses to start |
| Runtime | `mail` check in `/api/readyz` | The same, continuously, so a monitor sees it |

The container and readiness checks share one definition
(`app/Support/MailTransportHealth`). It does two things, because neither alone
is sufficient:

- **Builds the real transport**, catching `MAIL_MAILER=resendd`, an undefined
  driver, and any driver added after the check was written.
- **Then checks a credential map** for blank values, because `Resend::client('')`
  does *not* throw — an empty string satisfies the type hint and constructs a
  client that 401s on every send. An **absent** variable arrives as `null` and
  throws; a **present-but-empty** one does not. Both are "no key".

**What it does not cover:** the credential map lists only `resend`. `ses`
without credentials and `smtp` with a blank host both construct successfully and
pass all four gates, because those clients defer credential resolution to the
first send. Adding a driver to `MAIL_MAILER` means adding it to
`MailTransportHealth::REQUIRED_CREDENTIALS` and to the `deploy.yml` guard —
`DeploymentMailEnvContractTest` fails if you do the first without the second.

> **Renaming the secret is an operator action the pipeline cannot do for you.**
> Set `RESEND_API_KEY` on the Environment **before** the first deploy that
> carries this change, then delete the old one — a live 36-character Resend key
> sitting under a name nothing reads is credential sprawl, not a fallback:
>
> ```bash
> gh secret set    RESEND_API_KEY --env production --repo $R --body '<the same key>'
> gh secret delete RESEND_KEY     --env production --repo $R
> ```

Everything else here was verified against the live account, not designed on paper.

---

## 1. Environment at a glance

| | |
|---|---|
| Public URL | `https://dmw7.owbap.app` |
| Platform URL | `https://bayanihan-production.m317gkz7tgsqm.ap-southeast-1.cs.amazonlightsail.com/` |
| Container service | `bayanihan-production` — power `small`, scale `1` |
| Database | Neon `ep-solitary-sun-az2zhddv` — PostgreSQL 17.10, serverless, autoscaling, pgvector 0.8.0 |
| Buckets | `bayanihan-production-files` (uploads) · `bayanihan-production-audit-archives` (Object Lock) |
| Registry | `677206905439.dkr.ecr.ap-southeast-1.amazonaws.com/bayanihan` — immutable tags |
| Region / account | `ap-southeast-1` / `677206905439` |
| Cost | ~$31/month |

There is **no staging environment**. It was decommissioned; production is the only
environment.

### The AWS CLI is not on PATH

Installed but not on `PATH` on the current operator machine:

```
C:\Users\<user>\AppData\Local\Programs\Amazon\AWSCLIV2\aws.exe
```

Every `aws …` command below assumes you have resolved that, e.g. in PowerShell:

```powershell
Set-Alias aws "$env:LOCALAPPDATA\Programs\Amazon\AWSCLIV2\aws.exe"
```

---

## 2. Pipeline shape

| Workflow | Trigger | Does |
|---|---|---|
| `ci.yml` | PR + push to `main` | Pint, `composer audit`, `npm audit`, asset build, all four production cache commands, `migrate --pretend`, migrate, backend + frontend tests |
| `build-image.yml` | push to `main` / `deploy/**`, manual | Builds the image, asserts PHP extensions + FTS5 + nginx + supervisord parse, asserts the entrypoint fails closed **and** starts when configured, pushes to ECR tagged with the commit SHA, reports scan findings |
| `deploy-production.yml` | **manual only** | Confirmation phrase → `production` Environment approval → `deploy.yml` |
| `deploy.yml` | `workflow_call` | Mail-config guard → snapshot → deploy → wait READY → `/up` gate → `/api/readyz` gate (now includes a mail-transport check) |

The container adds a gate the pipeline cannot: the entrypoint runs
`mail:verify-transport --no-send` before migrations and exits non-zero on a
mailer that cannot deliver, so a broken mail configuration fails the release
instead of reaching users. See §0.

Build and deploy are separate on purpose. Merging code does **not** ship it.

`build-image.yml` ignores `docs/**` and `**.md`, so a documentation-only change
does not trigger a build.

---

## 3. Deploying a new version

**Step 1 — get the image tag.** Merging to `main` builds and pushes automatically.
The tag is the commit SHA.

```bash
gh run list --repo baphus/one-window-bayanihan --workflow build-image.yml --limit 5 \
  --json headSha,status,conclusion --jq '.[] | "\(.headSha[0:40]) \(.status) \(.conclusion)"'
```

Or read it straight from the registry:

```powershell
aws ecr describe-images --repository-name bayanihan --region ap-southeast-1 `
  --query "sort_by(imageDetails,&imagePushedAt)[-1].imageTags[0]" --output text
```

**Step 2 — dispatch the deploy.**

```bash
gh workflow run "Deploy Production" --repo baphus/one-window-bayanihan \
  -f image_tag=<commit-sha> -f confirm=PRODUCTION
```

Or Actions → **Deploy Production** → Run workflow. The `confirm=PRODUCTION` phrase
is deliberate friction; a misclick cannot ship.

**Step 3 — approve.** The `production` Environment requires a named reviewer. This
is a technical control, not a UI convention: the OIDC deploy role's trust policy
only permits `repo:baphus/one-window-bayanihan:environment:production`, so
credentials are unobtainable from a job that is not running in that environment.

**Step 4 — the pipeline gates for you.** Pre-deploy database snapshot → submit
deployment → wait for `READY` → `/up` must return 200 → `/api/readyz` must return
200. A failed gate turns the run red; the previous deployment continues serving.

> **Never confuse the image tag with an abbreviated SHA.** Passing a tag that does
> not exist produces a deployment that fails on image pull. Harmless — the previous
> deployment keeps serving, verified in practice — but it wastes a cycle and blocks
> the next deploy until it finishes failing. Copy the full 40-character tag.

---

## 4. Changing configuration

Lightsail bakes environment variables **into a deployment**. There is no "edit and
restart". Changing configuration therefore means a new deployment — but **no code
change and no rebuild**: update GitHub, then redeploy the *same* image tag.

The GitHub `production` Environment is the source of truth.

```bash
R=baphus/one-window-bayanihan

# non-secret
gh variable set SEARCH_INDEXING_ENABLED --env production --repo $R --body true
gh variable set MAIL_MAILER             --env production --repo $R --body resend
gh variable set MAIL_FROM_ADDRESS       --env production --repo $R --body noreply@owbap.app

# secret
gh secret   set RESEND_API_KEY              --env production --repo $R --body '<key>'

# apply
gh workflow run "Deploy Production" --repo $R -f image_tag=<same-sha> -f confirm=PRODUCTION
```

### Values that must not drift

| Key | Rule |
|---|---|
| `APP_KEY` | **Never change it.** `EncryptedString` and `EncryptedDate` make a wrong key indistinguishable from data corruption, and existing columns become permanently unreadable. |
| `RUN_MIGRATIONS` | Keep `true`. See §5 — nothing outside the platform can reach the database. |
| `RUN_SCHEDULER` | `true` on exactly **one** service. Two schedulers double-run retention and audit archiving, which corrupts retention evidence rather than merely duplicating work. |
| `STORAGE_USE_PATH_STYLE` | `false` for Amazon S3. The application default is `true`, which suits MinIO and Supabase but breaks S3 virtual-hosted addressing. |
| `AUDIT_ARCHIVE_BUCKET` | Must stay the **separate** Object Lock bucket. Pointing it at the uploads bucket silently loses audit immutability. |
| `APP_URL` | Must match the hostname served. Inertia and Ziggy build absolute URLs from it — a stale value sent every generated link to the platform hostname while TLS looked perfectly fine. |

### Go-live switches

Two flags are deliberately **not** derived from `APP_ENV`, so that "this is
production" and "this is open to the public" stay separate decisions:

| Flag | Currently | Flip when |
|---|---|---|
| `SEARCH_INDEXING_ENABLED` | `false` — sends `X-Robots-Tag: noindex` and a matching robots meta tag | The service should be publicly discoverable |
| `MAIL_MAILER` | **`resend`** — flipped. Was `log`, which wrote OTP/MFA codes to the container log so no user could sign in | Already flipped. Requires `RESEND_API_KEY` — see §0 for what happens when it is set under the wrong name |

---

## 5. Migrations

**They run automatically, inside the container, at startup** —
`migrate --force --isolated`, before nginx accepts traffic.

**You cannot run them from a laptop or from CI.** The database has no public
endpoint; it resolves to an RFC1918 address and port 5432 is closed to the
internet. That is the property that retired the worst compliance finding, and this
is its cost.

What the arrangement buys:

- A failed migration means the container never becomes healthy, so Lightsail keeps
  the previous deployment active. The release fails **visibly** instead of
  half-applying.
- `--isolated` takes an atomic cache lock (the `database` cache store implements
  `LockProvider`), so concurrent container starts cannot migrate twice.

What it requires of you:

> **Migrations must be backward compatible with the version being replaced.** The
> old container serves traffic while the new one migrates. **Expand in one release,
> contract in a later one** — never drop or rename a column in the same release
> that stops using it.

To preview what a release will apply, read the `ci.yml` run for that commit: it
executes `migrate --pretend` before migrating.

---

## 6. Running a one-off command

Lightsail has **no `exec`**, and a container service has only **one active
deployment**. A one-off command therefore replaces the running application.

> **This takes production down for roughly 3–5 minutes.** Treat it as a maintenance
> window, not a routine tool.

```powershell
# 1. Copy the deployment payload and modify it.
#    - remove the "publicEndpoint" block
#    - replace the container "command"
#      "command": ["sh","-c","php artisan audit:verify && echo TASK_DONE && sleep 300"]

# 2. Deploy it.
aws lightsail create-container-service-deployment `
  --cli-input-json file://<modified>.json --region ap-southeast-1

# 3. Read the result.
aws lightsail get-container-log --service-name bayanihan-production `
  --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text

# 4. REDEPLOY THE REAL APPLICATION. Step 4 is not optional.
```

### Prefer these instead

**Most scheduled work needs no manual run.** `schedule:work` is live in the
container: `helpcenter:sync`, `logs:cleanup`, `audit:archive`, `audit:prune`,
`audit:verify`, `storage:cleanup-orphans`, `cases:purge-trashed`,
`documents:prune` and the scheduler heartbeat all fire on their own. Confirm the
scheduler is alive via `/api/readyz` — a stale `scheduler.age_seconds` is the
signal that scheduled jobs are silently not running.

**Several operations are already in the admin UI**, with no downtime:

| Need | Where |
|---|---|
| Maintenance mode | `/admin/maintenance` (toggle) |
| Application logs | `/admin/logs`, `/admin/logs/download` |
| Data export | `/admin/data-export` |
| System settings | `/admin/system-settings` |
| Audit trail + export | `/audit-logs`, `/audit-logs/export` |
| Mail delivery state | `/admin/email-logs` |

**For frequent shell-level work, add a dedicated admin service.** A second
Lightsail container service (`nano`, ~$7/month) from the **same image**, with no
public endpoint and `RUN_SCHEDULER=false` / `RUN_QUEUE_WORKER=false`, gives a
throwaway target for one-off commands at **zero production downtime**. It reaches
the same private database. Not provisioned today — a cost decision, not a
technical obstacle.

---

## 7. Observability

```powershell
# container log (application logs go to stdout/stderr via LOG_CHANNEL=stderr)
aws lightsail get-container-log --service-name bayanihan-production `
  --container-name app --region ap-southeast-1 --query "logEvents[].message" --output text

# shallow health — this is what Lightsail's health check probes
curl https://dmw7.owbap.app/up

# deep health — database, scheduler heartbeat, queue backlog, failed jobs
curl -H "X-Monitoring-Token: <token>" https://dmw7.owbap.app/api/readyz
```

**Do not gate the platform health check on `/api/readyz`.** `/up` is shallow by
design; pointing Lightsail's probe at a deep check turns a transient database blip
into a container restart loop.

**And do not trust `/up` alone as an operational signal.** It never touches the
database. In an earlier environment it returned 200 continuously while the queue
worker and scheduler could not connect at all and every authenticated page
returned 502. `/api/readyz` exists specifically to catch that class of failure:

```json
{"status":"ok","failing":[],"checks":{
  "database":{"status":"ok"},
  "scheduler":{"status":"ok","age_seconds":26,"threshold_seconds":300},
  "queue_backlog":{"status":"ok","count":0,"threshold":100},
  "failed_jobs":{"status":"ok","count":0,"threshold":25}}}
```

It returns **503** when any threshold is breached, and **404** when
`MONITORING_READINESS_TOKEN` is unset — fail closed, so a forgotten secret cannot
expose backlog counts anonymously.

The in-app log viewer reads the container's ephemeral file and **will not survive a
redeploy**. Prefer `get-container-log`. Neither is a retention mechanism — shipping
logs off the platform is still outstanding (§10).

---

## 8. Rollback

ECR tags are **immutable**, so a tag always identifies exactly one image. That is
what makes redeploying a previous tag trustworthy rather than a guess.

```bash
gh workflow run "Deploy Production" --repo baphus/one-window-bayanihan \
  -f image_tag=<previous-sha> -f confirm=PRODUCTION
```

Deployment history, including which image each version ran:

```powershell
aws lightsail get-container-service-deployments --service-name bayanihan-production `
  --region ap-southeast-1 --query "deployments[].[version,state,containers.app.image]" --output text
```

Lightsail retains the last 50 deployment versions.

> **If the release contained a destructive or backfilling migration, do not roll
> back by redeploying.** Restore the pre-deploy snapshot instead.
> `migrate:rollback` is a schema tool, not an application rollback, and it cannot
> undo a data backfill.

```powershell
aws lightsail get-relational-database-snapshots --region ap-southeast-1 `
  --query "relationalDatabaseSnapshots[].[name,createdAt,state]" --output text
```

---

## 9. What is deliberately not possible

| Not possible | Why |
|---|---|
| SSH or `exec` into the container | Lightsail does not offer it. §6 is the workaround. |
| Connect to the database from outside AWS | Private endpoint — the property that closed the worst compliance finding. |
| Change environment variables without redeploying | Lightsail bakes them into a deployment. |
| Deploy without approval | The deploy OIDC role is assumable only from the `production` Environment. |
| Run migrations from CI | Follows from the private database. |
| Recreate the audit bucket with Object Lock | Object Lock is **creation-time only**. Losing that bucket means a new bucket plus a verified copy. |

---

## 10. Outstanding

Carried forward from v1.4.0 §9, unchanged unless noted.

**Blocking a public launch:**

1. ~~**Mail does not deliver.**~~ **Resolved in 1.6.0.** `MAIL_MAILER=resend`, the
   Resend domain for `owbap.app` is verified (DKIM at `resend._domainkey`; SPF TXT
   and `feedback-smtp.ap-northeast-1.amazonses.com` MX on the `support`
   subdomain), and `RESEND_API_KEY` is read under the name the pipeline sets.
   **Outstanding:** no DMARC record is published for `owbap.app`. Gmail and Yahoo
   have required one from bulk senders since February 2024. Add
   `_dmarc TXT "v=DMARC1; p=none; rua=mailto:<address>"`, monitor the aggregate
   reports, then tighten to `p=quarantine`.
2. **`SEARCH_INDEXING_ENABLED=false`.** Deliberate; flip at go-live.

**Highest remaining security risk:**

3. **Account root is used for administration.** Every provisioning action for this
   environment ran as root. Enable MFA on root and move human access to IAM
   Identity Center.
4. **Legacy IAM users `bayanihan-ci-ecr` and `bayanihan-deploy` still hold live
   access keys.** Superseded by OIDC, which is proven working. Not deleted because
   their other consumers are unknown — verify, then remove.
5. **Operator credentials live in `~/.owb-secrets/`** on one machine: `APP_KEY`,
   database password, monitoring token, S3 key, administrator passwords. That
   folder is currently the **only** copy of the production `APP_KEY`, and losing it
   makes every encrypted column permanently unreadable. Move it into the password
   manager.

**Operational gaps:**

6. No alert routing — `/api/readyz` exposes thresholds but nothing consumes it.
7. No restore drill. Automated backups are enabled but untested; backups are not
   recovery evidence.
8. Log retention — nothing ships container logs off the platform.
9. Single node, no HA. `RUN_SCHEDULER` / `RUN_QUEUE_WORKER` make the split possible
   without an image rebuild; it is a cost decision.
10. Apex `owbap.app` and `www.owbap.app` do not resolve. Only `dmw7` exists. An
    apex cannot be a CNAME, so this needs registrar URL forwarding or CNAME
    flattening.
11. Plaintext secrets in Lightsail container environment variables, readable via
    `lightsail get-container-services`. Lightsail has no secret store.
12. Upload malware scanning absent (`MALWARE_SCANNER=null`).
13. Supplier register incomplete — AWS, Resend, Sentry, OpenRouter, Cloudflare.

---

## 11. Standards-readiness check

Applied per project policy: this is a project document, not a certification
artefact.

**Controls this document makes operable rather than merely designed:**

| Practice | Standard |
|---|---|
| Change deployed only through an approved, gated pipeline | A.8.32, CC8.1 |
| Pre-deploy snapshot before every schema change | A.8.13 |
| Documented, tag-based rollback with immutable artefacts | A.8.32, CC8.1 |
| Release gated on a deep readiness probe, not a shallow ping | A.8.6, A.8.16 |
| Separation of "is production" from "is public" (§4 go-live switches) | A.8.31 |
| Configuration held outside the image, per environment | A.8.9 |

**Rework flagged by this document:** items 3–5 and 6–8 in §10. Items 6 and 7 are
*evidence* gaps rather than technical ones, and evidence cannot be produced
retroactively — a restore drill run next quarter does not demonstrate recoverability
this quarter. Schedule both before an assessment window rather than during it.

**Derivable from the standards rather than authored:** supplier register
(A.5.19–A.5.22), backup and restore procedure (A.8.13), logging and monitoring
policy (A.8.15–A.8.16). Each is a template-fill from the control text plus this
inventory and could be generated without design input — but none should be
*published* unreviewed, because each asserts organisational commitments (retention
periods, on-call rotas, processing terms) only the owner can make.

---

## 12. Changelog

| Version | Date | Change |
|---|---|---|
| 1.6.0 | 2026-07-28 | **Production outage fix.** The Resend API key was shipped as `RESEND_KEY` while `config/services.php` reads `RESEND_API_KEY`, so `Resend::client(null)` threw a `TypeError` and every intake OTP, MFA challenge and password reset returned 500 — while `/up` and `/api/readyz` both reported the release healthy. **Correction:** standardised on `RESEND_API_KEY` across `deploy.yml`, `app-deployment.template.json` and this runbook. **Corrective action — four gates** (§0): (1) `DeploymentMailEnvContractTest` pins the artefacts to the name the application reads and pins the pipeline guard to `MailTransportHealth::REQUIRED_CREDENTIALS`; (2) a "Verify the mail configuration is coherent" step in `deploy.yml`, ahead of the snapshot, catches an empty Environment secret before anything is touched; (3) the container entrypoint runs `mail:verify-transport --no-send` before migrations and refuses to start; (4) `/api/readyz` gained a `mail` check. (2)–(4) share one definition in `app/Support/MailTransportHealth`, which both constructs the real transport and rejects blank credentials. **Also:** recorded that `MAIL_MAILER=resend` is live and the Resend domain is verified (DKIM on the root domain, SPF+MX on `support`); DMARC still unpublished. |
| 1.7.0 | 2026-07-31 | **Migrated production database from Lightsail managed PostgreSQL to Neon serverless PostgreSQL.** Lightsail managed databases do not support custom extensions (pgvector, etc.), blocking the chatbot embeddings feature. Neon provides pgvector 0.8.0, autoscaling, scale-to-zero, and database branching. Connection uses the direct endpoint (`ep-solitary-sun-az2zhddv.c-3.ap-southeast-1.aws.neon.tech`), not the pooler endpoint (PgBouncer transaction mode cannot handle multi-statement DDL migrations). The `DB_HOST` in `app-deployment.template.json` and `.env` now points to Neon. The old Lightsail managed database (`bayanihan-production-db`) should be snapshotted and decommissioned after verifying production stability. |
| 1.5.0 | 2026-07-28 | Added the operations manual that did not previously exist in writing: deploy procedure and its approval gate, configuration changes and the values that must not drift, how migrations actually run and the expand/contract obligation that follows, the one-off command procedure and its downtime cost, observability, rollback including when to restore instead of redeploy, and an explicit list of what is deliberately not possible. Corrected `deploy/lightsail/app-deployment.template.json`, which still showed `APP_ENV=staging` and `MAIL_MAILER=resend`. |
| 1.4.0 | 2026-07-27 | Decommissioned staging; built production. Took the database off the public internet and verified both halves. Verified client-IP spoofing is rejected live. Migrations moved into the container with `--isolated`. Added the search-indexing go-live switch. Fixed the `config/sentry.php` closure that had broken `config:cache` on every boot. |
| 1.3.0 | 2026-07-27 | Recorded the rebuilt Lightsail environment, managed database, and two-bucket Object Lock split. |
| 1.2.0 | 2026-07-27 | Recorded the original environment's teardown; replaced the CI/CD suite; migrated CI auth to OIDC. |
| 1.1.0 | 2026-07-27 | Custom domain cutover procedure and seven application changes. |
| 1.0.0 | 2026-07-27 | Initial runbook for the original AWS Lightsail staging environment. |
