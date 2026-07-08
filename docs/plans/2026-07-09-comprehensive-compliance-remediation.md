# Comprehensive Compliance Remediation Plan

**Goal:** Achieve ISO 27001:2022 / 27002:2022 / 20000-1:2018 / ISO 9001:2015 certification readiness by addressing ALL remaining technical, documentation, governance, and organizational gaps.

**Status:** Phase 1–3 (P0/P1/P2 code-level items) are ✅ COMPLETED. This plan covers everything still open.

**Standards coverage:**
- ISO 27001:2022 (ISMS clauses 4–10)
- ISO 27002:2022 (Annex A controls)
- ISO 20000-1:2018 (SMS clauses)
- ISO 9001:2015 (QMS clauses)
- RA 10173 (Data Privacy Act), RA 11641 (DMW Act), DICT Cloud First Policy

**Tech Stack:** Laravel 13, Inertia (React 18), PostgreSQL (Supabase), Tailwind CSS, Vite 8

---

## How to read this plan

Each task is in one of five **tiers** that represent the recommended execution order:

| Tier | What | Who | Estimated timeline |
|------|------|-----|-------------------|
| **Tier 1** | Code — security & compliance fixes (fast, high risk-reduction) | Lead dev | 1–2 weeks |
| **Tier 2** | Code — CI/ops hardening | Lead dev / DevOps | 3–5 days |
| **Tier 3** | Governance documentation (draftable in repo, needs management approval) | Lead dev + mgmt | 2–3 weeks |
| **Tier 4** | Organizational external evidence (requires management/legal action) | ISM / DPO / Legal | 1–3 months |
| **Tier 5** | Long-term management-system maturity | Organization-wide | 3–12 months |

---

## Lay of the land — what's been done

### ✅ Phase 1 (P0 — Immediate)
| Item | Finding | Summary |
|------|---------|---------|
| I-1 | TECH-001 | Public registration disabled |
| I-2 | TECH-002 | `CheckUserActive` middleware — deactivated/deleted accounts blocked at login |
| I-3 | TECH-004 | `cookies.txt` removed, `.gitignore`d, gitleaks in CI |
| I-4 | TECH-005 | `trustProxies('*')` → CIDR-restricted |
| I-5 | TECH-020 | Debug OTP restricted to `local` only |
| D30-4 | TECH-009/021/022 | PR-gated CI with PHPUnit + Pint + `composer audit` + `npm audit` + gitleaks |

### ✅ Phase 2 (P1 — 30-day)
| Item | Finding | Summary |
|------|---------|---------|
| D30-2 | TECH-011 | `mfa_secret` encrypted, recovery codes HMAC-SHA256 |
| D30-1 | TECH-003 | MFA enforcement middleware for ADMIN role |
| D30-3a | TECH-006 | Real SHA256 hash chain in audit logs |
| D30-3b | TECH-007 | Missing auth/export audit events added |
| D60-3 | TECH-012 | Session invalidation on password change/reset |
| TECH-024 | TECH-024 | Password-reset enumeration fixed |
| D30-5 | TECH-010/023 | Governance docs partially reconciled |

### ✅ Phase 3 (P2 — 60-day)
| Item | Finding | Summary |
|------|---------|---------|
| D60-7 | TECH-015 | Audit prune unblocked via conditional trigger |
| D60-1 | TECH-013/028 | Nonce-based CSP (Report-Only), security headers, HSTS preload, CORP |
| TECH-017 | TECH-017 | HTTP timeouts: Turnstile 5s+3s, Cloudinary 30s |

### ✅ Compliance doc reconciliation (final wrap)
All 8 compliance documents updated to reflect resolved findings:
- `iso-27002-control-assessment-v1.0.0.md` (7 rows)
- `information-risk-register-v1.0.0.md` (5 rows)
- `iso-consolidated-control-matrix-v1.0.0.md` (2 rows)
- `iso-alignment-executive-summary-v1.0.0.md` (1 edit)
- Plus `SECURITY_REQUIREMENTS.md`, `REQUIREMENTS_TRACEABILITY.md`, `iso-remediation-roadmap-v1.0.0.md`, `technical-security-and-quality-findings-v1.0.0.md`

---

## Tier 1 — Code: Security & Compliance Fixes

