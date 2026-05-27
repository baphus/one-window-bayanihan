# Bayanihan One Window — Implementation Backlog

> **Source:** SRS v1.2 (May 19, 2026) — Requirements Traceability Matrix gaps
> **Last Updated:** 2026-05-28

---

## 1. Backlog Overview

This document tracks all SRS requirements not yet fully implemented, organized by priority. **388 total requirements** identified; **325 implemented (84%)**, 20 partial, 33 not done.

---

## 2. Priority Definitions

| Priority | Label | Timeline | Definition |
|---|---|---|---|
| P0 | Critical | Immediate | Blocking bug, security vulnerability, legal non-compliance |
| P1 | High | This sprint | Core feature gap, major requirement missing |
| P2 | Medium | Next sprint | Important but not blocking |
| P3 | Low | Backlog | Enhancement, nice-to-have, deferred feature |

---

## 3. P0 — Critical (0 items)

No P0 items currently identified. All user-facing bugs from Wave 3 tests have been resolved.

---

## 4. P1 — High Priority

### 4.1 AI Chatbot Implementation (SRS §4.8)

**FR-AI-001 through FR-AI-007 — 7 requirements, only 14% implemented**

| SRS ID | Requirement | Effort | Dependencies |
|---|---|---|---|
| FR-AI-001 | AI chatbot interface on public platform | 3d | OpenAI-compatible API key |
| FR-AI-002 | Automated FAQ responses | 5d | FAQ content + prompt engineering |
| FR-AI-003 | Procedural guidance | 3d | Knowledge base integration |
| FR-AI-004 | Case tracking navigation assistance | 3d | Tracker number query API |
| FR-AI-005 | No confidential data exposure | 2d | PII filter layer |
| FR-AI-006 | Fallback for unresolved requests | 1d | Fallback response logic |
| FR-AI-007 | Optional chat activity logging | 2d | Chat log table + privacy review |

**Implementation approach:**
- Add `app/Services/ChatbotService.php` as abstraction layer over OpenAI API
- Add `chatbot_messages` table for conversation history (optional per FR-AI-007)
- Implement PII masking before sending to AI provider (FR-AI-005)
- Integrate with Helpdesk knowledge base for procedural guidance

**Total estimated effort: 15–19 days**

### 4.2 Duplicate Record Flagging (SRS BR-012)

**BR-012 — Business Rule, 0% implemented**

| Requirement | Implementation plan |
|---|---|
| During intake, check client name + DOB against existing records | Add `CaseService::findDuplicates()` that queries `clients` by `first_name`, `last_name`, `date_of_birth` |
| Flag potential matches for admin review | Return match list to intake form, require explicit confirmation before finalizing |

**Files to modify:**
- `app/Services/CaseService.php` — add `findDuplicates()` method
- `app/Http/Requests/StoreCaseRequest.php` — add duplicate validation rule
- `resources/js/Pages/Case/Create.jsx` — show duplicate warning modal

**Total estimated effort: 2–3 days**

### 4.3 Privacy Impact Assessment (SRS LEGAL-023)

**LEGAL-023/024 — Legal requirement, 0% implemented**

| Requirement | Action |
|---|---|
| Conduct PIA for deployment architecture | Document: data flows, cloud exposure, vendor access, international transfer risk, breach response |
| Material change re-review | Process established for architecture changes |

**Deliverable:** Privacy Impact Assessment document

**Total estimated effort: 5–10 days (legal/security review)**

### 4.4 Performance Testing (SRS §5.1)

**NFR-PERF-001 through NFR-PERF-008 — 8 requirements, 0% measured**

| SRS ID | Target | Test Method |
|---|---|---|
| NFR-PERF-001 | Login/dashboard ≤ 3s | Browser load testing (Lighthouse, WebPageTest) |
| NFR-PERF-002 | Data retrieval ≤ 5s | Query profiling, pagination testing |
| NFR-PERF-003 | Real-time sync ≤ 2s | Deferred until WebSocket implemented |
| NFR-PERF-004 | 144 concurrent users | Load test (K6, Locust, or Artillery) |
| NFR-PERF-005 | Document load ≤ 3s | Cloudinary CDN latency test |
| NFR-PERF-006 | Reports ≤ 10s | Query optimization + caching |
| NFR-PERF-007 | 99% uptime | Monitor with Render + Supabase SLA |
| NFR-PERF-008 | Scalable architecture | Architecture review |

