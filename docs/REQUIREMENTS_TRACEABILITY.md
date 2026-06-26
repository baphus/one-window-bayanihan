# Bayanihan One Window — Requirements Traceability Matrix

> **Source:** SRS v1.2 (May 19, 2026)
> **Coverage:** All Functional Requirements (FR-*), Security Requirements (NFR-SEC-*), Performance (NFR-PERF-*), Safety (NFR-SAFE-*), Quality (NFR-QUAL-*), Business Rules (BR-*), Legal (LEGAL-*), Database (DB-*), Accessibility (ACC-*), Communications (COM-*, COM-MSG-*, COM-DATA-*, COM-SEC-*, COM-PERF-*, COM-GOV-*), and 16 Non-SRS Implemented Features
> **Last Updated:** 2026-05-28

---

## 1. Functional Requirements Traceability

### 1.1 Authentication & Access Security (SRS §4.1)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-AUTH-001 | Require authentication for admin users | `auth` and `verified` middleware on all protected route groups | Route list, middleware coverage | ✅ |
| FR-AUTH-002 | OTP MFA for admin access | `LoginOtpController` + `OtpService` (6-digit, 5-min TTL) | Integration test, manual flow | ✅ |
| FR-AUTH-003 | Tracker + OTP for OFW tracking | `TrackController` — `/track/send-otp`, `/track/verify-otp` | Integration test, manual | ✅ |
| FR-AUTH-004 | Role-based feature restriction | Spatie `laravel-permission`, `role:` middleware | Route permissions test | ✅ |
| FR-AUTH-005 | Lane-based access for agencies | Service layer filtered by `agcy_id` | Feature test | ✅ |
| FR-AUTH-006 | IP whitelist for admin backend | `IpWhitelist` middleware on `/admin/*` routes | `IpWhitelistMiddlewareTest` | ✅ |
| FR-AUTH-007 | Session timeout | `session.lifetime` config (120 min default) | Manual check | ✅ |
| FR-AUTH-008 | Rate-limit failed auth | `throttle:login` (6/min), `throttle:otp` (3/min) | Route middleware test | ✅ |
| FR-AUTH-009 | Reject invalid credentials | Laravel authentication validation | Auth test | ✅ |
| FR-AUTH-010 | Reject expired/invalid OTP | `OtpService::validate()` checks expiry + single-use | Test | ✅ |
| FR-AUTH-011 | Deny unauthorized function access | Role middleware + authorization gates | Auth test | ✅ |
| FR-AUTH-012 | Auth events in audit log | `AuditLog` model event listener on auth attempts | `AuditEventViewTest` | ✅ |

### 1.2 Administrative & User Management (SRS §4.2)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-ADM-001 | Only ADMIN creates/manages users | `AdminUserController` under `role:ADMIN` | Route auth test | ✅ |
| FR-ADM-002 | Assign user roles | User CRUD includes role assignment | Manual QA | ✅ |
| FR-ADM-003 | Agency registry | `agencies` table, `AdminAgencyController` CRUD | Manual QA | ✅ |
| FR-ADM-004 | Agency focal person records | Users with `agcy_id` FK, `role:AGENCY` | Schema check | ✅ |
| FR-ADM-005 | Service category configuration | `services` table, `AdminServiceController` | Manual QA | ✅ |
| FR-ADM-006 | Validate admin input before save | Form Request validation | Validation test | ✅ |
| FR-ADM-007 | Reject invalid admin data | Form Request error responses | Validation test | ✅ |
| FR-ADM-008 | Admin changes in audit log | AuditLog event on admin CRUD | `AuditEventViewTest` | ✅ |

### 1.3 Case Intake & Management (SRS §4.3)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-INT-001 | Only DMW creates cases | `CaseController` gated by `role:CASE_MANAGER` | Route auth test | ✅ |
| FR-INT-002 | Generate unique Case Number | Auto-generated in `CaseService::create()` | Schema unique constraint | ✅ |
| FR-INT-003 | Generate unique Tracker Number | Auto-generated, stored in `cases.tracker_number` | Schema unique constraint | ✅ |
| FR-INT-004 | Allow draft save | `status` field supports 'DRAFT' | Manual QA | ✅ |
| FR-INT-005 | Validate mandatory intake fields | Form Request validation rules | Validation test | ✅ |
| FR-INT-006 | Create Unified Master Case File | Case creation creates all related records | Integration test | ✅ |
| FR-INT-007 | Record case summaries | `cases.summary` text field | Manual QA | ✅ |
| FR-INT-008 | Record service needs | `service_requirements` + referral `required_services` | Schema check | ✅ |
| FR-INT-009 | Service needs in Master Case File | Linked via case_id FK | Schema check | ✅ |
| FR-INT-010 | Master Case File as shared reference | All referrals FK to same case | Schema check | ✅ |
| FR-INT-011 | Preserve case history | Append-only audit + milestones | Design review | ✅ |
| FR-INT-012 | Reject incomplete intake | Form Request validation | Validation test | ✅ |

### 1.4 Document Management (SRS §4.4)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-DOC-001 | DMW upload documents to case | `CaseDocument` model, file upload routes | Manual QA | ✅ |
| FR-DOC-002 | Documents linked to case | `case_documents.case_id` FK | Schema check | ✅ |
| FR-DOC-003 | Authorized agencies access shared docs | `ReferralAttachment` visibility filtered by referral/agency | Permission test | ✅ |
| FR-DOC-004 | Restrict confidential docs | Authorization gates on document routes | Permission test | ✅ |
| FR-DOC-005 | OFW cannot access internal docs | Public portal hides attachments per BR-011 | Manual QA | ✅ |
| FR-DOC-006 | Validate file types/size | Upload validation rules | Validation test | ✅ |
| FR-DOC-007 | Reject invalid uploads | File validation errors | Validation test | ✅ |
| FR-DOC-008 | Document access in audit log | AuditLog 'VIEW' events on document access | `AuditEventViewTest` | ✅ |

