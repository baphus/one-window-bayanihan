# ISO Alignment — Executive Summary

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| System | One Window Bayanihan — DMW Region VII OFW case-management & referral system (Laravel 13 + Inertia/React, PostgreSQL/Supabase, Render) |
| Standards assessed | ISO/IEC 27001:2022, ISO/IEC 27002:2022, ISO/IEC 20000-1:2018, ISO 9001:2015 (editions **assumed** — none specified in the repo) |
| Basis | Read-only, evidence-based inspection of the repository at commit `b8a7211`, branch `main`, by six parallel specialist audit passes. No runtime observation; no application changes. |
| **This is NOT a certification audit** | This is an alignment & readiness assessment based on repository evidence only. It does not certify compliance and does not substitute for a formal audit by an accredited body, legal counsel, or a privacy professional. |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial executive summary. |

---

## Overall readiness

| Standard | Overall maturity (0–5) | One-line verdict |
|---|---|---|
| ISO/IEC 27001 (ISMS) | **~1.8 — Initial→Partial** | Strong technical controls; the management system (policy, risk, SoA, audit, review) does not yet exist in the repo. |
| ISO/IEC 27002 (controls) | **~2.0 — Partially implemented** | Technological controls are a genuine strength; organizational/people/physical controls are documentation/evidence gaps. |
| ISO/IEC 20000-1 (ITSM) | **~1.4 — Initial** | Largest structural gap: incident/problem/change/SLA/continuity processes are essentially absent. |
| ISO 9001 (QMS) | **~2.0 — Partially implemented** | Requirements traceability & testing are strong; quality policy, objectives, CAPA, and reviews are absent. |

**Scoring method:** maturity is a weighted judgement across five sub-scores (technical implementation, documentation, operational evidence, governance/management system, monitoring/continual improvement), **not** an average that treats every requirement as code-verifiable. Governance and operational sub-scores are low primarily because the evidence is **not in the repository** — this is an *evidence limitation*, not confirmed organizational non-conformity. See each standard report for the sub-score breakdown.

| Sub-score (all standards, indicative) | 27001 | 27002 | 20000-1 | 9001 |
|---|---|---|---|---|
| Technical implementation | 3 | 3 | 2 | 3 |
| Documentation | 2 | 2 | 1 | 2 |
| Operational evidence | 1 | 1 | 1 | 1 |
| Governance / management system | 1 | 1 | 1 | 1 |
| Monitoring / continual improvement | 2 | 2 | 1 | 2 |

## Five strongest existing controls
1. **File-upload security pipeline** — ClamAV scan + content-based `finfo` MIME sniffing + extension/size allowlist + UUID filenames + private disk with signed URLs, tested (`StorageService.php`; P-01).
2. **Object-level authorization in sensitive controllers** — ownership re-checks returning 404-not-403, with cross-tenant IDOR explicitly tested (`AuthorizationGapTest`; P-04).
3. **Append-only audit table enforced by a database trigger** — real, DB-layer tamper-evidence (migration trigger; P-02), plus recursive sensitive-value redaction (P-03).
4. **Defense-in-depth request security** — CSRF everywhere (no exceptions), parameterized SQL (no SQLi found), mass-assignment discipline, dompdf hardened, CSV formula-injection neutralized, seven named rate limiters + nginx edge limits (P-07…P-13).
5. **Requirements traceability & backend test suite** — `REQUIREMENTS_TRACEABILITY.md` (354 requirements → implementation → verification) and 129 test files (~984 passing) against a production-parity PostgreSQL CI (P-15).

## Ten highest-priority gaps
1. **[Critical] Public self-registration grants the `CASE_MANAGER` staff role** — any internet user can self-verify into staff-level access to OFW PII (TECH-001).
2. **[High] Deactivated/soft-deleted users can still log in** — access revocation is ineffective (TECH-002).
3. **[High] MFA is never enforced, even for administrators** (TECH-003).
4. **[High] `trustProxies('*')` lets X-Forwarded-For spoofing bypass the admin IP allowlist and rate limits** (TECH-005).
5. **[High] Audit trail incomplete** — failed logins, logouts, and PII exports are unlogged; the "hash chain" never computes a hash (TECH-006, TECH-007).
6. **[High] Backup is single-vendor with no offsite copy and no tested restore** (TECH-008).
7. **[High] No PR-gated CI, and no lint/SAST/dependency/secret scanning** — code deploys before verification (TECH-009).
8. **[High] Governance documentation contradicts the code** (false Spatie-RBAC claims; RLS/headers/password-reset mislabelled) — fails control of documented information (TECH-010).
9. **[Medium→High] MFA secrets & recovery codes, and OFW PII, are stored in plaintext at rest** (TECH-011, TECH-014).
10. **[Critical, regulatory] RA 10173 / DPTM privacy obligations are stated but largely unimplemented** — no PIA, no processor DPAs, no privacy notice, no retention/disposal, no data-subject-rights process (R-20; E-21/E-23/E-24/E-25).

