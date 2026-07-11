# Backend Modules

## Architecture Pattern

```
Controller (thin) → Service (business logic) → Model (Eloquent) → PostgreSQL
```

Controllers handle HTTP concerns only. All business logic lives in `app/Services/*`. Validation lives in `app/Http/Requests/*`.

## Authentication

### Custom OTP MFA Login

The system uses a custom OTP-based MFA flow, **not** Laravel Breeze's default login.

**Flow:**
1. User submits email + password
2. Turnstile CAPTCHA verified
3. System sends 6-digit OTP via email (5-minute TTL)
4. User enters OTP → session created
5. If TOTP enrolled → additional TOTP verification required
6. Recovery codes available as fallback

**Key Files:**
- `app/Http/Controllers/LoginOtpController.php` — Login flow
- `app/Http/Controllers/MfaController.php` — MFA setup/management
- `app/Services/OtpService.php` — OTP generation and verification
- `app/Services/MfaService.php` — TOTP secret management

**Rate Limits:**
- Login: 6 attempts/minute
- OTP verification: 3 attempts/minute
- TOTP challenge: throttled
- Recovery code: throttled

### Email Change Flow

Users can change their email through an OTP-verified flow:
1. Request email change → OTP sent to current email
2. Verify OTP → new email queued
3. Verification link sent to new email
4. Click link → email updated

**Key Files:**
- `app/Http/Controllers/EmailChangeController.php`
- `app/Http/Requests/EmailChangeInitRequest.php`
- `app/Http/Requests/EmailChangeSendOtpRequest.php`
- `app/Http/Requests/EmailChangeVerifyOtpRequest.php`

## Case Management

### Case Lifecycle

```
Create (Draft) → Publish → Refer → Track → Close
                                  ↓
                            Add Milestones
                                  ↓
                            Agency Actions
                                  ↓
                            All Referrals Terminal
                                  ↓
                            Case Closed (DMW only)
```

### Case States

| State | Description | Who Can Set |
|---|---|---|
| Draft | Incomplete, being edited | Case Manager |
| Published | Active, available for referral | Case Manager |
| Closed | All referrals terminal | Case Manager only |

### Key Business Rules

- **BR-001:** Only DMW creates official case records
- **BR-002:** Single Unified Master Case File per OFW
- **BR-003:** Parallel referrals to multiple agencies allowed
- **BR-008:** Only DMW closes cases (validates all referrals terminal)
- **BR-012:** Duplicate flagging on intake (name + birthdate matching)

### Key Files

- `app/Http/Controllers/CaseController.php` — CRUD operations
- `app/Http/Controllers/CaseDocumentController.php` — Document management
- `app/Http/Controllers/CaseIssueController.php` — Issue tracking
- `app/Services/CaseService.php` — Core business logic
- `app/Services/TrackingService.php` — Public tracking portal logic
- `app/Http/Requests/StoreCaseRequest.php` — Create validation
- `app/Http/Requests/UpdateCaseRequest.php` — Update validation

### Case Documents

Case documents are the authoritative files for a client, stored on Supabase Storage via signed expiring URLs. Unlike referral attachments (scoped to specific inter-agency transactions), case documents represent the master record.

**Access Control:**
- Case Manager: Full CRUD
- Admin: Read-only
- Agency: Read-only (only if they have an active referral for that case)

## Referral System

### Referral Lifecycle

```
Create (PENDING) → Accept → PROCESSING → Complete (COMPLETED)
                 → Reject → (REJECTED)
                 → Compliance check → FOR COMPLIANCE
                 → Unable to Proceed
```

### Referral States

| State | Description | Who Sets |
|---|---|---|
| PENDING | Awaiting agency response | System (on create) |
| PROCESSING | Agency accepted, working | Agency |
| COMPLETED | Agency finished | Agency |
| REJECTED | Agency declined | Agency |
| FOR COMPLIANCE | Needs additional info | Agency |

### Key Business Rules

- **BR-003:** One referral per agency per case (parallel referrals)
- **BR-004:** Referrals to established partner network only
- **BR-005:** Lane-based data isolation (RLS)
- **BR-006:** Mandatory comment for referral decisions

### Milestones

Milestones are **append-only** (BR-007). Once submitted, they cannot be edited or deleted. This ensures an immutable progress trail.

### Key Files

- `app/Http/Controllers/ReferralController.php` — CRUD + status management
- `app/Services/ReferralService.php` — Business logic
- `app/Http/Requests/StoreReferralRequest.php` — Create validation
- `app/Http/Requests/UpdateReferralStatusRequest.php` — Status change validation
- `app/Http/Requests/StoreMilestoneRequest.php` — Milestone validation

### Referral Attachments

- Versioned file uploads with `version_group_id`
- Replace creates new version, preserving history
- Download via signed URLs

## Feedback & SERVQUAL

The system implements SERVQUAL (Service Quality) evaluation for agency performance measurement.

### Feedback Flow

```
Case Closed → Feedback Invitation Sent → OFW Submits Feedback
                                              ↓
                                    SERVQUAL Response (5 dimensions)
                                              ↓
                                    Agency Scorecard Generated
```