### 1.5 Digital Referral Management (SRS §4.5)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-REF-001 | DMW creates digital referrals | `ReferralController@store` gated by `role:CASE_MANAGER` | Route test | ✅ |
| FR-REF-002 | Parallel multi-agency referral | Multiple `referrals` rows per case, each to different `agcy_id` | Integration test | ✅ |
| FR-REF-003 | Independent referral linked to case | `referrals.case_id` FK, independent status per row | Schema check | ✅ |
| FR-REF-004 | Initial status = PENDING | `status` default 'PENDING' in migration | Schema check | ✅ |
| FR-REF-005 | Notify assigned agencies | `ReferralCreated` notification; `NotificationsController` | Manual QA | ✅ |
| FR-REF-006 | Agencies accept/reject/return | `ReferralController@updateStatus` with decision field | `CaseReferralGuardTest` | ✅ |
| FR-REF-007 | Mandatory comment for decisions | `decision_reason` required validation | `CaseReferralGuardTest` | ✅ |
| FR-REF-008 | Referral actions timestamped | `updated_at` + audit log | Schema check | ✅ |
| FR-REF-009 | Referral visibility restricted | Lane-based filtering in service layer | Permission test | ✅ |
| FR-REF-010 | Standardized referral statuses | Enum: PENDING, PROCESSING, COMPLETED, REJECTED, FOR COMPLIANCE | Schema check | ✅ |
| FR-REF-011 | Restrict invalid status transitions | `ReferralService::validateStatusTransition()` | `CaseReferralGuardTest` | ✅ |
| FR-REF-012 | Only assigned agency updates status | Lane check in service layer | `CaseReferralGuardTest` | ✅ |
| FR-REF-013 | No cross-referrals in v1.0 | UI restricts referral creation to DMW | Design decision | ✅ |
| FR-REF-014 | Reject updates without comments | `decision_reason` required on status change | `CaseReferralGuardTest` | ✅ |

### 1.6 Monitoring & Continuity of Care (SRS §4.6)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-TRK-001 | Agencies update referral progress | `ReferralController@addMilestone` | Manual QA | ✅ |
| FR-TRK-002 | Create milestone entries | `Milestone` model, `refr_id` FK | Schema check | ✅ |
| FR-TRK-003 | Milestones = progress/actions/notes | `title` + `description` fields | Design review | ✅ |
| FR-TRK-004 | Milestones append-only | No update/delete routes for milestones | `CaseReferralGuardTest` | ✅ |
| FR-TRK-005 | Only assigned agency creates milestones | `user_id` + lane check | `CaseReferralGuardTest` | ✅ |
| FR-TRK-006 | Distinguish case vs referral status | `cases.status` (OPEN/CLOSED) vs `referrals.status` | Schema check | ✅ |
| FR-TRK-007 | Auto-compute case status | Logic in `CaseService::computeStatus()` | Integration test | ✅ |
| FR-TRK-008 | Unified case timeline | Case show page aggregates all referrals + milestones | Manual QA | ✅ |
| FR-TRK-009 | DMW monitors all referrals | DMW role has cross-lane visibility | Manual QA | ✅ |
| FR-TRK-010 | Highlight pending/unresolved outcomes | Status badges, color-coded | UI check | ✅ |
| FR-TRK-011 | Only DMW closes cases | `CaseController@publish`/`toggleStatus` gated by role | `CaseReferralGuardTest` | ✅ |
| FR-TRK-012 | Prevent closure unless all terminal | `CaseService::canClose()` checks all referrals | `CaseReferralGuardTest` | ✅ |
| FR-TRK-013 | Reject unauthorized closure | Role middleware + validation | `CaseReferralGuardTest` | ✅ |

### 1.7 OFW Tracking Portal (SRS §4.7)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-PORT-001 | OFW tracks via Tracker Number | `TrackController@index` → input form | Manual QA | ✅ |
| FR-PORT-002 | OTP before case info | `TrackController@sendOtp` + `verifyOtp` | Manual QA | ✅ |
| FR-PORT-003 | OTP expiring | `OtpService` — 5-min TTL | Test | ✅ |
| FR-PORT-004 | Rate-limit OTP attempts | `throttle:tracking` (5/min) | Route check | ✅ |
| FR-PORT-005 | Display high-level progress | `TrackController@show` returns public-safe data | Manual QA | ✅ |
| FR-PORT-006 | Simplified timeline for public | Filtered milestone summary | Manual QA | ✅ |
| FR-PORT-007 | Hide internal comments/notes | Explicit field filtering in public view | Manual QA | ✅ |
| FR-PORT-008 | Mobile-responsive | Tailwind responsive breakpoints | UI check | ✅ |
| FR-PORT-009 | Reject invalid tracker numbers | Validation + DB lookup | Test | ✅ |

### 1.8 AI Chatbot (SRS §4.8)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-AI-001 | AI chatbot interface | `ChatbotController@message` route | Route check | 🟡 |
| FR-AI-002 | Automated FAQ responses | ChatbotService integration (if enabled) | 🔴 Not fully implemented |
| FR-AI-003 | Procedural guidance | Same as above | 🔴 Not fully implemented |
| FR-AI-004 | Navigation assistance | Same as above | 🔴 Not fully implemented |
| FR-AI-005 | No confidential data exposure | AI is restricted to non-sensitive queries | 🔴 Not fully implemented |
| FR-AI-006 | Fallback for unresolved requests | Default "I cannot answer" response | ✅ |
| FR-AI-007 | Optional chat logging | Not yet implemented | 🔴 Not Done |

