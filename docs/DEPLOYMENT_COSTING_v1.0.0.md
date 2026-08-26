# Deployment Costing — Bayanihan One Window

**What it costs to keep the system running, per month and per year.**

Prepared: 2026-08-18 · Currency: USD (PHP shown at ₱58.00/USD — see §8)
Environment costed: `production` (`https://dmw7.owbap.app`), AWS `ap-southeast-1`, account `677206905439`

---

## 1. Scope

This covers **running costs only** — the infrastructure and third-party services
needed to keep the deployed application available. It excludes development
labour, project management, training, and hardware.

Architecture being costed (from `docs/DEPLOYMENT_PRODUCTION_AWS_v1.6.0.md`):

| Layer | What runs it |
|---|---|
| Application (web + queue worker + scheduler) | One Lightsail container service, single node |
| Database | Lightsail managed PostgreSQL 17, private, encrypted, automated backups |
| File storage | Two S3 buckets — uploads, and audit archives with Object Lock |
| Cache / queue / session | PostgreSQL (`database` driver) — **no Redis in production** |
| Container images | Amazon ECR, immutable tags |
| TLS certificate | Lightsail-managed, no charge |
| Email | Resend (HTTPS API) |
| Error monitoring | Sentry |
| Bot protection | Cloudflare Turnstile |
| AI chatbot | OpenRouter (free-tier model), pgvector retrieval in the same database |
| CI/CD | GitHub Actions |

There is **no staging environment** — it was decommissioned. Production is the
only environment. §6 prices staging back in if that decision is revisited.

---

## 2. AWS infrastructure — current baseline

| Item | Specification | USD / month | USD / year |
|---|---|---:|---:|
| Lightsail container service | power `small`, scale `1` | 15.00 | 180.00 |
| Lightsail PostgreSQL | bundle `micro_2_0`, 40 GB SSD, encrypted, backups on | 15.00 | 180.00 |
| S3 — `bayanihan-production-files` | uploads, versioned, low volume | 0.50 | 6.00 |
| S3 — `bayanihan-production-audit-archives` | Object Lock, monthly bundles | 0.50 | 6.00 |
| Amazon ECR | container images, under 1 GB | 0.10 | 1.20 |
| ACM / TLS certificate | Lightsail-managed | 0.00 | 0.00 |
| Data transfer | within the Lightsail bundle allowance | 0.00 | 0.00 |
| **AWS subtotal** | | **~31.10** | **~373.20** |

Both the container service and the database **bill continuously** — whether a
deployment exists or not, and whether the service is enabled or not. Stopping
them does not stop the charge; they must be *deleted*.

---

## 3. Third-party services — current baseline

All of these currently sit on free tiers. The paid step-up is shown because the
free tiers have hard ceilings that ordinary growth will reach.

| Service | Purpose | Current tier | USD / month | Next paid tier |
|---|---|---|---:|---|
| Resend | Transactional email — OTP, MFA, password reset | Free: 3,000 emails/mo, 100/day | 0.00 | $20/mo — 50,000 emails |
| Sentry | Error and performance monitoring | Developer: 5,000 errors/mo | 0.00 | $26/mo — Team |
| Cloudflare Turnstile | Bot protection on public forms | Free (unlimited) | 0.00 | — |
| OpenRouter | AI chatbot answer generation | Free model | 0.00 | ~$5–20/mo on a paid model |
| GitHub Actions | CI/CD pipeline | Within included minutes | 0.00 | $4/user/mo (Team) |
| Domain `owbap.app` | Public URL | `.app` registration | ~1.75 | ~$21/yr |
| DNS hosting | Name resolution | Cloudflare free / Route 53 | 0.00–0.50 | — |
| **Third-party subtotal** | | | **~1.75–2.25** | |

> **The 100-emails-per-day Resend cap is the first ceiling you will hit.** Every
> OFW intake sends an OTP. A busy intake day of 100+ verifications silently stops
> sending, and users cannot sign in. Budget the $20/mo tier before go-live volume,
> not after.

