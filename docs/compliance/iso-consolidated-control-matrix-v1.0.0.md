# Consolidated ISO Control Matrix — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| Basis | Repository evidence at commit `b8a7211`. Read-only. |
| Standard editions | ISO/IEC 27001:2022, 27002:2022, 20000-1:2018, ISO 9001:2015 |
| Evidence strength | Strong / Moderate / Weak / None / EER (external-evidence-required) |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial consolidated cross-standard control matrix. |

> This matrix is a cross-standard rollup. Full detail per control is in the individual standard reports; per-finding detail is in `technical-security-and-quality-findings-v1.0.0.md`.

| ID | Standard | Clause/Control | Requirement | Applicable | Status | Evidence | Strength | Gap | Risk | Recommendation | Priority | Effort | Evidence Needed |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| M-01 | 27002 | 5.15/8.2 | Access control & privilege | Yes | Partial | `CheckRole.php:13`; TECH-001 | Moderate | Public reg self-grants staff | R-01 | Gate registration; invite+approval | P0 | S | — |
| M-02 | 27002 | 5.18 | Access revocation | Yes | Not impl | TECH-002 | Weak | Deactivation ineffective | R-02 | Enforce is_active at login | P0 | S | — |
| M-03 | 27002 | 8.5 | Secure authentication (MFA) | Yes | Partial | `LoginOtpController:120`; TECH-003 | Moderate | MFA unenforced for ADMIN | R-03 | Mandatory MFA for privileged | P1 | M | — |
| M-04 | 27002 | 8.20/8.22 | Network security | Yes | Partial | `bootstrap/app.php:39`; TECH-005 | Weak | XFF spoof bypasses allowlist | R-04 | Trust only LB CIDR | P1 | S | LB topology |
| M-05 | 27002 | 8.15 | Logging integrity | Yes | Partial | `AuditObserver:68-75`; TECH-006 | Moderate | Hash chain non-functional | R-05 | Real hash chaining + verifier | P1 | M | — |
| M-06 | 27002 | 8.15/8.16 | Logging completeness | Yes | Partial | TECH-007 | Weak | No auth-fail/logout/export logs | R-06 | Add missing audit events | P1 | M | — |
| M-07 | 27002 | 8.13 | Backup + restore testing | Yes | Weak | `DEPLOYMENT_GUIDE §9`; TECH-008 | Weak | Provider-only, untested | R-07 | Offsite dumps + restore drill | P1 | M | Backup config, restore test record |
| M-08 | 27002 | 8.28/8.29 | Secure coding & security testing | Yes | Partial | `.github/workflows`; TECH-009 | Weak | No PR CI/SAST/scan | R-08 | PR-gated CI + scanning | P1 | M | Branch-protection settings |
| M-09 | 27001/9001 | 7.5.3 | Control of documented info | Yes | Partial | TECH-010/023 | Weak | Docs contradict code | R-21 | Reconcile docs; change control | P1 | M | — |
| M-10 | 27002 | 8.24 | Cryptography (secrets at rest) | Yes | Partial | `User.php:69-83`; TECH-011 | Weak | MFA secrets plaintext | R-10 | Encrypt secret; hash recovery codes | P1 | S | — |
| M-11 | 27002 | 8.24/5.34 | PII encryption at rest | Yes | Partial | TECH-014 | Weak | PII plaintext | R-11 | encrypted casts + key mgmt | P2 | M | Key-management approach |
| M-12 | 27002 | 8.5 | Session invalidation on reset | Yes | Not impl | TECH-012 | Weak | Sessions survive reset | R-12 | logoutOtherDevices | P2 | S | — |
| M-13 | 27002 | 8.26/8.9 | App security config (CSP) | Yes | Partial | `ContentSecurityPolicy.php:75`; TECH-013 | Moderate | unsafe-inline/eval | R-13 | Nonce-based CSP | P2 | M | — |
| M-14 | 27002 | 5.10/8.4 | Secrets in VCS | Yes | Partial | `cookies.txt`; TECH-004 | Weak | Cookie file committed | R-09 | Remove + purge + scan gate | P0 | S | — |
| M-15 | 27002 | 8.7 | Malware protection | Yes | Implemented | `StorageService.php`; P-01 | Strong | Scanner null by default | — | Confirm prod enablement | P2 | S | Prod ClamAV config |
| M-16 | 27002 | 8.16 | Monitoring & alerting | Yes | Weak | `config/logging.php`; TECH-016 | Weak | No monitoring/alerting | R-14 | Error tracker + alerting | P2 | M | — |
| M-17 | 20000-1 | 8.6 | Incident management | Yes | Not impl | TECH-016 | Weak | No incident process | — | Incident procedure + tooling | P2 | M | Ticketing/on-call records |
| M-18 | 20000-1 | 8.5.1 | Release/deployment + rollback | Yes | Partial | `deploy-staging.yml:96-106`; TECH-018 | Weak | No rollback/health gate | R-15 | Health gate + snapshot + rollback | P2 | M | — |
| M-19 | 20000-1 | 8.2.3 | Service catalogue / SLA/SLM | Yes | Not impl | TECH (docs) | None | No SLA/catalogue | — | Author catalogue + SLA | P2 | M | Service reports |
| M-20 | 27001 | A.5.29/5.30 | Continuity / DR / RPO-RTO | Yes | Weak | TECH-019 | Weak | No BCP/DR | R-07 | BCP/DR + objectives | P1 | M | DR test evidence |
| M-21 | 27001 | 5.2 | Information security policy | Yes | Not impl | — | None | No ISMS policy | — | Author policy set | P1 | M | Policy documents |
| M-22 | 27001 | 6.1.2/6.1.3 | Risk assessment + SoA | Yes | Not impl | — | None | No risk process/SoA | — | Risk method + register + SoA | P1 | L | Risk assessment records |
| M-23 | 27001/9001 | 9.3 | Management review | Yes | Not impl | — | None | No reviews | — | Establish cadence | P2 | S | Review minutes |
| M-24 | 27001/9001 | 9.2 | Internal audit | Yes | Not impl | — | None | No audit programme | — | Audit programme | P2 | M | Audit records |
| M-25 | 27001/9001 | 10.2 | Nonconformity/CAPA | Yes | Not impl | backlog | Weak | No CAPA register | — | CAPA process | P2 | S | CAPA records |
| M-26 | 27002 | 5.34 | PII protection / PIA | Yes | Partial | `consent_given_at`; docs | Weak | No PIA/privacy notice/DSR | R-20 | PIA + notice + DSR process | P1 | L | PIA, DPAs, notice |
| M-27 | 27002 | 5.19-5.23 | Supplier/cloud security | Yes | Partial | services config | EER | No DPAs/agreements | R-20 | Execute DPAs; supplier eval | P1 | M | DPAs, provider certs |
| M-28 | 9001 | 8.2.3 | Requirements review/traceability | Yes | Implemented | `REQUIREMENTS_TRACEABILITY.md`; P-15 | Strong | Minor stale flags | — | Keep current | P3 | S | — |
| M-29 | 9001 | 5.2/6.2 | Quality policy & objectives | Yes | Not impl | — | None | No quality policy | — | Author policy/objectives | P2 | S | — |
| M-30 | 9001 | 9.1.2 | Customer satisfaction | Yes | Partial | SERVQUAL feature | Moderate | No analysis process | — | Document analysis/review | P2 | S | Feedback analysis records |
| M-31 | 27002 | 8.6/20000-1 8.6 | Capacity/availability | Yes | Weak | targets unmeasured; TECH-017 | Weak | Unmeasured; no timeouts | R-14 | Timeouts + capacity plan | P2 | M | Capacity/monitoring data |
| M-32 | 27002 | 6.1-6.8 | People controls | Yes | EER | — | None | Not in repo | — | Provide HR/security records | P2 | — | Screening/awareness/NDA records |
| M-33 | 27002 | 7.1-7.14 | Physical controls | Yes | EER | PaaS | None | Provider-covered | — | Provide provider certs | P3 | — | Provider certifications |
| M-34 | 27002 | 8.31 | Dev/test/prod separation | Yes | Implemented | env configs; TECH-020 | Moderate | Debug-OTP staging risk | R-17 | Restrict debug OTP to local | P2 | S | — |
| M-35 | 27002 | 8.8 | Vulnerability management | Yes | Weak | TECH-009/022 | Weak | No scanning; dep conflicts | R-08 | Dependabot + audits | P1 | S | — |

*Confidence: High for code-cited rows; EER rows are correctly flagged as requiring organizational evidence. Effort key: S ≤ ~2 days, M ≤ ~2 weeks, L > ~2 weeks or cross-functional.*