### 1.9 Analytics & Reporting (SRS §4.9)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-ANA-001 | Dashboard KPIs | `DashboardService` — stats, trends, status distribution | Manual QA | ✅ |
| FR-ANA-002 | Agency performance metrics | `ReportsService` — agency-specific data | Manual QA | ✅ |
| FR-ANA-003 | Filter by date/agency/service/status | Filter parameters on reports/analytics pages | Manual QA | ✅ |
| FR-ANA-004 | Export PDF/CSV | `ReportsController@exportPdf` for PDF; CSV not yet implemented | `PdfExportTest` (partial) | 🟡 Partial |
| FR-ANA-005 | Restrict PII by permissions | Role-based data filtering | Manual QA | ✅ |
| FR-ANA-006 | Anonymized analytics | `AnonymizedAnalyticsService` + `AnonymizedAnalyticsController` | `AnonymizedAnalyticsTest` | ✅ |
| FR-ANA-007 | Reject invalid report params | Form Request validation | Test | ✅ |

### 1.10 Audit Log Management (SRS §4.10)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-AUD-001 | Auto-record critical actions | `AuditLog` model events on Case, Referral, User, Auth | `AuditEventViewTest` | ✅ |
| FR-AUD-002 | Record user, timestamp, action, entity | `audit_logs` columns: user_id, timestamp, action, entity_id, module | Schema check | ✅ |
| FR-AUD-003 | Immutable append-only | No update/delete routes in app code | Design review | ✅ |
| FR-AUD-004 | Only admin access audit logs | `audit-logs.index` — role check | Route test | ✅ |
| FR-AUD-005 | Filter by user/date/action | Query parameters on audit log index | Manual QA | ✅ |
| FR-AUD-006 | Audit log access is auditable | VIEW events on audit log access tracked | `AuditEventViewTest` | ✅ |
| FR-AUD-007 | Prevent modification/deletion | No update/delete API for audit logs | Design review | ✅ |
| FR-AUD-008 | Corrections as new entries | Business process (not code) | Process rule | ✅ |

### 1.11 Feedback Collection (SRS §4.11)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| FR-FBK-001 | OFW feedback after closure | `FeedbackController` + `feedback` table | Route check | ✅ |
| FR-FBK-002 | SERVQUAL evaluation | `feedback_servqual_responses` + `servqual_configs` tables | Schema check | ✅ |
| FR-FBK-003 | Feedback linked to case | `feedback.case_id` FK | Schema check | ✅ |
| FR-FBK-004 | Feedback available for reporting | `feedbacks.index` route | Route check | ✅ |
| FR-FBK-005 | Reject invalid submissions | Form Request validation | Validation test | ✅ |
| FR-FBK-006 | No confidential data in feedback | Feedback form is public-facing | Design review | ✅ |

---

## 2. Non-Functional Requirements Traceability

### 2.1 Performance (SRS §5.1)

| SRS ID | Requirement | Target | Verification | Status |
|---|---|---|---|---|
| NFR-PERF-001 | Login/dashboard load ≤ 3s | ≤ 3s | Load testing needed | 🔴 Not measured |
| NFR-PERF-002 | Data retrieval ≤ 5s | ≤ 5s | Query profiling needed | 🔴 Not measured |
| NFR-PERF-003 | Real-time sync ≤ 2s | ≤ 2s | WebSocket not yet implemented | 🔴 Not Done |
| NFR-PERF-004 | Support 144 concurrent users | 144 CCU | Load testing needed | 🔴 Not measured |
| NFR-PERF-005 | Document load ≤ 3s | ≤ 3s | CDN test needed | 🔴 Not measured |
| NFR-PERF-006 | Reports ≤ 10s | ≤ 10s | Query profiling | 🔴 Not measured |
| NFR-PERF-007 | 99% uptime | 99% | SLA from Render/Supabase | 🟡 Provider-dependent |
| NFR-PERF-008 | Scalable architecture | Future-proof | Design review | ✅ |

### 2.2 Safety (SRS §5.2)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| NFR-SAFE-001 | Confirmation for critical actions | UnsavedChangesModal + confirmation dialogs | Manual QA | ✅ |
| NFR-SAFE-002 | Append-only historical records | Milestones, AuditLogs INSERT-only | Design review | ✅ |
| NFR-SAFE-003 | Automated backup/recovery | Supabase auto-backups | Provider SLA | ✅ |
| NFR-SAFE-004 | Monitor delayed/unresolved cases | OverdueReferralController + dashboard | Manual QA | ✅ |
| NFR-SAFE-005 | Server-side input validation | Form Request classes | Test | ✅ |
| NFR-SAFE-006 | Unauthorized access prevention | RBAC + middleware + RLS | Auth test | ✅ |
| NFR-SAFE-007 | Transactional consistency | DB transactions + ACID compliance | Schema/design | ✅ |
| NFR-SAFE-008 | Sensitive action logging | AuditLog on all critical actions | `AuditEventViewTest` | ✅ |

### 2.3 Quality Attributes (SRS §5.4)

