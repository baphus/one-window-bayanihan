# ISO Remediation Roadmap — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| Basis | Findings from the alignment assessment at commit `b8a7211`. |
| Owners | Roles are *inferred* placeholders; confirm with management. ISM = Information Security Manager; DPO = Data Protection Officer. |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial remediation roadmap. |
| v1.1.0 | 2026-07-08 | Remediation sprint | Marked P0 items I-1–I-5, P1 items D30-1–D30-4, P2 item D60-3, and TECH-024 as **completed**. Added Phase 2 Waves A–C implementation. |
| v1.2.0 | 2026-07-08 | Phase 3 implementation | Marked D60-1 (nonce-based CSP + security headers), D60-4 (HTTP timeouts), and D60-7 (audit prune fix) as **completed**. |

Sequencing principle: close the **Critical/High technical** items first (fast, high risk-reduction, mostly code), then build the **management-system** layer (slower, documentation/governance), because certification depends on both but the technical items are exploitable today.

---

## Immediate actions (P0 — within days)
| # | Action | Findings | Standards | Risk reduction | Dependencies | Owner | Effort | Validation | Status |
|---|---|---|---|---|---|---|---|---|---|
| I-1 | Disable/gate public registration (invite + admin approval; domain allowlist) | TECH-001 | 27002 5.15/8.2 | Critical→Low (R-01) | none | Lead dev | S | Registration cannot create a privileged account; regression test | ✅ COMPLETED |
| I-2 | Enforce `is_active`/`is_deleted` at login + kill live sessions on deactivate | TECH-002 | 27002 5.18 | High→Low (R-02) | none | Lead dev | S | Deactivated user cannot log in; test | ✅ COMPLETED |
| I-3 | Remove `cookies.txt` from tracking + `.gitignore` + purge history; add secret-scan gate | TECH-004 | 27002 5.10/8.4 | Low→Low, hygiene | I-9 (CI) | DevOps | S | `git ls-files` clean; scanner in CI | ✅ COMPLETED |
| I-4 | Restrict `trustProxies` to the LB CIDR | TECH-005 | 27002 8.20 | High→Low (R-04) | E-38 | DevOps | S | Forged XFF ignored; admin allowlist holds | ✅ COMPLETED |
| I-5 | Confirm production `APP_DEBUG=false`, `debug_otp_enabled=false`, ClamAV enabled | TECH-020, M-15 | 27002 8.5/8.7 | Medium→Low (R-17) | E-39 | DevOps | S | Staging/prod never return `debug_otp` | ✅ COMPLETED |

## 30-day improvements (P1)
| # | Action | Findings | Standards | Risk reduction | Owner | Effort | Validation | Status |
|---|---|---|---|---|---|---|---|---|
| D30-1 | Enforce MFA for ADMIN (then CASE_MANAGER) | TECH-003 | 27002 8.5 | High→Low (R-03) | Lead dev/ISM | M | Un-enrolled admin blocked until setup | ✅ COMPLETED |
| D30-2 | Encrypt `mfa_secret`; hash recovery codes | TECH-011 | 27002 8.24 | High→Low (R-10) | Lead dev | S | Ciphertext in DB; login works | ✅ COMPLETED |
| D30-3 | Add auth-failure/logout/export audit events; fix hash chaining + verifier | TECH-006, TECH-007 | 27002 8.15/8.16 | High→Low (R-05/R-06) | Lead dev | M | Failed login + export produce audit rows; chain verifies | ✅ COMPLETED |
| D30-4 | PR-gated CI: tests + Pint + Larastan + ESLint + tsc + composer/npm audit + gitleaks; require as status check; add Dependabot | TECH-009, TECH-022, TECH-033 | 27002 8.28/8.29/8.8 | Medium→Low (R-08) | Lead dev | M | Failing PR blocked; branch protection (E-36) | ✅ COMPLETED |
| D30-5 | Reconcile all governance docs with code; add doc-review-on-change | TECH-010, TECH-023 | 27001/9001 7.5.3 | High→Low (R-21) | Lead dev/QMS | M | Sampling review: docs match code | 🏗️ IN PROGRESS |
| D30-6 | Independent encrypted offsite DB dumps + document & run a restore test | TECH-008 | 27002 8.13 | High→Low (R-07) | DevOps/ISM | M | Restore drill reproduces DB + a document | ⏳ PENDING |
| D30-7 | Draft Information Security Policy + assign ISM/DPO roles | 27001 5.2/5.3; 27002 5.1 | E-01 | Governance uplift | Management/ISM | M | Approved policy exists | ⏳ PENDING |
| D30-8 | Start PIA + execute DPAs with processors; draft privacy notice | TECH-014, R-20 | 27002 5.34/5.19; RA 10173 | Critical(reg)→Medium | DPO/Legal | L | PIA + signed DPAs (E-21/E-23/E-24) | ⏳ PENDING |

