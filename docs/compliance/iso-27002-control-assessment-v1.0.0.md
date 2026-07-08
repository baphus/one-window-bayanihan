# ISO/IEC 27002:2022 Control Assessment — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| Standard | ISO/IEC 27002:2022 (93 controls in 4 themes) |
| Basis | Repository evidence at commit `b8a7211`. Read-only. Findings referenced as TECH-nnn (see technical findings report). |
| Evidence-strength scale | Strong / Moderate / Weak / None / External-evidence-required (EER) |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial 27002 control-by-control assessment. |

> Physical controls (7.x) and most people controls (6.x) **cannot be inferred from application code** and are marked EER. Do not read technical presence as organizational conformity.

---

## 5. Organizational controls

| Control | Status | Strength | Evidence / Gap |
|---|---|---|---|
| 5.1 Policies for information security | Not implemented | None | No InfoSec policy (TECH-010 context). |
| 5.2 InfoSec roles & responsibilities | Partial | Weak | Technical roles only; no ISM/DPO assignment. |
| 5.3 Segregation of duties | Partial | Weak | Role separation ADMIN/CM/AGENCY; but public registration self-grants staff (TECH-001), and direct-to-`main` deploy lacks separation (TECH-009). |
| 5.7 Threat intelligence | Not implemented | None | — |
| 5.8 InfoSec in project management | Partial | Moderate | `docs/PROJECT_RULES.md`, SRS security requirements — but drifted (TECH-010). |
| 5.9–5.11 Asset inventory / acceptable use / return | Partial | Weak | `docs/DATA_MODEL.md` is a de-facto data asset list (stale); no acceptable-use policy. |
| 5.12–5.13 Classification / labelling | Not implemented | None | PII identified but no classification scheme. |
| 5.14 Information transfer | Partial | Moderate | TLS/SSL DB, private disks; no transfer policy. |
| 5.15 Access control | Implemented | Moderate | `CheckRole` + object-level checks (P-04); weakened by TECH-001/002. |
| 5.16 Identity management | Partial | Weak | Registration + admin user CRUD; no lifecycle/onboarding-offboarding procedure; deactivation ineffective (TECH-002). |
| 5.17 Authentication information | Partial | Moderate | Strong password policy + OTP; but MFA secrets plaintext (TECH-011), MFA unenforced (TECH-003). |
| 5.18 Access rights (review/revocation) | Partial | Weak | Admin can toggle users/sessions; no periodic access review; revocation ineffective (TECH-002). |
| 5.19–5.23 Supplier / cloud-service security | Partial | EER | Supabase/Cloudinary/OpenAI/Render used; no DPAs/supplier security agreements in repo. |
| 5.24–5.28 Incident management & evidence | Partial | Weak | Audit logs + incident IDs on 500s support forensics; no incident-response plan/runbook; audit gaps (TECH-006/007). |
| 5.29–5.30 Continuity / ICT readiness | Weak | Weak | Backup claimed; no BCP/DR, no RPO/RTO (TECH-008/019). |
| 5.31 Legal/statutory/regulatory | Partial | Moderate | RA 10173/RA 11641/DICT articulated in SRS (`srs_legal_section.txt`); implementation largely pending (PIA, retention). |
| 5.34 PII protection | Partial | Weak | `consent_given_at` field; audit redaction (P-03); but no PIA, no privacy notice, no PII-at-rest encryption (TECH-014). |
| 5.35–5.36 Independent review / compliance | Not implemented | None | No independent review or compliance-monitoring evidence. |
| 5.37 Documented operating procedures | Partial | Weak | `DEPLOYMENT_GUIDE.md` exists; no ops runbooks (backup/restore, incident). |

## 6. People controls
| 6.1–6.8 Screening, terms, awareness, disciplinary, post-termination, NDAs, remote work, event reporting | **EER** | None | Not verifiable from a code repository. Request HR/security policies and records. |

## 7. Physical controls
| 7.1–7.14 Perimeters, secure areas, physical access, equipment, disposal, clear desk, cabling | **EER** | None | Cannot be inferred from application code. Hosting is PaaS (Render/Supabase) → covered largely by provider certifications (request them). |

## 8. Technological controls