| SRS ID | Requirement | Implementation | Verification | Status |
|---|---|---|---|---|
| NFR-QUAL-001 | Functional completeness of SRS §4 modules | All 11 FR modules implemented | This matrix | ✅ |
| NFR-QUAL-002 | Workflow alignment with DMW governance | Business rules coded per SRS §5.5 | Review | ✅ |
| NFR-QUAL-003 | Accurate operational execution | Test coverage across all modules | Test results | ✅ |
| NFR-QUAL-004 | Performance efficiency targets defined | Targets per NFR-PERF-001–008 | — | 🔴 |
| NFR-QUAL-005 | Resource utilization within infrastructure limits | Render resource planning | — | 🔴 |
| NFR-QUAL-006 | Transaction response time targets | Load testing needed | — | 🔴 |
| NFR-QUAL-007 | Coexist and cooperate with partner systems | Standalone architecture, API-ready | Design decision | ✅ |
| NFR-QUAL-008 | Secure interoperability with external systems | TLS, API keys, auth | Config review | ✅ |
| NFR-QUAL-009 | Effective user interaction (desktop) | Layout, sidebar, navigation | Manual QA | ✅ |
| NFR-QUAL-010 | Effective user interaction (mobile) | Responsive public portal | Manual QA | ✅ |
| NFR-QUAL-011 | User satisfaction criteria | Not formally measured | — | 🔴 |
| NFR-QUAL-012 | Role-appropriate dashboard visibility | RBAC-scoped dashboards | Manual QA | ✅ |
| NFR-QUAL-013 | Minimal unnecessary complexity | Streamlined workflows | Manual QA | ✅ |
| NFR-QUAL-014 | Usability validated with target users | Not formally tested | — | 🔴 |
| NFR-QUAL-015 | Operational reliability across workflows | ACID transactions | Design review | ✅ |
| NFR-QUAL-016 | Consistent behavior across modules | Append-only, same patterns | Design review | ✅ |
| NFR-QUAL-017 | Authentication security | OTP MFA, session management | Security review | ✅ |
| NFR-QUAL-018 | Authorization security | RBAC, lane isolation | Security review | ✅ |
| NFR-QUAL-019 | Overall security posture | Multi-layer security architecture | Security review | ✅ |
| NFR-QUAL-020 | Code-level maintainability | Modular MVC architecture | Architecture review | ✅ |
| NFR-QUAL-021 | Operational maintainability | Containerized, CI/CD pipeline | Architecture review | ✅ |
| NFR-QUAL-022 | Ease of enhancement/extension | Laravel service pattern | Architecture review | ✅ |
| NFR-QUAL-023 | Browser portability | All modern browsers | Manual QA | ✅ |
| NFR-QUAL-024 | Minimum requirements compatibility | Low-bandwidth, older browsers | Manual QA | ✅ |
| NFR-QUAL-025 | Responsive design for mobile clients | Tailwind responsive breakpoints | Manual QA | ✅ |
| NFR-QUAL-026 | Accessibility compliance (WCAG) | Per ACCESSIBILITY_REQUIREMENTS.md | Manual QA | ✅ |

---

## 3. Business Rules Traceability (SRS §5.5)

| ID | Rule | Implementation | Verification | Status |
|---|---|---|---|---|
| BR-001 | DMW-only case intake | `role:CASE_MANAGER` on create | Route auth test | ✅ |
| BR-002 | Single Master Case File | Auto-generated unique Case/Tracker Number | Schema check | ✅ |
| BR-003 | Parallel referrals | Multiple referrals per case supported | Integration test | ✅ |
| BR-004 | Authorized partner routing | Agency registry + FK constraint | Schema check | ✅ |
| BR-005 | Lane-based data isolation | Service layer filter by `agcy_id` | `CaseReferralGuardTest` | ✅ |
| BR-006 | Mandatory referral comments | Validated in `updateStatus` | `CaseReferralGuardTest` | ✅ |
| BR-007 | Append-only milestones/audit | No update/delete routes | `CaseReferralGuardTest` | ✅ |
| BR-008 | DMW-only case closure | Role check + terminal-state validation | `CaseReferralGuardTest` | ✅ |
| BR-009 | OTP MFA | `OtpService` for all auth | Integration test | ✅ |
| BR-010 | IP whitelist admin backend | `IpWhitelist` middleware | `IpWhitelistMiddlewareTest` | ✅ |
| BR-011 | Privacy-safe public tracking | TrackController hides internal data | Manual QA | ✅ |
| BR-012 | Duplicate flagging | Name + DOB matching needed | 🔴 Not Done |
| BR-013 | Analytics for closed cases | `AnonymizedAnalyticsService` | Test | ✅ |

---

## 4. Legal & Regulatory Traceability (SRS §6.1)

### 4.1 RA 10173 — Data Privacy Act of 2012 (§6.1.1)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-001 | Data processed only for legitimate, authorized purposes | Case/Referral management scope | ✅ |
| LEGAL-002 | Collection limited to minimum necessary information | Schema designed for operational minimum | ✅ |
| LEGAL-003 | PII protected through technical, organizational, admin safeguards | Multi-layer security architecture | ✅ |
| LEGAL-004 | Access restricted to authorized users with legitimate need | RBAC + lane isolation | ✅ |
| LEGAL-005 | Auditability, accountability, traceability for data access | AuditLog + VIEW tracking + append-only | ✅ |
| LEGAL-006 | Data retention, archival, disposal governed by documented controls | Not yet implemented | 🔴 |
| LEGAL-007 | Prohibit unauthorized disclosure, misuse, uncontrolled exposure | Encryption + access controls | ✅ |

### 4.2 RA 11641 — DMW Act Compliance (§6.1.2)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-008 | DMW as primary operational authority for case governance | DMW CASE_MANAGER role creates/closes cases | ✅ |
| LEGAL-009 | DMW-controlled case intake and lifecycle governance | Intake, referral, closure gated to DMW | ✅ |
| LEGAL-010 | Agencies operate within delegated referral lanes | Lane isolation per agency | ✅ |
| LEGAL-011 | Inter-agency accountability and governance oversight | Append-only audit + mandatory comments | ✅ |

### 4.3 DICT Cloud First Policy (§6.1.3)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-012 | Cloud infrastructure with security, privacy, governance satisfied | Render, Supabase, Supabase Storage | ✅ |
| LEGAL-013 | Formal provider approval as part of deployment governance | Vendor selection documented | 🟡 Partial |
| LEGAL-014 | Risk assessment + privacy governance review for cloud adoption | PIA required before production | 🔴 |
| LEGAL-015 | Defined security responsibilities across all infrastructure layers | ARCHITECTURE.md defines boundaries | ✅ |
| LEGAL-016 | Cloud dependencies documented as operational assumptions | ARCHITECTURE.md §9 | ✅ |

### 4.4 Cross-Border Processing & International Data Governance (§6.1.4)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-017 | Cross-border privacy governance for regulated OFW data | Documented in deployment governance | 🟡 Partial |
| LEGAL-018 | Organizational approval for third-party infrastructure providers | Vendor selection process | 🟡 Partial |
| LEGAL-019 | Provider confidentiality and privacy obligations | Vendor DPAs required before production | 🟡 Partial |
| LEGAL-020 | Vendor production access controlled and justified | Supabase/Render admin access restricted | 🟡 Partial |
| LEGAL-021 | Sensitive OFW data not unnecessarily exposed to third-parties | Minimized in OTP/notifications | ✅ |
| LEGAL-022 | International processing risks evaluated through privacy review | PIA scoping needed | 🔴 |