---

## 4. Total cost to keep it running

| | USD / month | USD / year | PHP / month | PHP / year |
|---|---:|---:|---:|---:|
| AWS infrastructure | 31.10 | 373.20 | ₱1,804 | ₱21,646 |
| Third-party services | 2.00 | 24.00 | ₱116 | ₱1,392 |
| **Total — current baseline** | **~33.10** | **~397.20** | **₱1,920** | **₱23,038** |

**Run rate: roughly $33 a month, or under $400 a year.**

On a $200 AWS credit balance, the AWS portion alone runs about **six months**.

---

## 5. Growth scenarios

The baseline is a single-node deployment with no redundancy and free-tier
supporting services. Two realistic step-ups:

### Tier B — Hardened production (recommended before wide public launch)

Removes the single point of failure and closes the operational gaps that a
certification assessor will ask about.

| Item | Change | USD / month |
|---|---|---:|
| Lightsail container service | `small`, scale `2` — no single node | 30.00 |
| Lightsail PostgreSQL | `micro_2_0` **high availability** (standby + failover) | 30.00 |
| S3 storage | ~50 GB uploads + archives, growing | 2.00 |
| Log shipping / retention | CloudWatch Logs or equivalent, off-platform | 5.00–10.00 |
| Sentry | Team tier | 26.00 |
| Resend | Pro tier, 50,000 emails | 20.00 |
| Cloudflare | Pro — WAF, rate limiting | 20.00 |
| Uptime / alert routing | consumes `/api/readyz` | 0.00–10.00 |
| Snapshot retention | scheduled DB snapshots kept beyond default | 3.00 |
| ECR + domain + DNS | unchanged | 2.00 |
| **Tier B total** | | **~$138–158/mo · ~$1,656–1,896/yr** |

PHP: roughly **₱8,000–9,200/month · ₱96,000–110,000/year**.

### Tier C — High usage (≈10× current caseload)

| Item | Change | USD / month |
|---|---|---:|
| Container service | `medium`, scale `2` | 80.00 |
| PostgreSQL | `small_2_0` HA | 60.00 |
| S3 + data transfer | ~250 GB of case documents | 8.00 |
| Email | 50k–100k/mo | 20.00–35.00 |
| AI chatbot (paid model) | ~20,000 conversations/mo | 15.00–40.00 |
| Monitoring, WAF, logs, backups | as Tier B | 65.00 |
| **Tier C total** | | **~$248–288/mo · ~$2,976–3,456/yr** |

PHP: roughly **₱14,400–16,700/month · ₱173,000–200,000/year**.

### What actually drives the cost up

| Driver | Effect |
|---|---|
| Uploaded case documents | S3 storage — linear and cheap; 100 GB ≈ $2.50/mo |
| Email volume | Step function at the 3,000/mo and 50,000/mo tier boundaries |
| Concurrent users | Container power and scale — the largest single line item |
| Database size and query load | Bundle upgrade; `micro_2_0` is 40 GB and 1 GB RAM |
| AI chatbot usage | Zero on the free model; per-token once on a paid one |
| Audit retention (`AUDIT_RETENTION_DAYS=365`) | Archive bundles accumulate permanently under Object Lock — by design they cannot be deleted early |

---

## 6. Optional and deferred items

Not provisioned today. Each is a cost decision, not a technical obstacle.

| Item | USD / month | Why you might add it |
|---|---:|---|
| Staging environment | ~25–31 | Currently none — every change is verified in production. A second container service (`nano`, no public endpoint) plus a second database. |
| Admin container service | 7.00 | `nano`, no public endpoint, scheduler and worker off — a throwaway target for one-off `artisan` commands at zero production downtime. |
| Split queue worker / scheduler | 7.00–10.00 | `RUN_SCHEDULER` / `RUN_QUEUE_WORKER` already make this possible without an image rebuild. |
| Malware scanning of uploads | 0.00–15.00 | `MALWARE_SCANNER=null` today. ClamAV in-container is free but needs RAM; a hosted scanning API is not. |
| Secret management | ~5.00 | Lightsail has no secret store — production secrets sit in plaintext container environment variables today. |