| Control | Status | Strength | Evidence / Gap |
|---|---|---|---|
| 8.1 User endpoint devices | EER | — | Out of app scope. |
| 8.2 Privileged access rights | Partial | Moderate | ADMIN + `ip.whitelist`; but XFF spoofable (TECH-005), MFA unenforced (TECH-003). |
| 8.3 Information access restriction | Implemented | Moderate | Object-level authz (P-04), RLS design; app-layer primary (TECH-034). |
| 8.4 Access to source code | EER | — | GitHub repo permissions not verifiable here; `cookies.txt` committed (TECH-004). |
| 8.5 Secure authentication | Implemented | Moderate | OTP + optional TOTP, session binding, throttling (P-06/P-07); gaps TECH-003/011/020/024. |
| 8.6 Capacity management | Weak | Weak | Container resource limits; no capacity plan; targets unmeasured; no HTTP timeouts (TECH-017). |
| 8.7 Protection against malware | Implemented | Strong | ClamAV upload scanning (P-01) — though scanner `null` by default (verify prod enablement). |
| 8.8 Management of technical vulnerabilities | Weak | Weak | No dependency-audit/SAST in CI (TECH-009); exact-pinned sensitive deps (positive). |
| 8.9 Configuration management | Partial | Moderate | Env templates, docker configs, security-headers middleware; config drift across compose/Dockerfile/.env. |
| 8.10 Information deletion | Partial | Weak | Soft-delete pattern; retention/disposal not implemented (TECH-015); deletion ≠ revocation (TECH-002). |
| 8.11 Data masking | Partial | Moderate | Audit-value redaction (P-03); reports send only aggregates to AI; no broader masking. |
| 8.12 Data leakage prevention | Weak | Weak | Private disks + signed URLs; no DLP; export unlogged (TECH-007). |
| 8.13 Backup | Weak | Weak | Provider-only, untested restore (TECH-008). |
| 8.14 Redundancy | Weak | Weak | Single DB/storage SPOFs; no failover documented. |
| 8.15 Logging | Partial | Moderate | Rich audit context (P-02/P-03) but incomplete events + broken hash chain (TECH-006/007). |
| 8.16 Monitoring | Weak | Weak | No centralized monitoring/alerting (TECH-016). |
| 8.17 Clock synchronization | Moderate | Moderate | UTC everywhere; no explicit NTP pinning. |
| 8.18 Privileged utility programs | Partial | Moderate | Admin console gated; scheduled-task toggles present. |
| 8.19 Software installation | Moderate | Moderate | `.npmrc ignore-scripts` (P-13); container images pinned. |
| 8.20–8.22 Network security / services / segregation | Partial | Moderate | nginx rate limiting + headers; RLS segregation; but `trustProxies('*')` (TECH-005). |
| 8.23 Web filtering | N/A | — | Not applicable to this workload. |
| 8.24 Cryptography | Partial | Weak | TLS + encrypted sessions; **but** MFA secrets & PII plaintext at rest (TECH-011/014); no crypto/key-mgmt policy. |
| 8.25 Secure development lifecycle | Partial | Moderate | Coding standards in `instructions.md`/`CLAUDE.md`; no formal SDLC policy; no PR gate (TECH-009). |
| 8.26 Application security requirements | Implemented | Moderate | FormRequest validation ~90%, CSRF, upload pipeline; CSP weak (TECH-013). |
| 8.27–8.28 Secure architecture / coding | Partial | Moderate | Thin controllers→services, parameterized SQL (P-11), mass-assignment discipline (P-12); no `strict_types` (TECH-033). |
| 8.29 Security testing | Partial | Moderate | Dedicated `tests/Feature/Security/*`; not run on PRs; no DAST (TECH-009/036). |
| 8.30 Outsourced development | EER | — | Not verifiable. |
| 8.31 Separation of dev/test/prod | Implemented | Moderate | Distinct env configs + staging service; debug-OTP risk in staging (TECH-020). |
| 8.32 Change management | Partial | Weak | Git + CI mechanics; no change procedure/approval gate; no rollback (TECH-018). |
| 8.33 Test information protection | Moderate | Moderate | Seeded fake data; but staging creds broadcast (TECH-030). |
| 8.34 Protection of systems during audit | EER | — | Not verifiable. |

---

## Summary — ISO 27002 readiness

| Theme | Maturity (0–5) | Note |
|---|---|---|
| Organizational (5.x) | 2 | Some access/legal controls; no policies/classification/incident/continuity/supplier docs. |
| People (6.x) | NVR/1 | Not verifiable — request HR/security records. |
| Physical (7.x) | NVR | Provider-covered; request certifications. |
| Technological (8.x) | 3 | Genuine strength: auth, uploads, rate limiting, SQL safety, hardening. Gaps in crypto-at-rest, backup, monitoring, vuln mgmt. |
| **Overall** | **~2.0 (Partially implemented)** | Technological controls carry the score; organizational/people/physical are documentation/evidence gaps. |

**Strongest controls:** 8.7 malware protection, 8.5 secure authentication, 8.3 access restriction, 8.26 application security, plus SQLi/CSRF/mass-assignment discipline.
**Weakest controls:** 8.13 backup, 8.16 monitoring, 8.24 cryptography (at rest), 8.8 vulnerability management, 5.1/5.12/5.29 policy/classification/continuity.

*Confidence: High for technological controls (code-cited); people/physical are correctly reported as not verifiable from the repository.*