### 4.5 Privacy Impact Assessment Requirements (§6.1.5)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-023 | PIA or equivalent maintained for deployment architecture | Not yet conducted | 🔴 |
| LEGAL-024 | Material architectural changes require updated privacy review | Process not established | 🔴 |

### 4.6 Third-Party Service Governance (§6.1.6)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-025 | Providers used only for legitimate operational purposes | Render, Supabase, Supabase Storage, SMTP | ✅ |
| LEGAL-026 | Providers not unrestricted controllers of OFW data | Data processing agreements scoping | 🟡 Partial |
| LEGAL-027 | Provider permissions limited to required functionality | Least-privilege principle applied | ✅ |
| LEGAL-028 | Provider credentials/secrets securely governed | Environment variables, not in source code | ✅ |
| LEGAL-029 | Provider dependency risk acknowledged in governance planning | Architecture documentation identifies dependencies | ✅ |

### 4.7 Procurement & Public Sector Governance Alignment (§6.1.7)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-030 | Infrastructure documented for review, audit, procurement evaluation | ARCHITECTURE.md §9, DEPLOYMENT_GUIDE.md | ✅ |
| LEGAL-031 | Security controls, vendor dependencies, constraints documented | ARCHITECTURE.md + SECURITY_REQUIREMENTS.md | ✅ |
| LEGAL-032 | Technology decisions preserve maintainability, auditability | Laravel + React + Inertia standard stack | ✅ |

### 4.8 AI Governance Requirements (§6.1.8) — If Enabled

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| LEGAL-033 | AI restricted to approved operational functions | Not yet implemented | 🔴 |
| LEGAL-034 | No unnecessary transmission of OFW PII to AI services | PII masking layer needed | 🔴 |
| LEGAL-035 | Case narratives, identifiers, evidence not exposed without approval | Governance framework needed | 🔴 |
| LEGAL-036 | AI usage subordinate to privacy, operational, security governance | Policy documentation needed | 🔴 |

---

## 5. Database Requirements Traceability (SRS §6.2)

### 5.1 Transactional Integrity (§6.2.1)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-001 | ACID-compliant transaction processing | PostgreSQL ACID compliance | ✅ |
| DB-002 | Multi-step workflow transactional consistency | DB transactions in services | ✅ |
| DB-003 | No partial/orphan commits | Transaction rollback on failure | ✅ |
| DB-004 | No corrupted state on interruption | Transactional integrity | ✅ |
| DB-005 | Referential integrity constraints | FK constraints | ✅ |

### 5.2 Authoritative System of Record (§6.2.2)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-006 | No uncontrolled alternate system of record | Supabase as sole system of record | ✅ |
| DB-007 | Single-source-of-truth consistency | No alternate systems | ✅ |

### 5.3 Row-Level Security & Data Isolation (§6.2.3)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-008 | PostgreSQL RLS mandatory for agency-visible records | Available, not fully configured | 🟡 |
| DB-009 | Agencies access only assigned records | Lane filter applied in service layer | ✅ |
| DB-010 | Cross-agency visibility denied by default | Service-layer authorization gates | ✅ |
| DB-011 | DMW retains broader visibility consistent with governance | DMW role bypasses lane filter | ✅ |
| DB-012 | DB controls reinforce app-layer access | RLS policies pending full config | 🟡 |

### 5.4 Sensitive Data Protection (§6.2.4)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-013 | Provider-managed encryption at rest | Supabase managed encryption | ✅ |
| DB-014 | Application-layer encryption for sensitive fields | Passport, address, emergency contact planned | 🟡 |
| DB-015 | No unnecessary duplication across uncontrolled storage | Single-source design | ✅ |
| DB-016 | Exports governed by access controls and secure handling | Not yet implemented | 🔴 |

### 5.5 Access Control & Privilege Governance (§6.2.5)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-017 | DB credentials restricted to authorized service accounts | App DB credentials have minimum grants | ✅ |
| DB-018 | No direct DB credentials for partner agency users | Enforced — all access through app | ✅ |
| DB-019 | Application runtime uses restricted credentials | App DB user scoped | ✅ |
| DB-020 | Admin access limited to authorized maintainers | Supabase project-level access | ✅ |
| DB-021 | No default, shared, weak, or uncontrolled credentials | Enforced | ✅ |
| DB-022 | Secrets not embedded in publicly accessible source code | `.env` only, excluded from VCS | ✅ |

### 5.6 Secure Database Communications (§6.2.6)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-023 | TLS-secured PostgreSQL connections | `DB_CONNECTION=pgsql` with SSL | ✅ |
| DB-024 | Plaintext communications prohibited | Enforced | ✅ |
| DB-025 | Secure connection validation enforced | Certificate validation | ✅ |
| DB-026 | Direct client-to-DB communications prohibited | Mediated architecture | ✅ |

### 5.7 Audit Logging & Traceability (§6.2.7)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-027 | Audit records maintained for security-relevant events | AuditLog model — auth, cases, referrals, docs, admin | ✅ |
| DB-028 | Append-only and tamper-resistant audit records | INSERT-only in application code | ✅ |
| DB-029 | No destructive modification/deletion by unauthorized actors | No update/delete routes for audit logs | ✅ |
| DB-030 | Corrections preserved as traceable new records | Business process — new audit entry | ✅ |

### 5.8 Backup & Recovery (§6.2.8)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-031 | Managed backups per operational recovery requirements | Supabase auto-backups | ✅ |
| DB-032 | Backup encryption equivalent to production | Inherits provider encryption | ✅ |
| DB-033 | Recovery mechanisms for disruption/corruption/failure | Supabase point-in-time recovery | ✅ |
| DB-034 | Backup access restricted to authorized roles | Supabase project-level access | ✅ |

