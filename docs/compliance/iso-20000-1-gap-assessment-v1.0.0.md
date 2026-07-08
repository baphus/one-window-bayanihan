# ISO/IEC 20000-1:2018 Gap Assessment — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| Standard | ISO/IEC 20000-1:2018 (Service Management System) |
| Basis | Repository evidence at commit `b8a7211`. Read-only. |
| Key limitation | ISO 20000-1 is a **service-management system** standard satisfied by processes, records, agreements, and reporting. The repository shows deployment mechanics but almost none of the SMS process artifacts. Absence in the repo = **evidence gap**, not automatic non-conformity. |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial ISO 20000-1 gap assessment. |

## Status legend
Implemented · Partially implemented · Not implemented · **Not verifiable from repository (NVR)**

---

## Service management governance (Clauses 4–5, 9)
| Area | Status | Evidence / Gap |
|---|---|---|
| SMS policy & objectives | Not implemented | No service-management policy or objectives. |
| SMS scope | Not implemented | Not defined. |
| Roles & responsibilities | Partial | Technical roles only; no service-management roles (service owner, incident/change manager). |
| Document control | Partial (defective) | Docs exist but stale/contradictory (TECH-010/023). |
| Resource planning / competence / awareness | NVR | Not in repo. |
| Service reporting | Not implemented | No service reports/metrics. |
| Performance evaluation / internal audit / management review | Not implemented | None. |
| Continual improvement | Partial | `docs/IMPLEMENTATION_BACKLOG.md` informal proxy. |

## Service portfolio & relationships (Clause 8.2, 8.3)
| Area | Status | Evidence / Gap |
|---|---|---|
| Service catalogue | Not implemented | None. (`services.processing_days` column hints at per-service SLA targets — data, not a catalogue.) |
| Service ownership | Not implemented | — |
| Business relationship management | NVR | DMW/partner agencies named in SRS; no BRM process. |
| Customer requirements | Partial | SRS captures requirements strongly (input). |
| Supplier management | Partial/EER | Supabase/Cloudinary/OpenAI/Render used; no supplier agreements/OLAs/DPAs. |
| Service-level management & SLAs/OLAs | Not implemented | No SLA document. Only implicit signals: `services.processing_days`; NFR-PERF-007 "99% uptime" (unmeasured). |

## Service design, build & transition (Clause 8.5)
| Area | Status | Evidence / Gap |
|---|---|---|
| Requirements definition | Implemented | SRS + `docs/REQUIREMENTS_TRACEABILITY.md` (strong). |
| Service design | Partial | `docs/ARCHITECTURE.md`, `API_CONTRACTS.md` (drifted). |
| Release & deployment management | Partial | CI/CD deploys to staging; no release policy/approval gate; masks failures (TECH-018). |
| Build & test controls | Partial | Strong backend tests; not PR-gated; no lint/SAST (TECH-009). |
| Acceptance criteria | Partial | Present in traceability/backlog; no formal UAT/sign-off records. |
| Deployment / rollback procedures | Weak | No rollback (TECH-018). |
| Early-life support | NVR | — |
| Configuration management / CMDB | Partial | Env + schema docs; no CM plan (TECH-010 drift). |
| Change enablement | Partial/Weak | Git + CI; no documented change procedure/CAB. |

## Service operation (Clause 8.6, 8.7)
| Area | Status | Evidence / Gap |
|---|---|---|
| Incident management | Not implemented | No incident process; bug-report issue template is the closest artifact; incident IDs generated on 500s (partial technical support). |
| Service-request management | Partial | Public helpdesk/chatbot/tracking exist; no request-management process. |
| Problem management | Not implemented | No problem/known-error records. |
| Event monitoring / alerting | Weak | No centralized monitoring/alerting (TECH-016); trivial container health-check (TECH-032); health path mismatch (TECH-027). |
| Service availability management | Weak | 99% target stated, unmeasured; SPOFs (TECH-008/035). |
| Capacity management | Weak | 144-user target "not measured"; resource limits set; no capacity plan (TECH-017). |
| Information security management | Partial | Covered by 27001/27002 assessments. |
| Service continuity | Weak | No BCP/DR, no RPO/RTO (TECH-019). |
| Backup & recovery | Weak | Provider-only, untested restore (TECH-008). |
| Knowledge management | Partial | `docs/` + `interview/` specs + helpdesk content; not managed as an SMS knowledge base. |
| Escalation / communication procedures | Not implemented | None documented. |

---

## Summary — ISO 20000-1 readiness

| Dimension | Maturity (0–5) | Rationale |
|---|---|---|
| Technical implementation | 2 | Deployment/CI + rate limiting exist; monitoring/rollback/health weak. |
| Documentation | 1 | Requirements/design strong; SMS process docs absent. |
| Operational evidence | 1 | No service reports, incident/change/problem records. |
| Governance / management system | 1 | No SMS policy, roles, or reviews. |
| Monitoring & continual improvement | 1 | No monitoring/metrics; informal backlog only. |
| **Overall SMS readiness** | **~1.4 (Initial)** | Largest structural gap of the four standards: the SMS process layer is essentially absent from the repository. |

**Highest-priority SMS gaps:** service catalogue + SLA/SLM; incident, problem, and change-management procedures; monitoring/alerting; service-continuity plan with RPO/RTO; release-management with rollback; supplier agreements/OLAs.

*Confidence: High that SMS process artifacts are not in the repository. Some may exist organizationally (ticketing tool, on-call rota, provider SLAs) — request them (see `external-evidence-required-v1.0.0.md`).*
