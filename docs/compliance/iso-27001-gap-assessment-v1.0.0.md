# ISO/IEC 27001:2022 Gap Assessment — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.1.0 · Date 2026-07-08 |
| Standard | ISO/IEC 27001:2022 (Clauses 4–10, ISMS requirements) |
| Basis | Repository evidence at commit `b8a7211`. Read-only. |
| Re-verification | 2026-10-03 — every referenced path re-resolved against the working tree (HEAD `054e61a`). No finding was deleted; where a cited gap has since been remediated it is annotated with the date and the resolving evidence, and where it could not be confirmed it is marked UNVERIFIED. |
| Key limitation | ISO 27001 Clauses 4–10 are **management-system** requirements. They are satisfied by governance artifacts, records, and management activity — **not** by source code. Most are therefore *Not verifiable from the repository* and are marked as such. A low score here is an **evidence limitation**, not confirmed organizational non-conformity. |

## Changelog
| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial ISO 27001 clause-by-clause gap assessment. |
| v1.1.0 | 2026-10-03 | Citation re-verification | Re-resolved every referenced path against the working tree. Corrected the Clause 5.3 role list (four roles, not three); cited the exact location of the `SECURITY_REQUIREMENTS.md` gaps table and of `PROJECT_RULES.md` §5; added dated status notes to 7.5 (TECH-010/023 still open residuals) and 9.1 (TECH-016 now PARTIAL — Sentry wired, alert routing unevidenced). No status changed to "Implemented"; no finding removed. |

## Status legend
Implemented · Partially implemented · Not implemented · Not applicable · **Not verifiable from repository (NVR)**

> Rule applied: a management-system requirement is **never** marked "Implemented" because a technical feature exists.

---

## Clause 4 — Context of the organization
| Sub-clause | Requirement | Status | Evidence / Gap |
|---|---|---|---|
| 4.1 | External/internal issues | NVR | No context analysis in repo. SRS names DMW/OWWA/partners and legal basis (`srs_legal_section.txt`) — partial input, not a Clause 4.1 record. |
| 4.2 | Interested parties & requirements | Partially (input only) | SRS lists agencies, OFW users, RA 10173/RA 11641/DICT obligations — strong requirement *input*, but no interested-party register. |
| 4.3 | ISMS scope | Not implemented | No documented ISMS scope statement. |
| 4.4 | ISMS & its processes | Not implemented | No ISMS defined. |

## Clause 5 — Leadership
| 5.1 | Leadership & commitment | NVR | No evidence (requires management records). |
| 5.2 | Information security policy | **Not implemented** | No top-level InfoSec policy. `docs/PROJECT_RULES.md:170` (§5 *Security Principles*) is engineering principles only. |
| 5.3 | Roles, responsibilities, authorities | Partially | Technical roles exist (`CASE_MANAGER`, `ADMIN`, `AGENCY`, `OFW` — `users.role` is a plain `string(50)`, not an enum; `role:` middleware counts in `routes/web.php`: CASE_MANAGER ×8, ADMIN ×3, AGENCY ×2, OFW ×1); no assigned ISMS roles (ISM, risk owner, DPO). |

## Clause 6 — Planning
| 6.1.1 | Actions to address risks/opportunities | Not implemented | No risk-management framework (this assessment seeds one). |
| 6.1.2 | Information security risk assessment | Not implemented | No documented methodology/criteria. `docs/SECURITY_REQUIREMENTS.md:214` (§11 *Known Gaps / Future Work*) is an ad-hoc "gaps" table only. |
| 6.1.3 | Risk treatment & **Statement of Applicability** | Not implemented | No SoA. |
| 6.2 | Information security objectives | Not implemented | No measurable security objectives. |
| 6.3 | Planning of changes | Partially | CI/CD exists; no documented change-planning for the ISMS. |

## Clause 7 — Support
| 7.1 | Resources | NVR | — |
| 7.2 | Competence | NVR | No training/competence records. |
| 7.3 | Awareness | NVR | No awareness programme evidence. |
| 7.4 | Communication | NVR | — |
| 7.5 | Documented information | **Partially (with defect)** | Rich `docs/` set exists (strength), **but** it contradicts the code and is stale (TECH-010, TECH-023) → fails 7.5.3 control of documented information. *Status 2026-10-03: still open — TECH-010 and TECH-023 are both PARTIAL, not resolved. Core claims (RBAC, RLS, headers, password reset, table inventory) are reconciled; residuals are five stale "2026-05-28" doc headers and `docs/TESTING_STRATEGY.md:6,15,32` still documenting a `playwright.config.ts` / `npm run test:e2e` harness that does not exist in the tree.* |

## Clause 8 — Operation
| 8.1 | Operational planning & control | Partially | Strong technical operational controls (auth, RLS, uploads, rate limits) — see ISO 27002 assessment. Not tied to an ISMS operating procedure. |
| 8.2 | Risk assessment (operational) | Not implemented | No recurring operational risk assessment. |
| 8.3 | Risk treatment (operational) | Partially | Many controls implemented ad hoc; no treatment plan traceability. |

## Clause 9 — Performance evaluation
| 9.1 | Monitoring, measurement, analysis, evaluation | Partially/Weak | Audit logging + rate limiting present; availability target stated but unmeasured. *No centralized monitoring/alerting (TECH-016) — Status 2026-10-03: PARTIAL, not resolved. Error tracking is now wired (`sentry/sentry-laravel` in `composer.json:25`, integrated at `bootstrap/app.php:191`); alert routing, on-call and log-retention beyond the container's `json-file` rotation are still not evidenced in the repository.* |
| 9.2 | Internal audit | Not implemented | No internal audit programme. |
| 9.3 | Management review | Not implemented | No management-review records. |

## Clause 10 — Improvement
| 10.1 | Continual improvement | Partially | `docs/IMPLEMENTATION_BACKLOG.md` is an informal improvement proxy. |
| 10.2 | Nonconformity & corrective action | Not implemented | No CAPA register; bug/issue template only. |

---

## Summary — ISO 27001 readiness

> Scores below are the original point-in-time assessment (2026-07-08, commit `b8a7211`) and have **not** been re-scored at the 2026-10-03 re-verification. The clause-level status notes above are current; the maturity numbers are not.

| Dimension | Maturity (0–5) | Rationale |
|---|---|---|
| Technical implementation | 3 | Strong Annex-A-style technical controls (see 27002 report). |
| Documentation | 2 | Substantive docs exist but contradict code / no ISMS-level policy set. |
| Operational evidence | 1 | Not verifiable from repo; largely absent. |
| Governance / management system | 1 | No ISMS scope, policy, risk process, SoA, audit, or review. |
| Monitoring & continual improvement | 2 | Logging/backlog exist; no monitoring/alerting or CAPA. |
| **Overall ISMS readiness** | **~1.8 (Initial → Partial)** | Technical foundation is real; the management system does not yet exist in the repository. |

**Highest-priority ISMS gaps:** InfoSec policy (5.2), risk assessment + register + SoA (6.1), management review (9.3), internal audit (9.2), corrective action (10.2), and remediation of the documented-information defect (7.5.3 / TECH-010).

*Confidence: High that these management artifacts are absent from the repository; the correct interpretation is "evidence not present," and organizational records (if any) must be requested — see `external-evidence-required-v1.0.0.md`.*