### T1.1 — D60-2: PII encryption at rest (TECH-014)

| Field | Value |
|-------|-------|
| **Effort** | M (~3–5 days) |
| **Risk** | Medium (existing data migration) |
| **Standards** | 27002 8.24/5.34, RA 10173 |
| **Risk register** | R-11 (High) |
| **Dependencies** | None |

**Approach:**
- Identify sensitive PII columns across `clients`, `client_employments`, `client_addresses`, `next_of_kin`, `users` tables
- Apply Laravel's `AsEncrypted` / `encrypted` cast to sensitive fields (uses APP_KEY for AES-256)
- Migration note: existing plaintext rows need migration — write an Artisan command to re-encrypt
- Document key-management approach: APP_KEY rotation procedure, impact on encrypted data
- Append `_encrypted` suffix convention or keep same column name with cast

**Files to modify:**
- `app/Models/Client.php` — add `encrypted` casts for passport, identifiers
- `app/Models/ClientEmployment.php` — add encrypted casts
- `app/Models/ClientAddress.php` — add encrypted casts
- `app/Models/NextOfKin.php` — add encrypted casts
- `app/Models/User.php` — add encrypted casts for other sensitive fields (if any)
- Create migration(s) to widen columns if ciphertext exceeds current limits
- Create `php artisan pii:encrypt-existing` command for data migration
- `docs/SECURITY_REQUIREMENTS.md` — update NFR-SEC-024 to ✅
- `docs/REQUIREMENTS_TRACEABILITY.md` — update DB-014 to ✅
- `docs/compliance/technical-security-and-quality-findings-v1.0.0.md` — mark TECH-014 remediated in v1.2 changelog

**Validation:**
- DB inspection shows ciphertext
- Read/write operations function correctly
- Old plaintext rows are migrated

---

### T1.2 — D60-5: Health-gate + rollback (TECH-018)

| Field | Value |
|-------|-------|
| **Effort** | M (~2–3 days) |
| **Risk** | Low |
| **Standards** | 20000-1 8.5.1, 9001 8.6, 27002 8.32 |
| **Risk register** | R-15 (Medium) |
| **Dependencies** | T1.7 (TECH-027 for /up standardization) |

**Approach:**
- Remove `continue-on-error: true` from deploy workflow
- Add post-deploy health probe that curls `/up` and waits for 200
- Add pre-deploy DB snapshot step: `pg_dump` to timestamped file
- Document rollback procedure: restore snapshot → redeploy previous image
- Wire rollback step in CI

**Files to modify:**
- `.github/workflows/deploy-staging.yml` — remove `continue-on-error`, add health gate, add rollback step
- `docs/DEPLOYMENT_GUIDE.md` — document rollback procedure
- `docs/compliance/technical-security-and-quality-findings-v1.0.0.md` — mark TECH-018 remediated

**Validation:**
- Deliberately failing deploy fails pipeline
- Rollback procedure is documented and scripted

---

### T1.3 — D60-6: Error tracker + monitoring/alerting (TECH-016, TECH-027, TECH-032)

| Field | Value |
|-------|-------|
| **Effort** | M (~2–3 days) |
| **Risk** | Low |
| **Standards** | 27002 8.16, 20000-1 8.6 |
| **Dependencies** | T1.7 (TECH-027 for /up) |

**Approach:**
- Wire Sentry (PHP + JavaScript) via `sentry-laravel` package
- Configure failed-job alerting (Slack/email channel in `config/queue.php`)
- Standardize health endpoint on `/up` (TECH-027)
- Fix container health-check to curl `/up` instead of `php -r "echo 'ok';"` (TECH-032)
- Add `/health` → `/up` redirect for backwards compatibility

**Files to modify:**
- `composer.json` — add `sentry/sentry-laravel`
- `config/sentry.php` — create
- `.env.example` — add SENTRY_DSN, SENTRY_LARAVEL_TRACES_SAMPLE_RATE
- `bootstrap/app.php` — standardize `/up` as health path, remove `/health` route if separate
- `docker-compose.yml` — change container health-check to `curl -f http://localhost/up`
- `.github/workflows/reset-staging-data.yml` — change `/health` to `/up`
- `docs/compliance/technical-security-and-quality-findings-v1.0.0.md` — mark TECH-016, TECH-027, TECH-032 remediated

