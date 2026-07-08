# ISO 9001:2015 Gap Assessment — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 · Date 2026-07-08 |
| Standard | ISO 9001:2015 (Quality Management System) |
| Basis | Repository evidence at commit `b8a7211`. Read-only. |
| Key limitation | ISO 9001 Clauses 4–10 are **QMS** requirements met by policy, records, and management activity. Software-development evidence (Clause 8) is partly verifiable from the repo; QMS governance (4, 5, 9.3, 10.2) is largely NVR. |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial ISO 9001 gap assessment. |

## Status legend
Implemented · Partially implemented · Not implemented · **Not verifiable from repository (NVR)**

---

## Clauses 4–7 — Context, leadership, planning, support
| Sub-clause | Requirement | Status | Evidence / Gap |
|---|---|---|---|
| 4.1–4.2 | Context / interested parties | Partial (input) | SRS captures stakeholders & requirements. |
| 4.4 | QMS & processes | Not implemented | No QMS defined. |
| 5.1–5.2 | Leadership / quality policy | Not implemented | No quality policy. |
| 5.1.2 | Customer focus | Partial | In-product SERVQUAL feedback + public feedback exist (implemented feature); no documented customer-focus process. |
| 6.1 | Risk-based thinking | Partial | This assessment + backlog; no QMS risk process. |
| 6.2 | Quality objectives | Not implemented | No measurable quality objectives. |
| 7.1.5 | Monitoring/measuring resources | Partial | Tests/CI exist; no calibration/metrics governance. |
| 7.2–7.3 | Competence / awareness | NVR | — |
| 7.5 | Documented information | Partial (defective) | Extensive docs (strength) but stale/contradictory (TECH-010/023) → 7.5.3 control defect. |

## Clause 8 — Operation (software development)
| Sub-clause | Requirement | Status | Evidence / Gap |
|---|---|---|---|
| 8.1 | Operational planning & control | Partial | `docs/PROJECT_RULES.md`, `IMPLEMENTATION_BACKLOG.md`. |
| 8.2.2–8.2.3 | Requirements determination & review | **Implemented** | `docs/REQUIREMENTS_TRACEABILITY.md` (354 reqs → impl → verification, ~84% coverage) — strong. |
| 8.3.2 | Design & development planning | Partial | Backlog + `interview/` feature specs. |
| 8.3.3–8.3.4 | Design inputs / controls / verification & validation | Partial | Tests (129 files) + traceability = V&V evidence; frontend/E2E gaps (TECH-036). |
| 8.3.5 | Design outputs | Implemented | `ARCHITECTURE.md`, `DATA_MODEL.md`, `API_CONTRACTS.md` (drifted). |
| 8.3.6 | Design changes | Partial/Weak | Git history; no formal change control (TECH-018). |
| 8.4 | Externally provided processes (suppliers) | Partial/EER | Cloud providers used; no supplier evaluation/agreements. |
| 8.5.1 | Controlled production/service provision | Partial | CI/CD; no release approval/rollback (TECH-018). |
| 8.6 | Release of products/services | Partial | Post-merge tests; not PR-gated; no acceptance sign-off (TECH-009). |
| 8.7 | Control of nonconforming outputs | Weak | Bug template + backlog; no formal nonconformity control. |

## Clauses 9–10 — Performance evaluation & improvement
| Sub-clause | Requirement | Status | Evidence / Gap |
|---|---|---|---|
| 9.1.2 | Customer satisfaction | Partial | SERVQUAL implemented in-product; no documented analysis/review process (NFR-QUAL "not formally measured"). |
| 9.1.3 | Analysis & evaluation | Weak | No quality metrics/dashboards; no monitoring (TECH-016). |
| 9.2 | Internal audit | Not implemented | None. |
| 9.3 | Management review | Not implemented | No records. |
| 10.2 | Nonconformity & corrective action | Not implemented | No CAPA register (backlog is informal proxy). |
| 10.3 | Continual improvement | Partial | Backlog + conventional commits + feature specs. |

---

## Software-development quality checklist
| Practice | Present? | Evidence |
|---|---|---|
| Traceable requirements | ✅ Strong | `REQUIREMENTS_TRACEABILITY.md` |
| Acceptance criteria / DoD | ◐ Partial | Traceability/backlog; not enforced as gates |
| Coding standards | ✅ | `instructions.md`, `CLAUDE.md`, `AGENTS.md`, `.cursorrules`, `.editorconfig` |
| Code review / branch protection | ❌ NVR | No PR CI; branch protection is external evidence (TECH-009) |
| Automated testing | ✅ Strong (backend) | 129 test files, ~984 passing; PostgreSQL CI |
| Test coverage (frontend/E2E) | ❌ Weak | TECH-036 |
| Static analysis / type check / lint / format | ❌ | None configured (TECH-009/033) |
| Build validation | ✅ | Vite build in CI |
| Security testing | ◐ | `tests/Feature/Security/*` (not PR-gated) |
| Regression testing | ◐ | Suite exists; stale "do not fix" guidance risk (TECH-023) |
| UAT | ❌ NVR | No records |
| Release approval | ❌ | No gate (TECH-018) |
| Defect tracking / RCA / CAPA | ◐ | Issue template + backlog; no RCA/CAPA process |
| Version control | ✅ | Git, mostly conventional commits |
| Documentation control | ◐ (defective) | Docs stale/contradictory (TECH-010) |
| Customer-feedback collection | ✅ | SERVQUAL feature |
| Quality metrics | ❌ | None |

---

## Summary — ISO 9001 readiness
| Dimension | Maturity (0–5) | Rationale |
|---|---|---|
| Technical implementation (dev process) | 3 | Strong requirements traceability + backend testing. |
| Documentation | 2 | Strong design/requirements docs, but no quality policy/objectives; docs drifted. |
| Operational evidence | 1 | No management-review/UAT/CAPA records. |
| Governance / management system | 1 | No QMS, quality policy, or objectives. |
| Monitoring & continual improvement | 2 | Feedback feature + backlog; no metrics/CAPA. |
| **Overall QMS readiness** | **~2.0 (Partially implemented)** | Development-quality practices are a genuine strength; QMS governance and records are absent. |

**Strongest area:** requirements review & traceability (8.2/8.3) and design V&V via testing.
**Highest-priority gaps:** quality policy & objectives (5.2/6.2), management review (9.3), nonconformity/CAPA (10.2), release approval + change control (8.5/8.6), and remediation of documentation control (7.5.3).

*Confidence: High for development-process evidence (code-cited); QMS governance correctly reported as not present in the repository.*