---

## 7. Standards-readiness check

Per project policy, this document is not a certification artefact, so the costed
architecture is checked against ISO 9001 / ISO 27001 / DPTM / SOC 2 for anything
that would need rework at audit time. Each item below is a **cost line with a
compliance consequence** — deferring it is a budget decision that carries an
audit finding.

| Gap in the current baseline | Standard reference | Cost to close |
|---|---|---|
| Single node, no HA — no availability redundancy | ISO 27001 A.8.14, SOC 2 A1.2 | +$15–30/mo (Tier B) |
| No off-platform log retention — container logs are ephemeral | A.8.15, CC7.2 | +$5–10/mo |
| No alert routing — `/api/readyz` exposes thresholds, nothing consumes them | A.8.16, CC7.2 | $0–10/mo |
| Backups never restore-tested — backups are not recovery evidence | A.8.13, A.5.30, SOC 2 A1.3 | Labour only, no infra cost |
| Secrets in plaintext container environment variables | A.8.24, CC6.1 | ~$5/mo |
| No upload malware scanning | A.8.7, CC6.8 | $0–15/mo |
| No staging — changes are verified in production | ISO 9001 §8.3.4, A.8.31, CC8.1 | +$25–31/mo |
| Supplier register incomplete (AWS, Resend, Sentry, OpenRouter, Cloudflare) | A.5.19–A.5.22, CC9.2 | Labour only, no infra cost |

**Tier B closes most of these for roughly $105–125/month above the current
baseline.** That is the number to quote if certification is on the roadmap —
budgeting only the $33 baseline means retrofitting at audit time, which costs
more than building it in now.

---

## 8. Assumptions

1. **Prices are AWS `ap-southeast-1` (Singapore) list prices.** The AWS baseline
   in §2 is taken from the deployment runbook, which records figures verified
   against the live account.
2. **One line to reconcile against the actual invoice:** the runbook records the
   `small` container service at **$15/month**; AWS list price for a `small`
   container node is **$20/node/month** in most regions. If the invoice shows
   $20, the baseline totals become **$38/month · $456/year**, and Tier B rises by
   $10/month. Confirm from the billing console before this figure goes into a
   budget submission.
3. **FX rate ₱58.00/USD**, indicative only. AWS bills in USD, so the peso figure
   moves with the exchange rate — carry a 10% FX contingency in any peso budget.
4. Free tiers are assumed genuinely free at current volume. Resend (100
   emails/day) and Sentry (5,000 errors/month) are the two that will be reached
   first.
5. Data transfer is assumed to stay inside the Lightsail bundle allowance. Heavy
   document downloads by many concurrent users would add overage.
6. Excludes: developer time, penetration testing, certification audit fees, and
   any DMW-side network or endpoint costs.
7. Taxes (VAT / withholding on AWS invoices) are not included.

---

## 9. Summary

| Scenario | Per month | Per year |
|---|---:|---:|
| **Current baseline** — what it costs today | **~$33** | **~$397** |
| **Tier B** — hardened, HA, audit-ready | ~$138–158 | ~$1,656–1,896 |
| **Tier C** — 10× caseload | ~$248–288 | ~$2,976–3,456 |

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-08-18 | Initial costing. Baseline drawn from the verified AWS production runbook (`DEPLOYMENT_PRODUCTION_AWS_v1.4.0.md` §8, `v1.6.0` §1); third-party services identified from `deploy/lightsail/app-deployment.template.json` and `.github/workflows/deploy.yml`. Added growth tiers, cost drivers, deferred items, and a standards-readiness check. |