**Total estimated effort: 5–8 days**

---

## 5. P2 — Medium Priority

### 5.1 Application-Layer Encryption for Sensitive Fields (SRS NFR-SEC-024)

| Field | Location | Encryption Method |
|---|---|---|
| Passport numbers | `clients` (via `client_employments` or migration) | Laravel cast encryption |
| Addresses | `client_addresses` | Laravel cast encryption |
| Emergency contact details | `next_of_kin` | Laravel cast encryption |
| Financial assistance data | Future field | Laravel cast encryption |

**Approach:** Use Laravel's `Illuminate\Contracts\Encryption\Encrypter` with `encrypt` / `decrypt` casts on model attributes. Add a migration with encrypted column types.

**Total estimated effort: 3–5 days**

### 5.2 PostgreSQL RLS Policies (SRS NFR-SEC-028, DB-008–012)

| Policy | Description |
|---|---|
| `cases` RLS | DMW sees all, agencies see only their referred cases |
| `referrals` RLS | Agencies see only rows where `agcy_id` matches their agency |
| `milestones` RLS | Same as referrals |
| `clients` RLS | DMW sees all, agencies see lane-limited |

**Approach:** Enable RLS on key tables in a new migration, create policies using `current_setting('app.user_id')` or database roles.

**Total estimated effort: 2–3 days**

### 5.3 CSV Report Export (SRS FR-ANA-004)

**Currently:** PDF export via DOMPDF  
**Missing:** CSV export

**Approach:** Add `ReportsController@exportCsv` that streams CSV response using Laravel's `streamedResponse()`.

**Total estimated effort: 1–2 days**

### 5.4 Data Retention Policy (SRS LEGAL-006)

| Item | Action |
|---|---|
| Retention schedule for case records | Define retention period per record type |
| Automated archival/purging | Queue job for periodic cleanup |
| Audit log retention | Minimum retention period defined |

**Total estimated effort: 3–5 days (policy + implementation)**

### 5.5 Security Headers (SRS NFR-SEC-035 extension)