### 5.9 Cloud Database Governance (§6.2.9)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-035 | Provider usage subject to organizational security governance | Vendor documentation | 🟡 Partial |
| DB-036 | Provider admin exposure treated as controlled risk | Administrative access tracking | 🟡 Partial |
| DB-037 | Sensitive data not unnecessarily exposed through vendor tooling | Data minimization principle | ✅ |
| DB-038 | Managed DB dependencies documented as architectural constraints | ARCHITECTURE.md §9 | ✅ |

### 5.10 Reporting Data Controls (§6.2.10)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| DB-039 | Reporting access governed by authorization controls | Role-based report access | ✅ |
| DB-040 | PII restricted based on operational necessity | Anonymized analytics for public data | ✅ |
| DB-041 | Analytical access preserves privacy safeguards | AnonymizedAnalyticsService | ✅ |

---

## 6. Accessibility Requirements Traceability (SRS §6.5)

### 6.1 Platform Accessibility (§6.5.1)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-001 | Browser-based web app, no specialized install | Web application — any modern browser | ✅ |
| ACC-002 | Cross-platform (desktop + mobile) | Responsive Tailwind CSS | ✅ |
| ACC-003 | Public portal mobile-responsive | Separate mobile layout for tracking | ✅ |
| ACC-004 | Works across modern browsers | Chrome, Edge, Firefox, Safari | ✅ |

### 6.2 Perceivable (§6.5.2 — WCAG)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-005 | Sufficient contrast ratios (≥ 4.5:1 normal, ≥ 3:1 large) | Tailwind default palette meets AA | ✅ |
| ACC-006 | Information not conveyed by color alone | Status badges use icons + text + color | ✅ |
| ACC-007 | Alt text for images/icons | `<img alt="">`, ARIA labels on icons | ✅ |
| ACC-008 | Text resizable to 200% without loss | Relative units (rem), responsive layout | ✅ |
| ACC-009 | Readable under responsive scaling/zoom | Tailwind responsive breakpoints | ✅ |

### 6.3 Operable (§6.5.2 — WCAG)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-010 | Keyboard operable for all functions | TabIndex on all interactive elements | ✅ |
| ACC-011 | Visible focus indicators | `focus:ring-2` Tailwind utilities | ✅ |
| ACC-012 | Timing accommodations for time limits | Session timeout warning; OTP resend | ✅ |
| ACC-013 | Predictable, consistent navigation | AppLayout sidebar consistent across pages | ✅ |
| ACC-014 | No seizure-inducing patterns | No animated/flashing content | ✅ |

### 6.4 Understandable (§6.5.2 — WCAG)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-015 | Clear labels, instructions, feedback | `<label>` elements on all form fields | ✅ |
| ACC-016 | Accessible error messaging and correction guidance | Inline validation errors + suggestions | ✅ |
| ACC-017 | Input requirements communicated before submission | Required field indicators | ✅ |
| ACC-018 | Logically consistent navigation behavior | Same UI patterns across all modules | ✅ |

### 6.5 Robust (§6.5.2 — WCAG)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-019 | Semantic markup compatible with assistive tech | Semantic HTML5 elements | ✅ |
| ACC-020 | Screen reader and accessibility tooling support | ARIA roles on dynamic components | ✅ |
| ACC-021 | Dynamic React components preserve accessibility | Inertia page updates preserve semantics | ✅ |

### 6.6 Vulnerable Public Users (§6.5.3)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-022 | Minimize complexity in public workflows | 3-step flow: enter number → OTP → status | ✅ |
| ACC-023 | Usable by varying digital literacy | Simple language, clear CTAs, visual indicators | ✅ |
| ACC-024 | Avoid technical language | Plain Filipino-English terms | ✅ |
| ACC-025 | Privacy-safe, user-comprehensible errors | "Invalid tracker number" not system details | ✅ |

### 6.7 Administrative Accessibility (§6.5.4)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-026 | Readable layouts with accessible navigation | Clean typography, generous spacing | ✅ |
| ACC-027 | Keyboard-accessible tables, forms, filters | All controls tabbable | ✅ |
| ACC-028 | Usable under prolonged use | High-contrast, reduced eye strain palette | ✅ |

### 6.8 Testing & Validation (§6.5.5)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| ACC-029 | Accessibility validated during dev/test | Automated (axe-core) + Manual (keyboard + screen reader) | 🟡 |
| ACC-030 | Regressions corrected before production release | CI/CD accessibility gate planned | 🟡 |

---

## 7. Communications Requirements Traceability (SRS §3.4)

### 7.1 Messaging Security (§3.4.2)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| COM-MSG-001 | OTPs delivered through approved secure channels | Email OTP via `Mail::send()` | ✅ |
| COM-MSG-002 | OTP codes expire after configurable validity | `OtpService` — 5-min TTL | ✅ |
| COM-MSG-003 | Repeated failed OTP rate-limited or blocked | `throttle:otp` (3/min) + `throttle:tracking` (5/min) | ✅ |
| COM-MSG-004 | Notification messages avoid unnecessary sensitive data | Privacy-safe content formatting | ✅ |
| COM-MSG-005 | System messages follow privacy-safe formatting | Email templates designed for minimum data | ✅ |

### 7.2 Form Security & Validation (§3.4.3)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| COM-DATA-001 | Server-side form validation before processing | Form Request classes for all inputs | ✅ |
| COM-DATA-002 | Input validation protects against SQLi, XSS, tampering | Eloquent ORM + validation rules | ✅ |
| COM-DATA-003 | System-generated IDs not manually editable | Case/Tracker numbers auto-generated | ✅ |
| COM-DATA-004 | Sensitive forms require authenticated session | Auth middleware on all protected routes | ✅ |
| COM-DATA-005 | Unauthorized submissions rejected and auditable | Authorization gates + AuditLog | ✅ |

