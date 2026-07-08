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
| v1.1.0 | 2026-07-08 | Remediation sprint | Updated Status for Phase 1 (P0 I-1–I-5) and Phase 2 (D30-1–D30-4, D60-3) completed controls. |

> This matrix is a cross-standard rollup. Full detail per control is in the individual standard reports; per-finding detail is in `technical-security-and-quality-findings-v1.0.0.md`.

| ID | Standard | Clause/Control | Requirement | Applicable | Status | Evidence | Strength | Gap | Risk | Recommendation | Priority | Effort | Evidence Needed |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| M-01 | 27002 | 5.15/8.2 | Access control & privilege | Yes | △ Enhanced | `CheckRole.php:13`; `AdminUserController`; TECH-001 | Strong | Registration gated; admin-only creation | R-01 | ✅ Completed: Registration disabled, admin-only user management (Phase 1 I-1) | P0 | S | — |
| M-02 | 27002 | 5.18 | Access revocation | Yes | ✅ Implemented | `CheckUserActive.php:20`; `AdminUserController:deactivate`; TECH-002 | Strong | is_active enforced + session deletion | R-02 | ✅ Completed: is_active gate + session kill on deactivate (Phase 1 I-2) | P0 | S | — |
| M-03 | 27002 | 8.5 | Secure authentication (MFA) | Yes | ✅ Implemented | `CheckMfaEnrolled.php`; `MfaService.php`; TECH-003/011 | Strong | MFA enforced for ADMIN; secret encrypted | R-03/R-10 | ✅ Completed: CheckMfaEnrolled middleware + encrypted mfa_secret + HMAC recovery codes (Phase 2 D30-1/D30-2) | P1 | M | — |
| M-04 | 27002 | 8.20/8.22 | Network security | Yes | ✅ Implemented | `bootstrap/app.php:39`; CIDR trustProxies | Strong | trustProxies restricted to LB CIDR | R-04 | ✅ Completed: LB CIDR whitelist + IpWhitelist middleware for admin (Phase 1 I-4) | P0 | S | LB topology |
| M-05 | 27002 | 8.15 | Logging integrity | Yes | ✅ Implemented | `AuditLog.php:boot()` SHA-256 chain | Strong | Global hash chain operational | R-05 | ✅ Completed: SHA-256 hash chain across all audit logs (Phase 2 D30-3a) | P1 | M | — |
| M-06 | 27002 | 8.15/8.16 | Logging completeness | Yes | ✅ Implemented | `LoginOtpController` audit events; `routes/auth.php` logout; `NewPasswordController` | Strong | Auth-failure/OTP/TOTP/recovery/logout/password-reset all logged | R-06 | ✅ Completed: All missing auth audit events added (Phase 2 D30-3b) | P1 | M | — |
| M-07 | 27002 | 8.13 | Backup + restore testing | Yes | Weak | `DEPLOYMENT_GUIDE §9`; TECH-008 | Weak | Provider-only, untested | R-07 | Offsite dumps + restore drill | P1 | M | Backup config, restore test record |
| M-08 | 27002 | 8.28/8.29 | Secure coding & security testing | Yes | △ Enhanced | `.github/workflows`; gitleaks; Dependabot; Pint; Larastan | Moderate | PR-gated CI running tests/SAST/scan | R-08 | ✅ Completed: PR CI with audit + lint + gitleaks + Dependabot (Phase 1 I-3/I-9/D30-4) | P1 | M | Branch-protection settings |
| M-09 | 27001/9001 | 7.5.3 | Control of documented info | Yes | 🏗️ In Progress | TECH-010/023 | Moderate | Docs being reconciled | R-21 | Reconcile docs; change control | P1 | M | — |
| M-10 | 27002 | 8.24 | Cryptography (secrets at rest) | Yes | ✅ Implemented | `User.php:69-83` `Model::encrypted` cast; `MfaService.php` HMAC-SHA256 | Strong | MFA secret encrypted + recovery codes hashed | R-10 | ✅ Completed: encrypted cast for mfa_secret + HMAC recovery codes (Phase 2 D30-2) | P1 | S | — |
| M-11 | 27002 | 8.24/5.34 | PII encryption at rest | Yes | Partial | TECH-014 | Weak | PII plaintext | R-11 | encrypted casts + key mgmt | P2 | M | Key-management approach |
| M-12 | 27002 | 8.5 | Session invalidation on reset | Yes | ✅ Implemented | `PasswordController::update`; `NewPasswordController::store`; TECH-012 | Strong | Sessions invalidated on password change/reset | R-12 | ✅ Completed: password change/reset deletes all other sessions (Phase 2 D60-3) | P2 | S | — |
| M-13 | 27002 | 8.26/8.9 | App security config (CSP) | Yes | Partial | `ContentSecurityPolicy.php:75`; TECH-013 | Moderate | unsafe-inline/eval | R-13 | Nonce-based CSP | P2 | M | — |
| M-14 | 27002 | 5.10/8.4 | Secrets in VCS | Yes | ✅ Implemented | `cookies.txt` removed; `.gitignore`; gitleaks in CI | Strong | Cookie file purged; secret scan gate active | R-09 | ✅ Completed: cookies.txt removed, history purged, gitleaks in CI pipeline (Phase 1 I-3) | P0 | S | — |
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
| M-34 | 27002 | 8.31 | Dev/test/prod separation | Yes | △ Enhanced | env configs; `LoginOtpController` env gate; `SystemSetting:debug_otp_enabled` | Strong | debug_otp gated to local/testing only via env + SystemSetting | R-17 | ✅ Completed: debug_otp restricted to local/testing environments (Phase 1 I-5) | P0 | S | — |
| M-35 | 27002 | 8.8 | Vulnerability management | Yes | △ Enhanced | Dependabot config; `composer audit` + `npm audit` in CI | Moderate | Scanning active; dep conflicts remain | R-08 | ✅ Completed: Dependabot + composer/npm audit in PR CI (Phase 1 D30-4) | P1 | S | — |

*Confidence: High for code-cited rows; EER rows are correctly flagged as requiring organizational evidence. Effort key: S ≤ ~2 days, M ≤ ~2 weeks, L > ~2 weeks or cross-functional.*