### SERVQUAL Dimensions

1. **Tangibles** — Physical facilities, equipment, staff appearance
2. **Reliability** — Ability to perform promised service dependably
3. **Responsiveness** — Willingness to help and provide prompt service
4. **Assurance** — Knowledge and courtesy of employees
5. **Empathy** — Caring, individualized attention

### Key Files

- `app/Http/Controllers/FeedbackController.php` — Dashboard and management
- `app/Http/Controllers/PublicFeedbackController.php` — Public submission
- `app/Services/FeedbackService.php` — Business logic
- `app/Services/FeedbackInvitationService.php` — Invitation management
- `app/Services/Feedback/ServqualConfigService.php` — SERVQUAL configuration

## Helpdesk & Knowledge Base

### Article Lifecycle

```
Draft → Publish → (Archive)
```

### Visibility Levels

| Level | Who Sees |
|---|---|
| `public` | Everyone (including anonymous) |
| `authenticated` | Logged-in users only |
| `role_restricted` | Specific roles only |

### Key Files

- `app/Http/Controllers/HelpdeskController.php` — Article management
- `resources/js/data/helpdesk/` — Static article content
- `resources/js/Pages/Helpdesk/` — Frontend pages

## AI Chatbot

The chatbot provides interactive help for OFW inquiries. It matches user messages to helpdesk articles or generates AI-powered responses.

### Architecture

```
User Message → Intent Detection → Route to Handler
                                    ├─ Helpdesk Article Match
                                    ├─ Case Status Query (requires OTP)
                                    └─ General AI Response
```

### Key Files

- `app/Http/Controllers/ChatbotController.php` — Message endpoint
- `app/Services/Chatbot/ChatbotIntentService.php` — Intent detection
- `app/Services/Chatbot/ChatbotRetrievalService.php` — Article matching
- `app/Services/Chatbot/ChatbotGuideService.php` — Guided flows
- `app/Services/Chatbot/ChatbotHelpdeskService.php` — Helpdesk integration

## Admin Module

### Admin Routes (IP-restricted + Admin role)

| Area | Controller | Purpose |
|---|---|---|
| Users | `AdminUserController` | User CRUD, verification, email changes |
| Agencies | `AdminAgencyController` | Agency CRUD, service assignment |
| Services | `AdminServiceController` | Service category management |
| Case Categories | `AdminCaseCategoryController` | Category management |
| Case Statuses | `AdminCaseStatusController` | Status configuration |
| Case Issues | `AdminCaseIssueController` | Issue type management |
| System Settings | `SystemSettingsController` | Key-value configuration |
| Data Export | `DataExportController` | Bulk data export |
| Logs | `LogViewerController` | Application log viewing |
| Security | `SecuritySettingsController` | IP whitelist, security config |
| Maintenance | `MaintenanceController` | Maintenance mode toggle |
| Active Sessions | `ActiveSessionsController` | Session management |
| Email Logs | `EmailLogController` | Email delivery logs |

## Reports & Analytics

### Dashboard

Role-based dashboards with KPIs, charts, and recent activity:
- **Case Manager:** Cases they own, pending referrals, overdue items
- **Agency:** Referrals to their agency, milestones, pending actions
- **Admin:** System-wide metrics, user activity, agency performance

### Reports

- **Case Trends** — Case creation over time
- **Referral Status Distribution** — Pie chart of referral states
- **Geographic Distribution** — Province/city mapping
- **Agency Scorecard** — SERVQUAL-based performance
- **Employment Analytics** — OFW employment patterns
- **Export:** PDF and Excel formats

### Key Files

- `app/Http/Controllers/ReportsController.php` — Report generation
- `app/Http/Controllers/DashboardController.php` — Dashboard data
- `app/Services/DashboardService.php` — Dashboard business logic
- `app/Services/ReportsService.php` — Report queries
- `app/Services/Reports/ReportsExportService.php` — Export generation

## Audit System

### Append-Only Audit Log

All critical actions are logged immutably:

| Action | What's Logged |
|---|---|
| CREATE | New entity created |
| UPDATE | Entity modified (old_value → new_value) |
| DELETE | Entity soft-deleted |
| VIEW | Entity accessed |
| LOGIN | Authentication attempt |
| LOGOUT | Session termination |

### Audit Log Schema

- `action` — Enum: CREATE, UPDATE, DELETE, VIEW, LOGIN, LOGOUT
- `module` — Affected module (e.g., "cases", "referrals")
- `entity_id` — UUID of affected record
- `old_value` — JSONB previous state
- `new_value` — JSONB new state
- `user_id` — Who performed the action
- `ip_address` — Request IP
- `user_agent` — Browser info
- `request_id` — Correlation ID for request tracing

### Key Files

- `app/Models/AuditLog.php` — Model
- `app/Observers/AuditObserver.php` — Automatic logging
- `app/Services/AuditLogFormatter.php` — Display formatting
- `app/Http/Controllers/AuditLogController.php` — Viewing