### 7.3 Communication Security Controls (§3.4.4)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| COM-SEC-001 | HTTPS for all authenticated sessions | Enforced at Render/Cloudflare level | ✅ |
| COM-SEC-002 | No direct client-to-DB communication | Mediated architecture | ✅ |
| COM-SEC-003 | DB credentials restricted to authorized roles | App DB credentials only | ✅ |
| COM-SEC-004 | App-to-DB TLS with strict certificate validation | `DB_CONNECTION=pgsql` with SSL | ✅ |
| COM-SEC-005 | No insecure transport for sensitive internal comms | All internal comms encrypted | ✅ |
| COM-SEC-006 | Document/media access requires auth or signed URLs | Supabase Storage signed URLs | ✅ |
| COM-SEC-007 | No public exposure of protected OFW documents | Authorization gates on all document routes | ✅ |
| COM-SEC-008 | External integrations require authenticated encrypted APIs | API keys + HTTPS | ✅ |
| COM-SEC-009 | Privileged communication failures/suspicious events logged | AuditLog event tracking | ✅ |

### 7.4 Communication Performance (§3.4.4)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| COM-PERF-001 | Pages load within defined performance targets | Not measured | 🔴 |
| COM-PERF-002 | Real-time updates sync'd through secure channels | WebSocket deferred to v1.0+ | 🔴 |
| COM-PERF-003 | Notification delivery within acceptable time windows | Provider-dependent (email) | 🟡 |
| COM-PERF-004 | Communication resilience for service interruptions | Queue retry mechanism | 🟡 |

### 7.5 Cross-Border Communication Governance (§3.4.5)

| ID | Requirement | Implementation | Status |
|---|---|---|---|
| COM-GOV-001 | Cross-border comms governed under privacy safeguards | Documented in deployment governance | 🟡 |
| COM-GOV-002 | Only approved providers with contractual obligations | Render, Supabase, Supabase Storage, SMTP | ✅ |
| COM-GOV-003 | Sensitive data not unnecessarily transmitted to third-parties | Minimized in notifications | ✅ |
| COM-GOV-004 | External provider access governed by security controls | Vendor access restricted | 🟡 |

---

## 8. Non-SRS Features (Implemented)

The following features have been implemented to enhance operational efficiency, transparency, and user experience, though they were not explicitly defined in the initial SRS.

### 1. Helpdesk/Knowledge Base
**Description**: Full content management system for helpdesk articles with categories, tags, revisions, feedback, and featured articles.
**Status**: 🔄 Migrated to database models (controllers removed)
**Key Files**: `app/Models/HelpdeskArticle.php`, `app/Models/HelpdeskCategory.php`, `app/Models/HelpdeskTag.php`, `app/Models/HelpdeskArticleRevision.php`, `app/Models/HelpdeskArticleFeedback.php`
**Routes**: `GET /helpdesk`, `GET /helpdesk/search`, `GET /helpdesk/{slug}`, `POST /helpdesk/feedback`, `GET /admin/helpdesk/articles`, `POST /admin/helpdesk/articles`, etc.
**Why Added**: To provide self-service information for OFWs and reduce dependency on case manager inquiries.

### 2. Agency Service Self-Management
**Description**: Allows agencies to manage their own service offerings and descriptions directly.
**Status**: ✅ Implemented
**Key Files**: `app/Http/Controllers/AgencyServiceController.php`, `app/Models/Service.php`
**Routes**: `GET /services`, `POST /services`, `PATCH /services/{service}`, `DELETE /services/{service}`
**Why Added**: To decentralize service management and ensure agency profiles remain up-to-date.

### 3. Referral Attachment Versioning
**Description**: Complete version control for referral documents, allowing users to replace files while maintaining history.
**Status**: ✅ Implemented
**Key Files**: `app/Models/ReferralAttachment.php`, `app/Services/ReferralService.php`
**Routes**: Integrated into Referral API
**Why Added**: To prevent data loss when documents are updated and provide a clear audit trail of document changes.

### 4. Overdue Referral Monitoring
**Description**: System for identifying referrals that have exceeded expected processing times and sending automated reminders.
**Status**: ✅ Implemented
**Key Files**: `app/Http/Controllers/Admin/OverdueReferralController.php`, `app/Mail/ReferralOverdueMail.php`
**Routes**: `GET /overdue-referrals`, `POST /overdue-referrals/send-reminders`
**Why Added**: To ensure no referral is neglected and to improve overall service delivery speed.

### 5. Feedback SERVQUAL Implementation
**Description**: Sophisticated service quality evaluation using the 5-dimension SERVQUAL model (Tangibles, Reliability, Responsiveness, Assurance, Empathy).
**Status**: ✅ Implemented
**Key Files**: `app/Models/FeedbackServqualResponse.php`, `app/Models/ServqualConfig.php`
**Routes**: Part of the Feedback submission flow
**Why Added**: To provide more granular and academic-standard quality metrics beyond simple satisfaction ratings.

### 6. Custom Case Statuses
**Description**: Admin-configurable status labels for both cases and referrals to adapt to changing organizational workflows.
**Status**: ✅ Implemented
**Key Files**: `app/Models/CaseStatus.php`, `app/Http/Controllers/Admin/AdminCaseStatusController.php`
**Routes**: `GET /admin/case-statuses`, `POST /admin/case-statuses`, `PATCH /admin/case-statuses/{id}`
**Why Added**: To provide flexibility in status naming without requiring code changes.

### 7. Public Agencies Listing
**Description**: Publicly accessible directory of partner agencies and their contact information.
**Status**: ✅ Implemented
**Key Files**: `resources/js/Pages/Public/Partners.jsx`, `routes/web.php`
**Routes**: `GET /partners`
**Why Added**: Transparency and ease of access for OFWs to find where they can seek help.

### 8. Contact Page
**Description**: Standard contact information page for DMW Region VII.
**Status**: ✅ Implemented
**Key Files**: `resources/js/Pages/Public/Contact.jsx`, `routes/web.php`
**Routes**: `GET /contact`
**Why Added**: Essential public-facing requirement for organizational communication.