| Header | Value | Purpose |
|---|---|---|
| `Content-Security-Policy` | Restrict script sources | Prevent XSS |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Enforce HTTPS |
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-Frame-Options` | `DENY` | Prevent clickjacking |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Control referrer info |

**Approach:** Add `app/Http/Middleware/SecurityHeaders.php` middleware.

**Total estimated effort: 1 day**

### 5.6 Accessibility CI Check (SRS ACC-029)

**Approach:** Integrate `axe-core` into the build pipeline (e.g., using `@axe-core/playwright` or `jest-axe` for component tests).

**Total estimated effort: 2–3 days**

---

## 6. P3 — Low Priority / Deferred

### 6.1 Real-Time WebSocket Sync (SRS COM-PERF-002)

| Item | Status |
|---|---|
| Pusher integration | Pending — SRS assumes WebSocket for live dashboards |
| Laravel Echo setup | Not configured |
| Broadcasting events | Not implemented |

**Note:** Deferred per project constraints (AGENTS.md). Current DB-driven sync is sufficient for v1.0.

**Total estimated effort: 5–8 days (when picked up)**

### 6.2 AI Security Controls (SRS §5.3.10, LEGAL-033–036)

**NFR-SEC-058 through NFR-SEC-060 — Only relevant if AI chatbot enabled**

| Requirement | Implementation |
|---|---|
| No OFW PII to AI | PII masking layer |
| Sensitive data protection | Prompt sanitization |
| Privacy governance | AI usage policy |

**Deferred until AI Chatbot (P1 #4.1) is implemented.**

### 6.3 Persistent OFW Profile (SRS TBD-015)

**Current:** Case-centric model — each case has its own client data  
**Future:** Enterprise-wide reusable OFW profile across multiple cases

**Deferred:** v1.0 is case-centric per TBD-015.

### 6.4 Cross-Referrals (SRS FR-REF-013)

**Current:** Only DMW creates referrals  
**Future:** Agencies can cross-refer to other agencies

**Explicitly excluded from v1.0 per SRS TBD-014.**

### 6.5 SMS Notifications

**Current:** Email-only notifications  
**Future:** SMS for OTP delivery, referral alerts, case updates

**Requires:** SMS provider integration, operational budget.

### 6.6 Multi-Region Rollout (SRS §6.3)

**Current:** Region VII focus  
**Future:** Blueprint for other DMW regional offices

**Architecture requirement:** Codebase and documentation maintained for replicability.

---

## 7. Deferred Features (from SRS TBD List)

| TBD ID | Item | Status | Notes |
|---|---|---|---|
| TBD-001 | Participating agencies | ✅ Finalized | DMW, OWWA, DOLE, TESDA, DSWD, DOH, Law Center Inc., Region VII LGUs |
| TBD-002 | Intake fields | ✅ Finalized | Personal info, vulnerability flags, employment, next-of-kin, case narrative |
| TBD-003 | Case summary format | ✅ Finalized | Structured narrative text |
| TBD-005 | Attachment handling | ✅ Finalized | Cloudinary with app-layer validation |
| TBD-006 | Notification channels | ✅ Finalized | Email v1.0; SMS deferred |
| TBD-007 | Analytics | ✅ Finalized | Dashboards, reports, descriptive analytics |
| TBD-008 | Case closure rules | ✅ Finalized | All referrals terminal before closure |
| TBD-009 | Lane-based access | ✅ Finalized | Agencies update only their lanes |
| TBD-010 | Referral deadline monitoring | ✅ Finalized | Processing days per service, overdue thresholds |
| TBD-011 | OFW tracking | ✅ Finalized | Tracker # + OTP per manuscript |
| TBD-012 | SERVQUAL | ✅ Finalized | In evaluation/compliance scope |
| TBD-013 | Consent process | ✅ Finalized | DMW-assisted registration |
| TBD-014 | Cross-referrals | ✅ Excluded | v1.0: DMW only |
| TBD-015 | Persistent OFW profile | 🔴 Deferred | v1.0 is case-centric |

---

## 8. Effort Summary

| Item | Priority | Effort (days) |
|---|---|---|
| AI Chatbot | P1 | 15–19 |
| Duplicate Flagging | P1 | 2–3 |
| Privacy Impact Assessment | P1 | 5–10 |
| Performance Testing | P1 | 5–8 |
| **Subtotal P1** | | **27–40** |
| App-Layer Encryption | P2 | 3–5 |
| PostgreSQL RLS Policies | P2 | 2–3 |
| CSV Export | P2 | 1–2 |
| Data Retention Policy | P2 | 3–5 |
| Security Headers | P2 | 1 |
| Accessibility CI | P2 | 2–3 |
| **Subtotal P2** | | **12–19** |
| WebSocket Sync | P3 | 5–8 |
| AI Security Controls | P3 | *part of AI Chatbot* |
| Persistent OFW Profile | P3 | *future* |
| SMS Notifications | P3 | 3–5 |
| Cross-Referrals | P3 | 5–8 |
| **Subtotal P3** | | **13–21** |
| **TOTAL** | | **52–80** |

---

## 9. Recommended Sprint Plan

### Sprint 1: AI Core + Duplicate Flagging (17–22 days)
- AI Chatbot: interface + FAQ + procedural guidance (FR-AI-001–004)
- Duplicate record flagging (BR-012)
- Security headers middleware

### Sprint 2: AI Security + PIA + Encryption (13–18 days)
- AI data protection (FR-AI-005–007, NFR-SEC-058–060)
- Privacy Impact Assessment (LEGAL-023)
- Application-layer encryption (NFR-SEC-024)

### Sprint 3: Data Governance + RLS (10–13 days)
- PostgreSQL RLS policies (DB-008–012)
- Data retention policy (LEGAL-006)
- CSV export (FR-ANA-004)

### Sprint 4: Performance + Testing (10–13 days)
- Performance testing & optimization (NFR-PERF)
- Accessibility CI integration (ACC-029)
- Load testing for 144 CCU (NFR-PERF-004)