## Critical vulnerabilities (exploitable today)
- **TECH-001** (public → staff role) is the single Critical technical vulnerability and should be closed within days.
- **TECH-002, TECH-005** are High and directly exploitable; **TECH-004** (committed `cookies.txt`) is a hygiene issue to remediate immediately.

## Controls that could not be assessed from the repository
- All **people controls** (screening, awareness, NDAs, on/offboarding, disciplinary) — ISO 27002 6.x.
- All **physical controls** — ISO 27002 7.x (PaaS-delegated; obtain provider certifications).
- **Management-system artifacts**: ISMS/QMS/SMS policies & scope, risk assessment & SoA, internal audit, management review, CAPA.
- **Service-management processes**: incident/problem/change records, service catalogue, SLAs, service reports.
- **Privacy/legal**: PIA, DPAs, privacy notice, NPC registration/DPO appointment.
- **Infrastructure specifics**: branch protection, Render worker/cron services, deployed proxy/real-IP config, production debug/ClamAV flags, secret-rotation records.

These are listed with rationale in `external-evidence-required-v1.0.0.md`. Their absence from the repo is an **evidence gap**, not a confirmed deficiency.

## Recommended remediation sequence
1. **Days:** Close TECH-001, TECH-002, TECH-004, TECH-005; confirm safe production flags (roadmap I-1…I-5).
2. **30 days:** Enforce MFA; encrypt MFA secrets; complete the audit trail; stand up PR-gated CI with scanning; reconcile docs to code; independent backup + restore test; draft the InfoSec policy and start the PIA/DPAs.
3. **60 days (Strict CSP + HTTP timeouts ✅ Completed):** Nonce-based CSP deployed via Report-Only; HTTP timeouts set on Turnstile and Cloudinary; PII-at-rest encryption pending; health-gate/rollback pending; monitoring & alerting pending; risk register + SoA pending; incident/change procedures pending; quality policy + CAPA pending.
4. **90 days & long-term:** service catalogue/SLAs; BCP/DR with RPO/RTO; capacity testing; E2E test coverage; internal audit & management-review cadence; classification scheme.

## Autogeneration triage of these deliverables (per documentation-generation policy)
All eleven reports below were **standards-resolvable and Claude-drafted** against the assumed ISO editions, but they are **REVIEW-tier**, not AUTO — each documents security-sensitive findings and organizational readiness and therefore requires named human approval (ISM/DPO/QMS owner) before use as audit input. **None** should be treated as auto-approved: risk ratings, regulatory conclusions (RA 10173), and remediation ownership are management decisions. The **HUMAN-authored** artifacts that Claude cannot generate are the management-system documents themselves (policies, SoA, risk acceptance, PIA, DPAs, management-review minutes) — listed in the evidence-request report.

## Cross-standard observations
- One root theme recurs across all four standards: **strong engineering, absent management system.** The technical sub-score (~3) consistently exceeds the governance sub-score (~1).
- A second theme is **documentation drift** (TECH-010): the docs are substantive but no longer match the code, which undermines Clause 7.5.3 in three of the four standards simultaneously — a high-leverage fix.
- Several controls are **better than the docs claim** (RLS, security headers, password reset, TOTP, Turnstile are implemented but marked "not done") — reconciling the docs will *raise* apparent conformity at no engineering cost.

## Overall conclusion
One Window Bayanihan has a **security-conscious, well-tested technical foundation** that is meaningfully ahead of its documented governance. It is **not certification-ready** for any of the four standards today: one Critical and several High technical issues are exploitable now, and the ISMS/SMS/QMS management layers and privacy artifacts are not yet in place. The gaps are **addressable** — the Critical/High technical items are mostly small, high-leverage code changes, and the governance/privacy work is well-scoped by the roadmap and evidence-request list. Closing the P0/P1 items and producing the requested external evidence would move all four standards materially toward readiness.

*Overall confidence in this assessment: **High** for code-cited technical findings; **Medium** for inferred production/infrastructure behaviour and for governance conclusions that depend on organizational evidence not present in the repository. This assessment does not constitute ISO certification or a legal/privacy compliance opinion.*