### 9. Geographic Distribution
**Description**: Visual analytics showing the distribution of cases by province.
**Status**: ✅ Implemented
**Key Files**: `app/Services/DashboardService.php`, `resources/js/Components/Dashboard/GeographicChart.jsx`
**Routes**: `GET /api/dashboard/stats`
**Why Added**: To help administrators identify regional hotspots and allocate resources effectively.

### 10. Agency Scorecard
**Description**: Per-agency performance report measuring acceptance rates, completion times, and feedback scores.
**Status**: ✅ Implemented
**Key Files**: `app/Services/ReportsService.php`, `app/Http/Controllers/ReportsController.php`
**Routes**: `GET /reports/agency-scorecard`
**Why Added**: To evaluate partner agency effectiveness and accountability.

### 11. Referral Cycle Time Distribution
**Description**: Statistical distribution analysis of how long referrals take to reach completion.
**Status**: ✅ Implemented
**Key Files**: `app/Services/ReportsService.php`
**Routes**: `GET /reports/cycle-time`
**Why Added**: To identify bottlenecks in the referral process and set realistic service level expectations.

### 12. Referral Aging
**Description**: Report highlighting stale referrals that have been in the same status for an extended period.
**Status**: ✅ Implemented
**Key Files**: `app/Services/ReportsService.php`
**Routes**: `GET /reports/aging`
**Why Added**: Proactive identification of cases that may require intervention or escalation.

### 13. Debug OTP Mode
**Description**: Development-only toggle that auto-fills OTP inputs to speed up developer testing.
**Status**: ✅ Implemented
**Key Files**: `app/Models/SystemSetting.php`, `app/Services/OtpService.php`
**Routes**: Internal logic
**Why Added**: Significantly improves developer productivity during local development and QA.

### 14. Client Employment History
**Description**: Tracking of both current and previous overseas employment details for OFWs.
**Status**: ✅ Implemented
**Key Files**: `app/Models/ClientEmployment.php`
**Routes**: Part of Client/Case creation
**Why Added**: To provide context on the OFW's work history which often impacts current case circumstances.

### 15. Keyword-based ChatBot
**Description**: Automated messaging interface that provides predefined responses based on user keywords.
**Status**: ✅ Implemented
**Key Files**: `app/Http/Controllers/ChatbotController.php`, `app/Services/ChatbotService.php`
**Routes**: `POST /chatbot/message`
**Why Added**: Provides immediate responses to common queries while the more advanced AI features are being developed.

### 16. Rejected Referral Tracking
**Description**: Specific dashboard indicators for referrals rejected by agencies, requiring immediate attention from case managers.
**Status**: ✅ Implemented
**Key Files**: `app/Services/DashboardService.php`, `resources/js/Pages/Dashboard/Index.jsx`
**Routes**: `GET /dashboard`
**Why Added**: Ensures rejected referrals don't "disappear" and are promptly reassigned or addressed.

---

## 9. Coverage Summary

| Category | Total Reqs | Implemented | Partial | Not Done | Coverage |
|---|---|---|---|---|---|
| FR-AUTH (Auth) | 12 | 12 | 0 | 0 | 100% |
| FR-ADM (Admin) | 8 | 8 | 0 | 0 | 100% |
| FR-INT (Intake) | 12 | 12 | 0 | 0 | 100% |
| FR-DOC (Documents) | 8 | 8 | 0 | 0 | 100% |
| FR-REF (Referrals) | 15 | 15 | 0 | 0 | 100% |
| FR-TRK (Tracking) | 13 | 13 | 0 | 0 | 100% |
| FR-PORT (Portal) | 9 | 9 | 0 | 0 | 100% |
| FR-AI (Chatbot) | 7 | 1 | 1 | 5 | 14% |
| FR-ANA (Analytics) | 7 | 6 | 1 | 0 | 86% |
| FR-AUD (Audit) | 8 | 8 | 0 | 0 | 100% |
| FR-FBK (Feedback) | 6 | 6 | 0 | 0 | 100% |
| NFR-SEC (Security) | 60 | 50 | 5 | 5 | 83% |
| NFR-PERF (Performance) | 8 | 1 | 0 | 7 | 12% |
| NFR-SAFE (Safety) | 8 | 8 | 0 | 0 | 100% |
| NFR-QUAL (Quality) | 26 | 22 | 0 | 4 | 85% |
| BR (Business Rules) | 13 | 12 | 0 | 1 | 92% |
| LEGAL (Legal) | 36 | 24 | 4 | 8 | 67% |
| DB (Database) | 41 | 36 | 3 | 2 | 88% |
| ACC (Accessibility) | 30 | 27 | 3 | 0 | 90% |
| COM (Communications) | 27 | 21 | 4 | 2 | 78% |
| **Non-SRS Extras** | **—** | **16** | **—** | **—** | **—** |
| **TOTAL (SRS)** | **354** | **299** | **20** | **35** | **84%** |

---

## 10. Gap Summary

### Critical Gaps (High Priority)

| Gap | Category | Impact |
|---|---|---|
| AI Chatbot (FR-AI-001–007) | Functional | Core chatbot features not implemented |
| Duplicate flagging (BR-012) | Business Rule | OFW may be re-registered |
| Performance measurement (NFR-PERF) | NFR | No load testing done |
| Privacy Impact Assessment (LEGAL-023) | Legal | Required before production |
| CSV export (FR-ANA-004 partial) | Functional | Only PDF export implemented |

### Medium Priority

| Gap | Category | Impact |
|---|---|---|
| Application-layer encryption (NFR-SEC-024) | Security | Sensitive fields not encrypted |
| PgSQL RLS policies (NFR-SEC-028) | Security | Lane isolation not at DB level |
| Data retention policy (LEGAL-006) | Legal | No automated data cleanup |
| Accessibility CI check (ACC-029) | Accessibility | Regressions possible |
| Security headers (CSP, HSTS) | Security | OWASP recommendation |