## 60-day improvements (P2)
| # | Action | Findings | Standards | Owner | Effort |
|---|---|---|---|---|---|
| D60-1 | Nonce-based strict CSP; consolidate/deduplicate security headers | TECH-013, TECH-028 | 27002 8.26/8.9 | Lead dev | M | ✅ COMPLETED |
| D60-2 | `encrypted` casts for sensitive PII + key-management approach | TECH-014 | 27002 8.24/5.34 | Lead dev/ISM | M | ⏳ PENDING |
| D60-3 | Session invalidation on password reset/change | TECH-012 | 27002 8.5 | Lead dev | S | ✅ COMPLETED |
| D60-4 | Timeouts/retries on all outbound HTTP; move non-critical to queue | TECH-017 | 27002 8.6 | Lead dev | M | ✅ COMPLETED |
| D60-5 | Deploy health-gate + pre-deploy snapshot + documented rollback; remove `continue-on-error` | TECH-018 | 20000-1 8.5.1; 9001 8.6 | DevOps | M |
| D60-6 | Error tracker (Sentry) + failed-job/security alerting; standardize `/up` health path | TECH-016, TECH-027, TECH-032 | 27002 8.16 | DevOps | M |
| D60-7 | Fix audit prune vs append-only trigger; implement tiered retention | TECH-015 | 27002 8.10/5.33; RA 10173 | Lead dev | S | ✅ COMPLETED |
| D60-8 | Risk register + Statement of Applicability (build on this assessment) | 27001 6.1 | ISM | L |
| D60-9 | Incident-response plan + change-management procedure + release approval | 27002 5.24/8.32; 20000-1 8.5.1/8.6 | ISM/DevOps | M |
| D60-10 | Quality policy & objectives; CAPA register; management-review cadence | 9001 5.2/6.2/9.3/10.2 | Management/QMS | M |
| D60-11 | Align CI runtimes (PHP 8.3/Node 20); single lockfile; fix dep conflicts | TECH-021, TECH-022 | 27002 8.28 | Lead dev | S |
| D60-12 | Confirm Render worker+cron services (or add them) | TECH-035 | 20000-1 8.6 | DevOps | S |

## 90-day improvements (P2/P3)
| # | Action | Findings | Standards | Owner | Effort |
|---|---|---|---|---|---|
| D90-1 | Service catalogue + SLAs/OLAs; service reporting & metrics | 20000-1 8.2.3/9.1 | Service owner | M |
| D90-2 | BCP/DR plan with RPO/RTO + BIA; continuity test | 27001 5.29/5.30; 20000-1 8.7.2 | ISM/DevOps | L |
| D90-3 | Capacity plan + load testing to validate 144-user & 99% targets | 27002 8.6; 20000-1 8.6.1 | DevOps | M |
| D90-4 | Implement 6 documented critical-path E2E tests + expand component tests | TECH-036 | 27002 8.29; 9001 8.6 | Lead dev | M |
| D90-5 | Parameterize RLS session vars; fail-closed on non-PG; verify pooling mode; complete RLS policy tests | TECH-026, TECH-034 | 27002 8.3/8.28 | Lead dev/DevOps | M |
| D90-6 | Notification ownership scope; upload-limit config alignment; strict_types adoption | TECH-025, TECH-029, TECH-033 | 27002 8.3/8.28 | Lead dev | S |
| D90-7 | Access-review procedure + onboarding/offboarding; supplier evaluation records | 27002 5.18/5.11/5.19 | ISM/HR | M |

## Long-term management-system maturity (P3)
- Internal audit programme; independent security review (27001 9.2; 27002 5.35).
- Information classification & handling scheme (27002 5.12/5.13).
- Security awareness/training programme with records (27001 7.3).
- Continual-improvement loop feeding CAPA from monitoring, audits, and customer feedback (all four standards, Clause 10).
- Formal SDLC policy; secure-architecture standards (27002 8.25/8.27).

---

### Expected trajectory (indicative)
Completing P0+P1 (Immediate + 30-day) closes the **Critical** and most **High** technical risks and begins the governance layer. Completing through 90 days raises the technical sub-scores toward 4 and lifts governance/operational sub-scores from 1 to ~2–3, contingent on the external evidence in `external-evidence-required-v1.0.0.md` being produced. Certification readiness for any of the four standards additionally requires the management-system artifacts, internal audit, and management review, which are organizational, not code, deliverables.

*Confidence: High on technical sequencing and effort; Medium on governance effort (depends on existing organizational maturity not visible in the repo).*