**Validation:**
- Forced error produces Sentry issue
- Failed job triggers notification
- Container health-check correctly probes app

---

### T1.4 — D90-4: E2E critical-path tests (TECH-036)

| Field | Value |
|-------|-------|
| **Effort** | M (~4–5 days) |
| **Risk** | Low |
| **Standards** | 27002 8.29, 9001 8.6 |
| **Dependencies** | None |

**Approach:**
Implement the 6 documented critical E2E paths using Playwright (already configured):
1. Login → OTP → Dashboard (happy path)
2. Create case with client + documents
3. Create referral → agency acceptance → milestone
4. Case closure (all referrals terminal)
5. Public tracking: enter tracker number → OTP → view status
6. Admin: user management + system settings

**Files:**
- `resources/js/test/e2e/critical-path.spec.js` — or per-scenario files
- `playwright.config.ts` — verify configuration
- `docs/TESTING_STRATEGY.md` — update coverage claims

**Validation:**
- `npm run test:e2e` passes for all 6 scenarios

---

### T1.5 — D90-5: RLS hardening (TECH-026, TECH-034)

| Field | Value |
|-------|-------|
| **Effort** | M (~2–3 days) |
| **Risk** | Medium (DB-level isolation) |
| **Standards** | 27002 8.3/8.28; RA 10173 |
| **Dependencies** | None |

**Approach:**
- Parameterize `SetPostgresSession.php` to use `SELECT set_config(?, ?, true)` with bindings instead of string interpolation
- Make RLS fail-closed on non-Postgres: change try/catch to throw
- Verify PgBouncer transaction-mode compatibility for `SET session` vs `SELECT set_config`
- Write/expand RLS tests covering lane isolation scenarios

**Files to modify:**
- `app/Http/Middleware/SetPostgresSession.php` — use bound parameters, fail-closed
- `database/migrations/..._enable_row_level_security.php` — update if needed
- `docs/compliance/technical-security-and-quality-findings-v1.0.0.md` — mark TECH-026, TECH-034 remediated

**Validation:**
- RLS tests pass
- Non-PG environment throws clear error

---

### T1.6 — Notification IDOR fix (TECH-025)

| Field | Value |
|-------|-------|
| **Effort** | XS (~1–2 hours) |
| **Risk** | Low (UUID-guessing, no data disclosure) |
| **Standards** | 27002 8.3 |
| **Dependencies** | None |

**Files to modify:**
- `app/Services/NotificationService.php:96-105` — change `Notification::findOrFail($id)` to `$request->user()->notifications()->findOrFail($id)`

**Validation:**
- User A cannot mark user B's notification read
- Existing tests pass

---

### T1.7 — Health-path standardization (TECH-027)

| Field | Value |
|-------|-------|
| **Effort** | XS (~1 hour) |
| **Risk** | Low |
| **Dependencies** | None (but do before T1.3) |

**Files to modify:**
- `bootstrap/app.php:33` — confirm `/up` is the only health route
- `.github/workflows/reset-staging-data.yml:32` — change `/health` → `/up`
- `docs/DEPLOYMENT_GUIDE.md` — update health endpoint references

---

### T1.8 — Duplicate security headers (TECH-028)

| Field | Value |
|-------|-------|
| **Effort** | XS (~1 hour) |
| **Risk** | Low |
| **Dependencies** | None |

**Approach:**
- Remove nginx `X-Frame-Options SAMEORIGIN` (Laravel sets `DENY`)
- Remove nginx `X-XSS-Protection` (deprecated)
- Single source of truth: Laravel middleware

**Files to modify:**
- `docker/nginx/conf.d/default.conf` — remove duplicate headers

---

### T1.9 — Upload validation from config (TECH-029)

| Field | Value |
|-------|-------|
| **Effort** | XS (~1 hour) |
| **Risk** | Low |
| **Dependencies** | None |

**Files to modify:**
- `app/Http/Requests/StoreCaseDocumentRequest.php:27` — replace `max:20480` with config call: `config('file-uploads.max_size', 20480)`

---

### T1.10 — Staging credentials from CI/Slack (TECH-030)

| Field | Value |
|-------|-------|
| **Effort** | XS (~30 min) |
| **Risk** | Low (staging scope) |
| **Dependencies** | None |

