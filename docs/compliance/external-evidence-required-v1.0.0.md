# External Evidence Required — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| Purpose | List evidence that **cannot** be obtained from the repository and must be supplied by management, HR, legal/DPO, infrastructure administrators, or the hosting providers. |
| Basis | Gaps identified across the four standard assessments. |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial external-evidence request list. |

> A "Not verifiable from repository" status in the assessments is **not** a finding of non-compliance. Items below may already exist organizationally; they simply are not in the codebase. Please supply or confirm their absence.

## A. Governance & management system
| # | Evidence | Standard(s) | Why needed |
|---|---|---|---|
| E-01 | Information Security Policy (and topic policies) | 27001 5.2; 27002 5.1 | Core ISMS requirement; none in repo. |
| E-02 | ISMS / SMS / QMS scope statements | 27001 4.3; 20000-1 4.3; 9001 4.3 | Defines certification boundary. |
| E-03 | Risk-assessment methodology + risk register + risk-treatment plan | 27001 6.1.2/6.1.3 | No risk process in repo (this assessment seeds one). |
| E-04 | Statement of Applicability (SoA) | 27001 6.1.3 | Mandatory 27001 artifact. |
| E-05 | Security & quality objectives | 27001 6.2; 9001 6.2 | Measurable objectives absent. |
| E-06 | Internal audit programme + reports | 27001/9001 9.2; 20000-1 9.2 | No audit evidence. |
| E-07 | Management-review minutes | 27001/9001 9.3; 20000-1 9.3 | No review records. |
| E-08 | Corrective-action / CAPA register | 27001/9001 10.2 | No CAPA process. |
| E-09 | Quality policy | 9001 5.2 | Absent. |
| E-10 | Service-management policy & objectives | 20000-1 5.2 | Absent. |

## B. People & organizational (ISO 27002 6.x)
| # | Evidence | Standard | Why needed |
|---|---|---|---|
| E-11 | Personnel screening records (where lawful) | 27002 6.1 | Not inferable from code. |
| E-12 | Employment/contractor terms incl. security obligations | 27002 6.2 | — |
| E-13 | Security awareness & training records | 27002 6.3; 27001 7.3 | Competence/awareness evidence. |
| E-14 | Disciplinary process | 27002 6.4 | — |
| E-15 | Onboarding/offboarding & post-termination procedure | 27002 6.5, 5.11 | Ties to access revocation (TECH-002). |
| E-16 | Confidentiality/NDA agreements | 27002 6.6 | — |
| E-17 | Remote-working policy | 27002 6.7 | — |
| E-18 | Security-event reporting procedure | 27002 6.8 | — |

## C. Physical (ISO 27002 7.x)
| # | Evidence | Standard | Why needed |
|---|---|---|---|
| E-19 | Hosting-provider physical-security certifications (Render, Supabase, Cloudinary) | 27002 7.1-7.14 | PaaS → physical controls delegated to providers; obtain their SOC 2 / ISO 27001 certs. |
| E-20 | Office/clear-desk/clear-screen & equipment-disposal policy (if staff handle PII locally) | 27002 7.7-7.10 | — |

## D. Supplier & privacy (ISO 27002 5.19-5.23, 5.31, 5.34)
| # | Evidence | Standard | Why needed |
|---|---|---|---|
| E-21 | Data-Processing Agreements with Supabase, Cloudinary, OpenAI, mail provider, Render | 27002 5.19-5.23, 5.34; RA 10173 | Cross-border PII processing (see profile §5). |
| E-22 | Supplier security-evaluation & performance-monitoring records | 27002 5.19-5.22; 20000-1 8.3.4 | Supplier management. |
| E-23 | Privacy Impact Assessment (PIA) | 27002 5.34; RA 10173 (LEGAL-023) | Explicitly required by SRS; marked "Not Done". |
| E-24 | Privacy notice, consent records, data-subject-rights (access/correction/erasure) procedure | 27002 5.34; RA 10173 | Consent field exists; no notice/DSR process. |
| E-25 | Data retention & disposal policy/schedule | 27002 8.10; RA 10173 (LEGAL-006) | Retention "proposed", prune broken (TECH-015). |
| E-26 | NPC registration / DPO appointment evidence | RA 10173 | Regulatory. |

## E. Operations & service management (ISO 20000-1, 27002 5.24-5.30)
| # | Evidence | Standard | Why needed |
|---|---|---|---|
| E-27 | Incident-response plan & incident records | 27002 5.24-5.28; 20000-1 8.6.1 | No process in repo. |
| E-28 | Problem-management / known-error records | 20000-1 8.6.3 | — |
| E-29 | Change-management procedure & change/approval records | 27002 8.32; 20000-1 8.5.1; 9001 8.5.6 | Direct-to-main deploy; no approval gate. |
| E-30 | Release records & deployment approvals | 20000-1 8.5.1; 9001 8.6 | — |
| E-31 | Service catalogue & SLAs/OLAs | 20000-1 8.2.3/8.3.3 | None. |
| E-32 | Service reports & availability/capacity metrics | 20000-1 9.1; 27002 8.6 | 99% target unmeasured; 144-user target unmeasured. |
| E-33 | Business-continuity & disaster-recovery plan; RPO/RTO; BIA | 27001 5.29/5.30; 20000-1 8.7.2 | None (TECH-019). |
| E-34 | Backup configuration + tested restore evidence | 27002 8.13 | Provider-only, untested (TECH-008). |
| E-35 | Monitoring/alerting configuration | 27002 8.16 | No app monitoring (TECH-016). |

## F. Development & infrastructure (verify with admins)
| # | Evidence | Standard | Why needed |
|---|---|---|---|
| E-36 | GitHub branch-protection settings (required reviews/checks) | 27002 8.28; 9001 8.6 | Cannot verify from repo (TECH-009). |
| E-37 | Render production service configuration (queue worker + scheduler/cron services) | 20000-1 8.6; availability | PaaS image lacks workers (TECH-035). |
| E-38 | Deployed `trustProxies` / LB CIDR & real-IP configuration | 27002 8.20 | Needed to assess TECH-005 exploitability. |
| E-39 | Production `APP_DEBUG`, `debug_otp_enabled`, ClamAV enablement values | 27002 8.5/8.7/8.9 | Confirm safe production config (TECH-020, M-15). |
| E-40 | Secrets inventory & rotation records (RENDER_API_KEY, SLACK_WEBHOOK, mail, AI keys) | 27002 5.10/8.24 | Rotation evidence. |
| E-41 | Customer-feedback analysis & satisfaction records | 9001 9.1.2 | SERVQUAL captured; analysis process not documented. |

*Confidence: High. These items were sought in the repository and not found; they are the standard organizational artifacts an ISO auditor will request. Provision of these will materially raise the governance/operational sub-scores.*
