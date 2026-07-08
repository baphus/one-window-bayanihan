# Information Risk Register — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 |
| Date | 2026-07-08 |
| Basis | Derived from repository evidence at commit `b8a7211`. Initial register for review by a qualified risk owner. |
| Method | Qualitative. Likelihood × Impact (Low/Medium/High) → rating. Ratings reflect *current* controls found in the repository. |
| Limitation | Not a formal ISO 27001 Clause 6.1.2 risk assessment (no asset valuation, no risk-acceptance criteria, no owner sign-off). This is an engineering-derived starting register. Responsible roles are *inferred* and must be confirmed. |

## Changelog

| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial risk register (22 risks). |

## Rating matrix

| | Impact: Low | Impact: Medium | Impact: High |
|---|---|---|---|
| **Likelihood: High** | Medium | High | Critical |
| **Likelihood: Medium** | Low | Medium | High |
| **Likelihood: Low** | Low | Low | Medium |

---

| Risk ID | Information asset | Threat | Vulnerability | Existing control | Likelihood | Impact | Rating | Evidence | Recommended treatment | Residual (post-treatment) | Responsible (inferred) |
|---|---|---|---|---|---|---|---|---|---|---|---|
| R-01 | OFW client PII (cases, clients, NOK, employment) | Unauthorized staff-level access by outsider | Public registration self-grants `CASE_MANAGER` | Email verification only (self-serviceable) | High | High | **Critical** | TECH-001 | Disable/gate registration; invite-only + admin approval; domain allowlist | Low | Product owner / Lead dev |
| R-02 | User accounts | Access by offboarded/compromised users | Deactivation/soft-delete not enforced at login | None in login path | Medium | High | **High** | TECH-002 | Enforce `is_active`/`is_deleted` at login + live-session kill | Low | Lead dev |
| R-03 | Admin console | Account takeover of privileged user | MFA never enforced for ADMIN | Password + email OTP | Medium | High | **High** | TECH-003 | Mandatory MFA enrolment for ADMIN/CASE_MANAGER | Low | Lead dev / ISM |
| R-04 | Admin console / rate limits | Bypass of IP allowlist; throttle evasion | `trustProxies('*')` honours client XFF | nginx real-IP (private ranges) | Medium | High | **High** | TECH-005 | Trust only the LB CIDR | Low | DevOps |
| R-05 | Audit trail | Undetected log tampering | Hash-chain never computes a hash | Append-only DB trigger (compensating) | Low | High | **Medium** | TECH-006 | Implement real hash chaining + verifier | Low | Lead dev |
| R-06 | Security monitoring | Undetected brute force / credential stuffing; unaccountable PII export | Failed logins, logouts, exports not audited | Successful-login logging only | High | Medium | **High** | TECH-007 | Add auth-failure/logout/export audit events | Low | Lead dev / ISM |
| R-07 | All data (DB + documents) | Data loss / unrecoverable outage | Single-vendor backup, no offsite, untested restore | Provider daily backup (7-day) | Medium | High | **High** | TECH-008, TECH-019 | Offsite encrypted dumps + tested restore + RPO/RTO | Low–Medium | DevOps / ISM |
| R-08 | Codebase integrity | Defective/vulnerable release reaching prod | No PR CI, no SAST/dependency/secret scan | Post-merge tests only | Medium | Medium | **Medium** | TECH-009 | PR-gated CI + scanning + branch protection | Low | Lead dev |
| R-09 | Session/secret material | Credential leak via VCS | `cookies.txt` committed | `.env` gitignored | Low | Medium | **Low** | TECH-004 | Remove + gitignore + history purge + secret-scan gate | Low | DevOps |
| R-10 | MFA secrets | 2FA defeat via data exposure | TOTP secret/recovery codes plaintext | `$hidden` (serialization only) | Medium | High | **High** | TECH-011 | Encrypt secret; hash recovery codes | Low | Lead dev |
| R-11 | OFW PII at rest | Bulk PII disclosure on DB/backup leak | No app-layer PII encryption | Postgres RLS + disk security | Medium | High | **High** | TECH-014 | `encrypted` casts on sensitive fields; key mgmt | Medium | Lead dev / ISM |
| R-12 | Web sessions | Persistent attacker session after reset | No session invalidation on password change | remember_token rotation only | Low | Medium | **Low** | TECH-012 | `logoutOtherDevices` on reset/change | Low | Lead dev |
| R-13 | Web application | Stored/reflected XSS not contained | CSP allows `unsafe-inline`/`unsafe-eval` | React auto-escaping; rehype-sanitize | Low | Medium | **Low** | TECH-013 | Nonce-based strict CSP | Low | Lead dev |
| R-14 | Availability (login/upload) | Service stall via slow third party | No timeouts/retries on outbound HTTP | php max_execution_time=60 | Medium | Medium | **Medium** | TECH-017 | Timeouts/retries; queue non-critical calls | Low | Lead dev |
| R-15 | Service delivery | Failed deploy undetected; no rollback | `continue-on-error`, no health gate, `--force` migrations | Tests run before deploy | Medium | Medium | **Medium** | TECH-018 | Health-gate + pre-deploy snapshot + rollback | Low | DevOps |
| R-16 | Audit retention | Unbounded audit growth; retention non-compliance | Prune blocked by trigger; flat 365d | Append-only trigger | Medium | Low | **Low** | TECH-015 | Archival/partition + tiered retention | Low | Lead dev |
| R-17 | Authentication | OTP factor bypass in shared env | Debug OTP in response; partial OTP logged | Env-gated to non-prod | Low | Medium | **Low** | TECH-020 | Restrict debug OTP to `local`; stop logging OTP | Low | Lead dev |
| R-18 | Async processing | Silent non-delivery of email/notifications; cleanup jobs not run | Queue/scheduler may be absent in PaaS image | Compose has workers | Medium | Medium | **Medium** | TECH-035 | Confirm/define Render worker+cron services | Low | DevOps |
| R-19 | Tenant isolation | Cross-tenant data exposure if RLS disabled | RLS soft-fails on non-PG; PgBouncer-mode sensitivity | App-layer scoping (primary today) | Low | High | **Medium** | TECH-026, TECH-034 | Fail-closed; verify pooling mode; complete RLS tests | Low | Lead dev / DevOps |
| R-20 | Personal data (regulatory) | RA 10173 / DPTM non-compliance | No PIA, retention policy, DPAs, privacy notice, consent records | `consent_given_at` field exists | High | High | **Critical** | Docs audit; TECH-014/015 | PIA + DPAs + privacy notice + retention/disposal + DSR process | Medium | DPO / Legal / ISM |
| R-21 | Documentation integrity | Misinformed decisions / audit nonconformity | Governance docs contradict code | Docs exist but stale | High | Medium | **High** | TECH-010, TECH-023 | Reconcile docs to code; doc-change control | Low | Lead dev / QMS owner |
| R-22 | Notifications | Read-state tampering | IDOR on mark-as-read | UUID identifiers | Low | Low | **Low** | TECH-025 | Scope to owning user | Low | Lead dev |

---

## Notes on the two Critical residual-risk items

- **R-01** is technically closable quickly (disable a route) — residual Low once done.
- **R-20** (regulatory) cannot be closed by code alone; residual is **Medium** even after technical work because it requires organizational artifacts (PIA, DPAs, privacy notice, DSR process) and legal/DPO review. This is an *evidence and governance* gap, not confirmed non-compliance — see `external-evidence-required-v1.0.0.md`.

*Confidence: Medium–High. Risk existence and evidence are High-confidence (code-cited); likelihood/impact ratings are qualitative engineering judgements pending validation by a designated risk owner and, for R-20, a privacy professional.*