**Files to modify:**
- `.github/workflows/deploy-staging.yml:121-122` — remove test credentials from Slack notification
- `.github/workflows/reset-staging-data.yml:42` — same
- Replace with: "Staging credentials available in internal runbook"

---

### T1.11 — Chatbot prompt-injection guard (TECH-031)

| Field | Value |
|-------|-------|
| **Effort** | S (~2–4 hours) |
| **Risk** | Low (bot is unprivileged) |
| **Dependencies** | None |

**Approach:**
- Add output character cap on chatbot response
- Add refusal guard for sensitive-pattern prompts
- Keep bot unprivileged (already is)

**Files to modify:**
- `app/Services/ChatbotService.php` — add output cap + refusal patterns
- `app/Http/Controllers/ChatbotController.php` — validation updates

---

### T1.12 — Container health-check fix (TECH-032)

| Field | Value |
|-------|-------|
| **Effort** | XS (~30 min) |
| **Risk** | Low |
| **Dependencies** | T1.7 (for /up endpoint) |

**Files to modify:**
- `docker-compose.yml:86-91` — change `php -r "echo 'ok';"` → `curl -f http://localhost/up`

---

### T1.13 — strict_types + TS strict mode (TECH-033)

| Field | Value |
|-------|-------|
| **Effort** | L (~1–2 weeks) |
| **Risk** | Medium (may expose type errors) |
| **Dependencies** | None |
| **Note** | Incremental adoption recommended |

**Approach:**
- Add `declare(strict_types=1)` to new files only (retroactively add to existing files incrementally)
- Enable `tsconfig.json` `strict: true` — may need to fix existing violations
- Add `tsc --noEmit` to CI step (already partially in D30-4)
- Increase `.tsx` usage for new components

---

## Tier 2 — Code: CI/Ops Hardening

### T2.1 — CI runtime alignment (D60-11 / TECH-021/022)

| Field | Value |
|-------|-------|
| **Effort** | S (~1 day) |
| **Risk** | Low |
| **Dependencies** | None |

**Note:** PHP 8.3 and Node 20 were already bumped in D30-4. Remaining:
- Verify `deploy-staging.yml` uses PHP 8.3 / Node 20 (already done)
- Remove stale `bun.lock` (already done)
- Align `@types/react` with React 18 if not already
- Consolidate Tailwind to one major version (^3 vs ^4 conflict in bun lock — check npm lock)

**Files to check:**
- `.github/workflows/deploy-staging.yml`
- `package.json` — check `@types/react`, `tailwindcss`, `@tailwindcss/vite` version alignment

---

### T2.2 — Queue worker/scheduler confirmation (D60-12 / TECH-035)

| Field | Value |
|-------|-------|
| **Effort** | XS (~1 hour research) |
| **Risk** | Medium if unconfirmed |
| **Dependencies** | Render access |

**Approach:**
- Verify that Render production service has `queue:listen` and `schedule:run` workers
- If not, add to `render.yaml` or document as ops procedure
- Update `docker/supervisord.conf` if multi-process image is used

---

## Tier 3 — Governance Documentation (can be drafted in repo)

### T3.1 — Back-up restore test doc (D30-6 / TECH-008)

| Field | Value |
|-------|-------|
| **Effort** | S (~1–2 days for doc + execution) |
| **Risk** | Low |
| **Standards** | 27002 8.13 |

**Approach:**
- Set up scheduled `pg_dump` → encrypted offsite storage (e.g., Supabase Storage or S3 bucket)
- Create `scripts/backup.sh` and `scripts/restore-test.sh`
- Perform a restore drill: restore DB + verify a document exists
- Document the procedure with evidence

**Files:**
- `scripts/backup.sh` — create
- `scripts/restore-test.sh` — create
- `docs/DEPLOYMENT_GUIDE.md` — add backup/restore section
- `docs/compliance/technical-security-and-quality-findings-v1.0.0.md` — mark TECH-008 remediated

---

### T3.2 — Risk register + SoA (D60-8)

| Field | Value |
|-------|-------|
| **Effort** | M (~3–5 days) |
| **Risk** | Low |
| **Standards** | 27001 6.1 |
| **Dependencies** | T3 done context |

**Approach:**
This assessment already produced `information-risk-register-v1.0.0.md`. Formalize it:
- Define risk-assessment methodology (ISO 27005 / OCTAVE / quantitative)
- Produce formal Statement of Applicability summarizing which Annex A controls apply
- Get ISM sign-off on risk acceptance

---

### T3.3 — Incident-response + change-management procedures (D60-9)

| Field | Value |
|-------|-------|
| **Effort** | M (~3–5 days) |
| **Risk** | Low |
| **Standards** | 27002 5.24/8.32, 20000-1 8.5.1 |

**Approach:**
- Draft incident-response plan: phases (detect → contain → eradicate → recover → post-mortem), severity levels, contact tree
- Draft change-management procedure: request → assess → approve → implement → verify → close
- Define release-approval gate (link to T1.2's health-gate)

**Files:**
- `docs/procedures/incident-response.md` — create
- `docs/procedures/change-management.md` — create
- `docs/DEPLOYMENT_GUIDE.md` — reference these

---

### T3.4 — Quality policy + CAPA (D60-10)

| Field | Value |
|-------|-------|
| **Effort** | S (~2–3 days) |
| **Risk** | Low |
| **Standards** | 9001 5.2/6.2/9.3/10.2 |

**Approach:**
- Draft quality policy statement (aligned with DMW mission)
- Define measurable quality objectives (e.g., case resolution time, referral acceptance rate, uptime %)
- Create CAPA register template

**Files:**
- `docs/management/quality-policy.md` — create
- `docs/management/quality-objectives.md` — create
- `docs/management/capa-register.md` — create

---

### T3.5 — Service catalogue + SLA/OLA (D90-1)

| Field | Value |
|-------|-------|
| **Effort** | M (~3–5 days) |
| **Risk** | Low |
| **Standards** | 20000-1 8.2.3 |

**Approach:**
- Draft a service catalogue listing each service (case management, referrals, tracking, feedback, chatbot, reporting)
- For each service: description, owner, availability target, throughput, response-time targets
- Define SLAs with DMW (internal) and OLAs with partner agencies

**Files:**
- `docs/management/service-catalogue.md` — create

---

### T3.6 — BCP/DR plan (D90-2)

| Field | Value |
|-------|-------|
| **Effort** | M (~3–5 days) |
| **Risk** | Low |
| **Standards** | 27001 5.29/5.30, 20000-1 8.7.2 |
| **Dependencies** | T3.1 (backup proven) |

**Approach:**
- Determine RPO/RTO targets (propose: RPO 24h, RTO 4h for production)
- Document BIA: which services are critical, what degradation is acceptable
- Write DR runbook: incident classification → failover → recovery → communication templates

**Files:**
- `docs/management/bcp-dr-plan.md` — create

---

### T3.7 — Capacity plan (D90-3)

| Field | Value |
|-------|-------|
| **Effort** | M (~3–5 days) |
| **Risk** | Low |
| **Standards** | 27002 8.6, 20000-1 8.6.1 |

**Approach:**
- Document capacity baseline (current DB size, upload store, request volume)
- Set targets: 144 concurrent users (from SRS), 99% availability
- Perform load testing to validate targets
- Document scaling plan (vertical → horizontal)

**Files:**
- `docs/management/capacity-plan.md` — create
- Load test scripts in `scripts/` — create

---

### T3.8 — Access-review procedure (D90-7)

| Field | Value |
|-------|-------|
| **Effort** | S (~1–2 days) |
| **Risk** | Low |
| **Standards** | 27002 5.18/5.11/5.19 |
| **Dependencies** | None |

**Approach:**
- Draft access-review procedure: quarterly review of privileged users, offboarding checklist
- Supplier evaluation record template

**Files:**
- `docs/management/access-review-procedure.md` — create

---

## Tier 4 — Organizational External Evidence

### T4.1 — Independent backup + restore test execution (D30-6)

After T3.1 procedure is drafted, execute:
- Set up automated encrypted offsite dumps
- Perform documented restore drill
- Keep evidence (screenshots, logs)

### T4.2 — InfoSec policy + ISM/DPO assignment (D30-7)

| Field | Value |
|-------|-------|
| **Effort** | Organizational |
| **Standards** | 27001 5.2/5.3 |
| **Dependencies** | Management action |

**Action:**
- Assign Information Security Manager (ISM)
- Assign Data Protection Officer (DPO) — may be a designated role, not necessarily new hire
- Draft and approve Information Security Policy
- Draft ISMS scope statement

**Evidence:** Signed policy document, role assignment records.

### T4.3 — PIA + DPAs + privacy notice (D30-8)

| Field | Value |
|-------|-------|
| **Effort** | Organizational/Legal |
| **Standards** | RA 10173, 27002 5.34 |
| **Dependencies** | Legal counsel |

**Action:**
- Conduct Privacy Impact Assessment
- Execute Data Processing Agreements with: Render, Supabase, Cloudinary, OpenAI, email provider
- Draft and publish privacy notice
- NPC registration / DPO appointment letter (if required under RA 10173)
- Document data-subject-rights (DSR) process (access, correction, erasure)

### T4.4 — Supplier security evaluation records (E-22)

Evaluate Render, Supabase, Cloudinary, OpenAI, SMTP provider against security criteria:
- SOC 2 / ISO 27001 certifications
- Data residency
- Sub-processor list
- Obtain their certifications

### T4.5 — Branch protection settings (E-36)

Configure GitHub branch protection:
- Require PR CI to pass
- Require review (at least 1)
- Require up-to-date branches
- Restrict force-push, deletion

### T4.6 — Secrets inventory + rotation records (E-40)

Document every secret: where stored, rotation period, last rotated date. `RENDER_API_KEY`, `SLACK_WEBHOOK`, mail/AI keys, Supabase credentials, Cloudinary `CLOUDINARY_URL`.

---

## Tier 5 — Long-term Management System Maturity

These are ongoing organizational activities, not one-time tasks:

| Activity | Standard | Frequency |
|----------|----------|-----------|
| Internal audit programme | 27001/9001/20000-1 9.2 | Annual |
| Management review | 27001/9001/20000-1 9.3 | Quarterly/Annual |
| Security awareness training | 27001 7.3 | Annual |
| Continual improvement (CAPA) | All standards clause 10 | Ongoing |
| Info classification & handling scheme | 27002 5.12/5.13 | Once, then maintain |
| Formal SDLC policy | 27002 8.25/8.27 | Once, then maintain |
| Independent security review | 27002 5.35 | Annual |

---

## Execution order recommended

```
Week 1-2 (Tier 1 code fixes):
├── T1.7  / TECH-027  — Health-path standardization (prerequisite)
├── T1.12 / TECH-032  — Container health check (prerequisite)
├── T1.6  / TECH-025  — Notification IDOR (fast win)
├── T1.8  / TECH-028  — Duplicate headers (fast win)
├── T1.9  / TECH-029  — Upload config (fast win)
├── T1.10 / TECH-030  — Staging credentials (fast win)
├── T1.11 / TECH-031  — Chatbot guard
├── T1.3  / D60-6 / TECH-016/027/032 — Sentry + monitoring
├── T1.2  / D60-5 / TECH-018 — Health-gate + rollback
├── T1.5  / D60-5 / TECH-026/034 — RLS hardening
├── T1.1  / D60-2 / TECH-014 — PII encryption (largest effort, start early)
└── T1.4  / D90-4 / TECH-036 — E2E tests

Week 3 (Tier 2 CI/Ops):
├── T2.1 / D60-11 / TECH-021/022 — CI runtime verification
└── T2.2 / D60-12 / TECH-035 — Worker confirmation

Week 3-5 (Tier 3 governance docs):
├── T3.1 / D30-6 / TECH-008 — Backup + restore
├── T3.2 / D60-8 — Risk register + SoA
├── T3.3 / D60-9 — Incident + change procedures
├── T3.4 / D60-10 — Quality policy + CAPA
├── T3.5 / D90-1 — Service catalogue + SLA/OLA
├── T3.6 / D90-2 — BCP/DR plan
├── T3.7 / D90-3 — Capacity plan
└── T3.8 / D90-7 — Access review procedure

Week 6+ (Tier 4 organizational):
├── T4.1 / D30-6 — Execute restore drill
├── T4.2 / D30-7 — InfoSec policy + ISM/DPO
├── T4.3 / D30-8 — PIA + DPAs + privacy notice
├── T4.4 / E-22 — Supplier evaluations
├── T4.5 / E-36 — Branch protection
└── T4.6 / E-40 — Secrets inventory

Ongoing (Tier 5):
├── Internal audit programme
├── Management review cadence
├── Security awareness
├── CAPA/continual improvement
└── SDLC policy
```

---

## Files to create

| File | Task |
|------|------|
| `scripts/backup.sh` | T3.1 |
| `scripts/restore-test.sh` | T3.1 |
| `scripts/load-test.sh` | T3.7 |
| `config/sentry.php` | T1.3 |
| `docs/procedures/incident-response.md` | T3.3 |
| `docs/procedures/change-management.md` | T3.3 |
| `docs/management/quality-policy.md` | T3.4 |
| `docs/management/quality-objectives.md` | T3.4 |
| `docs/management/capa-register.md` | T3.4 |
| `docs/management/service-catalogue.md` | T3.5 |
| `docs/management/bcp-dr-plan.md` | T3.6 |
| `docs/management/capacity-plan.md` | T3.7 |
| `docs/management/access-review-procedure.md` | T3.8 |

## Files to modify

| File | Task(s) |
|------|---------|
| `app/Models/Client.php` | T1.1 |
| `app/Models/ClientEmployment.php` | T1.1 |
| `app/Models/ClientAddress.php` | T1.1 |
| `app/Models/NextOfKin.php` | T1.1 |
| `app/Models/User.php` | T1.1 |
| `app/Services/NotificationService.php` | T1.6 |
| `app/Http/Middleware/SetPostgresSession.php` | T1.5 |
| `app/Services/ChatbotService.php` | T1.11 |
| `app/Http/Controllers/ChatbotController.php` | T1.11 |
| `app/Http/Requests/StoreCaseDocumentRequest.php` | T1.9 |
| `bootstrap/app.php` | T1.7 |
| `docker/nginx/conf.d/default.conf` | T1.8 |
| `docker-compose.yml` | T1.12 |
| `.github/workflows/deploy-staging.yml` | T1.2, T1.10, T2.1 |
| `.github/workflows/reset-staging-data.yml` | T1.7, T1.10 |
| `.env.example` | T1.3 |
| `composer.json` | T1.3 |
| `package.json` | T2.1 |
| `docs/DEPLOYMENT_GUIDE.md` | T1.2, T1.7, T3.1 |
| `docs/TESTING_STRATEGY.md` | T1.4 |
| `docs/SECURITY_REQUIREMENTS.md` | T1.1 |
| `docs/REQUIREMENTS_TRACEABILITY.md` | T1.1 |
| `docs/compliance/technical-security-and-quality-findings-v1.0.0.md` | All Tier 1-3 |
| `docs/compliance/iso-remediation-roadmap-v1.0.0.md` | All Tier 1-3 |
| `database/migrations/*_enable_row_level_security.php` | T1.5 |

---

## Risk summary by tier

| Tier | Items | Highest residual risk |
|------|-------|----------------------|
| **Tier 1** | 13 code changes | Medium: PII encryption migration, RLS fail-closed |
| **Tier 2** | 2 ops checks | Low: worker config unconfirmed |
| **Tier 3** | 8 governance docs | Low: drafts need management approval |
| **Tier 4** | 6 organizational actions | Critical: R-20 (no PIA/DPAs) until resolved |
| **Tier 5** | Ongoing | Governance maturity deficit without these |

---

## Verification

After each Tier 1 task:
- `php artisan test` — full suite must pass (baseline: 742/754)
- `npm run test:run` — JS unit tests
- Targeted feature test for the change

After Tier 3 tasks:
- Review: do documents accurately describe the code?
- Cross-reference against compliance docs for consistency

---

## Rollback

| Change | Rollback |
|--------|----------|
| PII encryption | Remove encrypted cast, run decrypt command |
| Sentry | Block DSN env → no change |
| Health-gate | Revert CI deploy step |
| RLS hardening | Revert migration, keep app-layer |
| All Tier 3-5 docs | No rollback needed (separate from code) |

---

*Generated: 2026-07-09. This plan supersedes the earlier phased implementation plan which only covered the P0-P2 code items now completed. The remaining work is organized into five tiers reflecting execution order, not risk priority. Items marked ⏳ PENDING in the remediation roadmap are mapped here.*
