# API Contracts

> **Version:** 2.1.0 | **Updated:** 2026-09-15 | **Source:** `routes/web.php` (455 lines), `routes/auth.php`, `routes/api.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php` (rate limiters)
>
> **What changed in 2.1.0:** recounted every group against the current route files; corrected the auth tables (the previous revision named a `LoginOtpController` flow that no longer exists — login is `AuthenticatedSessionController`, MFA challenge is `MfaChallengeController`, invites are `RegisterViaInviteController`); added the survey-token, intake, expanded track, session-auth ClientSelect, OFW portal, client-request, survey-form, and expanded admin sections that were missing; split document read/write and audit-log visibility by role; added the `/api/readyz` vs `/up` distinction, the Resend webhook, the exception-rendering contract, and the full named-throttle table. Previous version: 2.0.0 (2026-07-11, ~164 routes).

## Overview

All routes are defined in three files:

- `routes/web.php` — Application routes (public pages, session-authenticated app, session-authenticated `api/*` helpers)
- `routes/auth.php` — Authentication routes (required at the end of `web.php`)
- `routes/api.php` — Public stateless API routes (readiness probe, address lookup, CSP reports, mail webhooks)

**Important:** Session-authenticated API-style endpoints (`/api/clients*`, `/api/referrals/{referral}/messages`, `/api/*/audit-logs`) live in `web.php`, not `api.php`. Do not assume every API-looking route is in `routes/api.php`.

**Exact counts:** named-route totals drift as features land. For the authoritative count run:

```bash
php artisan route:list --except-vendor
```

Group totals below are approximate (`~165–175` named routes across all files at time of writing).

---

## Public Routes (No Authentication)

### Landing & Info Pages

| Method | URI | Controller/Handler | Name | Notes |
|--------|-----|-------------------|------|-------|
| GET | `/` | `HomeController@index` | — | Landing page |
| GET | `/partners` | Closure → `PublicAgencies/Index` | `partners` | Agency directory (active agencies + services) |
| GET | `/partners/{agency}` | Closure → `PublicAgencies/Show` | `partners.show` | Agency detail (slug or UUID) |
| GET | `/contact` | Closure → `Contact/Index` | `contact` | Contact page |
| POST | `/contact` | `ContactController@store` | `contact.store` | `turnstile`, `throttle:contact-form` |
| GET | `/privacy` | Closure → `Legal/PrivacyPolicy` | `privacy` | |
| GET | `/terms` | Closure → `Legal/TermsOfService` | `terms` | |

### Public Survey (Token-Based)

Token-based feedback submission — no auth required. Tokens are unguessable; possession of the token is the authorization.

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/survey/{token}` | `PublicSurveyController@show` | `survey.public.show` | `throttle:survey-view` |
| POST | `/survey/{token}` | `PublicSurveyController@submit` | `survey.public.submit` | `throttle:survey-submit` |

### Public Intake (OFW Self-Filing)

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/intake` | `IntakeController@index` | `intake.index` | — |
| POST | `/intake/verify-email` | `IntakeController@verifyEmail` | `intake.verify-email` | `turnstile`, `throttle:intake-otp` |
| POST | `/intake/check-duplicate` | `IntakeController@checkDuplicate` | `intake.check-duplicate` | `throttle:intake-duplicate` |
| POST | `/intake/submit` | `IntakeController@submit` | `intake.submit` | `throttle:intake-submit` |
| POST | `/intake/register` | `IntakeRegistrationController@store` | `intake.register` | `throttle:intake-submit` |

Each intake step has its own named limiter on purpose: sharing one counter with each other and with the address lookups the wizard cascades through caused legitimate submissions to hit 429 before ever being attempted.

### Case Tracking Portal

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/track` | `TrackController@index` | `track.index` | — |
| POST | `/track/send-otp` | `TrackController@sendOtp` | `track.send-otp` | `turnstile`, `throttle:tracking` |
| GET | `/track/verify-otp` | redirect → `track.index` | `track.verify-otp.get` | — (GET guard; the real verification is POST-only) |
| POST | `/track/verify-otp` | `TrackController@verifyOtp` | `track.verify-otp` | `throttle:tracking` |
| GET | `/track/case` | `TrackController@show` | `track.show` | — (OTP-gated session) |
| GET | `/track/case/{tracker}/referrals/{referral}/milestones` | `TrackController@milestones` | `track.milestones` | — |
| POST | `/track/register` | `TrackRegistrationController@store` | `track.register` | `throttle:intake-submit` |
| GET | `/track/request` | `ReferralClientRequestController@show` | `track.request.index` | token-based client view |
| POST | `/track/request/exchange` | `ReferralClientRequestController@exchange` | `track.request.exchange` | `throttle:track-request-exchange` |
| POST | `/track/request/messages` | `ReferralClientRequestController@clientMessage` | `track.request.messages.store` | `throttle:track-request-message` |
| POST | `/track/request/replacement` | `ReferralClientRequestController@replacement` | `track.request.replacement` | `throttle:track-request-replacement` |
| GET | `/track/request/attachments/{attachment}/download` | `ReferralClientRequestController@downloadAttachment` | `track.request.attachments.download` | — |

### Helpdesk (Knowledge Base)

| Method | URI | Handler | Name | Notes |
|--------|-----|---------|------|-------|
| GET | `/help` | Closure → `Helpdesk/Index` | `helpdesk.index` | `?category=` filter |
| GET | `/help/search` | Closure → `Helpdesk/Search` | `helpdesk.search` | `?q=` query |
| GET | `/help/{slug}` | Closure → `Helpdesk/Show` | `helpdesk.show` | Article by slug |

### Chatbot

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| POST | `/chatbot/message` | `ChatbotController@message` | `chatbot.message` | `turnstile.session`, `throttle:chatbot` |

`ChatbotController` is a one-liner: it delegates to `ChatbotConversationService::reply()` with the validated payload and `ChatbotAudience::forUser($request->user())` (guest-safe; audience is server-resolved, never trusted from the client).

The endpoint accepts `message` (up to 1,000 characters), optional `history` (up to 20 `{role: user|bot, text}` entries, each up to 1,000 characters), and optional `lastContext` (`source_type`, `source_label`, `article_title`). Context is a hint; the server re-resolves audience permissions and re-reads evidence every turn.

Responses contain `reply`, `status`, `sources`, `actions`, and explicitly nullable `lastContext`. Status is `answered`, `greeting`, `clarification`, `unsupported`, or `unavailable`. A source has `source_type`, `slug`, `heading`, `url`, `sections`, and, for helpdesk articles, canonical `article_title`. Article links identify only authorized content actually read and selected as support. Directory references have a null URL and a directory label. No vector-confidence score is returned. Clients must clear stored context on null and clear chat on identity/role changes. See `docs/CHATBOT_PIPELINE_v1.0.0.md` (which supersedes `docs/CHATBOT_AGENT.md`) for the full pipeline, configuration, and verification.

### Public API (`routes/api.php`)

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/api/readyz` | `ReadinessController` (single-action) | `monitoring.readyz` | `throttle:readiness` |
| GET | `/api/address/regions` | `PhilippineAddressController@regions` | — | `throttle:address-lookup` |
| GET | `/api/address/provinces` | `PhilippineAddressController@provinces` | — | `throttle:address-lookup` |
| GET | `/api/address/cities` | `PhilippineAddressController@cities` | — | `throttle:address-lookup` |
| GET | `/api/address/barangays` | `PhilippineAddressController@barangays` | — | `throttle:address-lookup` |
| GET | `/api/address/resolve` | `PhilippineAddressController@resolve` | — | `throttle:address-lookup` |
| POST | `/api/csp/report` | `CspViolationController@report` | — | `throttle:csp-report` |
| POST | `/api/webhooks/resend` | `ResendWebhookController` (single-action) | `webhooks.resend` | `throttle:resend-webhook` |

Notes:

- **`GET /up` vs `GET /api/readyz`.** `/up` is the shallow framework health endpoint (configured in `bootstrap/app.php`); the hosting platform's liveness probe must use it. `/api/readyz` is the deep readiness probe (database, scheduler heartbeat, queue backlog, image rendering) for external monitoring. It requires the `X-Monitoring-Token` header and returns 404 when no token is configured. They are deliberately different: pointing the liveness probe at the deep check turns a database blip into a restart loop.
- **PSGC address lookup.** The five `/api/address/*` endpoints serve public Philippine Standard Geographic Code data (regions → provinces → cities → barangays → resolve). No auth required. The limiter must stay the named `address-lookup` limiter — an inline limit would share one counter with `/intake/submit` and reject filer submissions.
- **Resend webhook.** Authenticated by Svix signature inside the controller (see the mail webhook verifier service), not by session or token. It lives in `api.php` so it bypasses CSRF, sessions, and the MFA middleware the web group appends.

### Health Check

| Method | URI | Notes |
|--------|-----|-------|
| GET | `/up` | Framework built-in shallow health endpoint (configured in `bootstrap/app.php`). This is what the platform probes. |

---

## Authentication Routes (`routes/auth.php`, ~19 routes)

### Guest-Only (Unauthenticated)

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/login` | `AuthenticatedSessionController@create` | `login` | `guest` |
| POST | `/login` | `AuthenticatedSessionController@store` | `login.store` | `guest`, `turnstile`, `throttle:login` |
| GET | `/login/mfa` | `MfaChallengeController@show` | `mfa.challenge.show` | `mfa.pending` |
| POST | `/login/mfa/totp` | `MfaChallengeController@totp` | `mfa.challenge.totp` | `mfa.pending`, `throttle:totp-challenge` |
| POST | `/login/mfa/recovery` | `MfaChallengeController@recovery` | `mfa.challenge.recovery` | `mfa.pending`, `throttle:recovery-code` |
| POST | `/login/mfa/cancel` | `MfaChallengeController@cancel` | `mfa.challenge.cancel` | `mfa.pending` |
| GET | `/invite/{token}` | `RegisterViaInviteController@show` | `register-via-invite` | `guest` |
| POST | `/invite/{token}` | `RegisterViaInviteController@store` | `register-via-invite.store` | `guest`, `throttle:invite-register` |
| GET | `/forgot-password` | `PasswordResetLinkController@create` | `password.request` | `guest` |
| POST | `/forgot-password` | `PasswordResetLinkController@store` | `password.email` | `guest`, `turnstile`, `throttle:password-reset-request` |
| GET | `/reset-password/{token}` | `NewPasswordController@create` | `password.reset` | `guest` |
| POST | `/reset-password` | `NewPasswordController@store` | `password.store` | `guest` |
| GET | `/forgot-email` | Closure → `Auth/ForgotEmail` | `forgot-email` | `guest` |

Notes:

- The login POST is named `login.store`, not `login`: two routes sharing the name `login` breaks `route:cache` ("Unable to prepare route [login] for serialization"). `route('login')` still resolves to the same `/login` path for form actions.
- There is no self-service `/register` route. Registration happens only through admin-issued invites (`/invite/{token}`).

### Authenticated

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/confirm-password` | `ConfirmablePasswordController@show` | `password.confirm` | `auth` |
| POST | `/confirm-password` | `ConfirmablePasswordController@store` | — | `auth` |
| GET | `/verify-email` | `EmailVerificationPromptController` | `verification.notice` | `auth` |
| GET | `/verify-email/{id}/{hash}` | `VerifyEmailController` | `verification.verify` | `auth`, `signed`, `throttle:email-verification` |
| POST | `/email/verification-notification` | `EmailVerificationNotificationController@store` | `verification.send` | `auth`, `throttle:email-verification` |
| PUT | `/password` | `PasswordController@update` | `password.update` | `auth` |
| GET | `/profile/email-change` | `EmailChangeController@init` | `profile.email-change.init` | `auth` |
| POST | `/profile/email-change/send-otp` | `EmailChangeController@sendOtp` | `profile.email-change.send-otp` | `auth`, `throttle:otp` |
| POST | `/profile/email-change/verify-otp` | `EmailChangeController@verifyOtp` | `profile.email-change.verify-otp` | `auth`, `throttle:otp` |
| POST | `/logout` | Closure (manual `AuditLog::create` + logout) | `logout` | `auth` |

Logout writes its own `AuditLog::create` row (LOGOUT/`auth`) before invalidating the session and clearing MFA-pending state — it does not rely on a framework event listener for the audit row.

---

## Authenticated Routes (All Roles)

All routes below require `auth` + `verified`, except the five notification routes, which explicitly opt out of `verified` via `withoutMiddleware('verified')` so that users with unverified emails can still read their notifications.

### Dashboard & Profile

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/dashboard` | `DashboardController@index` | `dashboard` |
| GET | `/profile` | `ProfileController@edit` | `profile.edit` |
| PATCH | `/profile` | `ProfileController@update` | `profile.update` |
| DELETE | `/profile` | `ProfileController@destroy` | `profile.destroy` |

### MFA Management

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/profile/mfa/status` | `MfaController@status` | `profile.mfa.status` |
| POST | `/profile/mfa/generate` | `MfaController@generateSecret` | `profile.mfa.generate` |
| POST | `/profile/mfa/verify` | `MfaController@verifyAndEnable` | `profile.mfa.verify` |
| POST | `/profile/mfa/disable` | `MfaController@disable` | `profile.mfa.disable` |
| POST | `/profile/mfa/recovery-codes/regenerate` | `MfaController@regenerateRecoveryCodes` | `profile.mfa.recovery-codes.regenerate` |

### Referrals (All Roles)

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/referrals` | `ReferralController@index` | `referrals.index` |
| GET | `/referrals/create` | `ReferralController@create` | `referrals.create` |
| POST | `/referrals` | `ReferralController@store` | `referrals.store` |
| GET | `/referrals/export-excel` | `ReferralController@exportExcel` | `referrals.export-excel` |
| GET | `/referrals/export-count` | `ReferralController@exportCount` | `referrals.export-count` |
| GET | `/referrals/{referral}` | `ReferralController@show` | `referrals.show` |
| PATCH | `/referrals/{referral}/status` | `ReferralController@updateStatus` | `referrals.update-status` |
| POST | `/referrals/{referral}/milestones` | `ReferralController@addMilestone` | `referrals.milestones.store` |
| POST | `/referrals/{referral}/services` | `ReferralController@addService` | `referrals.services.add` |
| DELETE | `/referrals/{referral}/services/{service}` | `ReferralController@removeService` | `referrals.services.remove` |
| POST | `/referrals/{referral}/services/{service}/requirements` | `ReferralController@addRequirement` | `referrals.services.requirements.add` |
| PATCH | `/referrals/{referral}/services/{service}/requirements/{requirement}` | `ReferralController@updateRequirement` | `referrals.services.requirements.update` |
| DELETE | `/referrals/{referral}/services/{service}/requirements/{requirement}` | `ReferralController@deleteRequirement` | `referrals.services.requirements.delete` |
| POST | `/referrals/{referral}/comments` | `ReferralController@addComment` | `referrals.comments.store` |
| POST | `/referrals/{referral}/comments/{comment}/reply` | `ReferralController@replyToComment` | `referrals.comments.reply` |
| GET | `/api/referrals/{referral}/messages` | `ReferralMessageController@index` | `api.referrals.messages.index` (`throttle:api-global`) |
| POST | `/referrals/{referral}/messages` | `ReferralMessageController@store` | `referrals.messages.store` |
| POST | `/referrals/{referral}/messages/read` | `ReferralMessageController@markRead` | `referrals.messages.read` |
| POST | `/referrals/{referral}/attachments` | `ReferralController@addAttachment` | `referrals.attachments.store` |
| POST | `/referrals/{referral}/attachments/{attachment}/replace` | `ReferralController@replaceAttachment` | `referrals.attachments.replace` |
| POST | `/referrals/{referral}/attachments/{attachment}/remove` | `ReferralController@deleteAttachment` | `referrals.attachments.delete` |
| GET | `/referrals/{referral}/attachments/{attachment}/download` | `ReferralController@downloadAttachment` | `referrals.attachments.download` |
| GET | `/referrals/{referral}/attachments/{versionGroupId}/versions` | `ReferralController@getAttachmentVersions` | `referrals.attachments.versions` |
| GET | `/api/referrals/{referral}/audit-logs` | `AuditLogController@referralAuditLogs` | `api.referrals.audit-logs` (`throttle:api-global`) |

### Reports

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/reports` | `ReportsController@index` | `reports.index` | `throttle:reports-view` |
| GET | `/reports/export-pdf` | `ReportsController@exportPdf` | `reports.export-pdf` | — |
| GET | `/reports/export-excel` | `ReportsController@exportExcel` | `reports.export-excel` | — |

Pre-flight row-cap guards, small-cell suppression, role scoping, and the attempt/outcome audit trail for the two exports are documented in `docs/REPORTS_EXPORT_v1.1.0.md` (which supersedes the pre-rebuild `docs/REPORTS_EXPORT_DESIGN_v1.0.0.md` for current behavior; the design record is retained as history).

### Notifications (Without `verified`)

All five routes below carry `withoutMiddleware('verified')` — users with unverified emails can still read notifications.

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/notifications` | `NotificationController@index` | `notifications.index` |
| GET | `/notifications/unread-count` | `NotificationController@unreadCount` | `notifications.unread-count` |
| PATCH | `/notifications/{id}/read` | `NotificationController@markAsRead` | `notifications.mark-as-read` |
| PATCH | `/notifications/mark-all-read` | `NotificationController@markAllAsRead` | `notifications.mark-all-read` |
| GET | `/notifications/page` | Closure → `Notifications/Index` | `notifications.page` |

### Onboarding (9 Routes)

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/onboarding/state` | `OnboardingController@state` | `onboarding.state` |
| POST | `/onboarding/skip` | `OnboardingController@skip` | `onboarding.skip` |
| POST | `/onboarding/complete` | `OnboardingController@complete` | `onboarding.complete` |
| POST | `/onboarding/replay` | `OnboardingController@replay` | `onboarding.replay` |
| POST | `/onboarding/step` | `OnboardingController@updateStep` | `onboarding.step` |
| POST | `/onboarding/guide-seen` | `OnboardingController@markGuideSeen` | `onboarding.guide-seen` |
| POST | `/onboarding/checklist/mark` | `OnboardingController@markChecklistItem` | `onboarding.checklist.mark` |
| POST | `/onboarding/checklist/dismiss` | `OnboardingController@dismissChecklist` | `onboarding.checklist.dismiss` |
| POST | `/onboarding/skip-profile` | `OnboardingController@skipProfile` | `onboarding.skip-profile` |

### Session-Authenticated API (in `web.php`)

Session-based, not stateless: these live in `web.php` under `auth` + `verified` + `throttle:api-global` with the `/api` prefix.

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/api/clients` | `ClientSelectController@search` | — |
| GET | `/api/clients/email-check` | `ClientSelectController@checkEmail` | `api.clients.email-check` |
| GET | `/api/clients/{client}` | `ClientSelectController@show` | — |

---

## Role-Gated Routes

### Case category input and filters

For case create, update, and save-draft mutations listed below, `category_ids` is the canonical input: an array of distinct UUIDs identifying active categories, synchronized to the `case_category` pivot. The deprecated scalar `category_id` remains a compatibility input for single-category clients and is converted to a one-element assignment. When category input is supplied, provide one field or the other, never both; sending both is invalid even if one is null or empty. The publish endpoint does not accept category input; it consumes and validates the assignments already stored for the case.

For mutation inputs, an omitted category field means "do not change" on update/save-draft. Draft creation may omit categories, and draft save/update may omit them; a null or empty scalar, or a null/empty `category_ids` array, means no category assignment and is allowed for drafts. A non-draft create requires at least one active category; a non-draft update may omit category fields to retain its assignments, but cannot clear them. Category IDs must be valid UUIDs and active; malformed, duplicate, or inactive IDs are rejected. Publishing consumes the stored pivot assignments and requires at least one active category.

The pivot is canonical. `cases.category_id` is a deprecated compatibility mirror containing the deterministic primary: retain the current mirror when it is still assigned; otherwise choose the active assignment by lowest `sort_order`, then `name`, then `id`. A legacy scalar mutation becomes the sole pivot assignment and its value becomes the mirror.

For `GET /cases`, `GET /cases/export-excel`, `GET /clients`, `GET /clients/export-excel`, `GET /referrals`, and `GET /referrals/export-excel`, `category_id` and `category_ids` are filters, not mutations. Either may be supplied; if both are supplied they are combined, not treated as a mutation conflict. A scalar is normalized into the selected-ID set, null/empty values mean no category filter, and `category_ids` accepts at most 50 distinct UUIDs. These are ANY filters: a result matches when at least one selected ID is present in the canonical case-category pivot or in the legacy `cases.category_id` mirror; client and referral results inherit this case-category match through their associated cases.

### CASE_MANAGER + ADMIN (Cases, Stakeholders, Audit Export)

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/cases` | `CaseController@index` | `cases.index` |
| GET | `/cases/intake-queue` | `CaseController@intakeQueue` | `cases.intake-queue` |
| GET | `/cases/create` | `CaseController@create` | `cases.create` |
| POST | `/cases` | `CaseController@store` | `cases.store` |
| GET | `/cases/drafts` | `CaseController@drafts` | `cases.drafts` |
| GET | `/cases/trash` | `CaseController@trashIndex` | `cases.trash` |
| GET | `/cases/export-excel` | `CaseController@exportExcel` | `cases.export-excel` |
| GET | `/cases/export-count` | `CaseController@exportCount` | `cases.export-count` |
| GET | `/cases/{case}/export-pdf` | `CaseController@exportPdf` | `cases.export-pdf` |
| DELETE | `/cases/{case}/destroy-draft` | `CaseController@destroyDraft` | `cases.drafts.destroy` |
| GET | `/cases/{case}/edit-draft` | `CaseController@editDraft` | `cases.edit-draft` |
| GET | `/cases/{case}/review-intake` | `CaseController@reviewIntake` | `cases.review-intake` |
| PUT | `/cases/{case}/save-draft` | `CaseController@updateDraft` | `cases.save-draft` |
| POST | `/cases/{case}/publish` | `CaseController@publish` | `cases.publish` |
| POST | `/cases/{case}/archive` | `CaseController@archive` | `cases.archive` |
| POST | `/cases/{case}/unarchive` | `CaseController@unarchive` | `cases.unarchive` |
| DELETE | `/cases/{case}/delete-archived` | `CaseController@deleteArchived` | `cases.delete-archived` |
| POST | `/cases/{case}/restore` | `CaseController@restore` | `cases.restore` |
| POST | `/cases/{case}/reject-intake` | `CaseController@rejectIntake` | `cases.reject-intake` |
| PATCH | `/cases/{case}` | `CaseController@update` | `cases.update` |
| POST | `/cases/{case}/toggle-status` | `CaseController@toggleStatus` | `cases.toggle-status` |
| POST | `/case-issues/quick` | `CaseIssueController@quickStore` | `case-issues.quick` |
| GET | `/stakeholders` | `StakeholderController@index` | `stakeholders.index` |
| GET | `/stakeholders/{stakeholder}` | `StakeholderController@show` | `stakeholders.show` |
| GET | `/audit-logs/export` | `AuditLogController@export` | `audit-logs.export` |
| GET | `/api/cases/{case}/audit-logs` | `AuditLogController@caseAuditLogs` | `api.cases.audit-logs` (`throttle:api-global`) |

The audit export route deliberately stays in this CASE_MANAGER + ADMIN group (the controller additionally enforces admin-only) so agency users never see an export surface.

### CASE_MANAGER + ADMIN + AGENCY (Shared Reads)

Rows are scoped per role inside the controllers — admin sees all, case managers see their cases, agencies see their agency's referrals and the cases those referrals belong to.

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/audit-logs` | `AuditLogController@index` | `audit-logs.index` |
| GET | `/cases/{case}` | `CaseController@show` | `cases.show` (standalone triple-role route) |
| GET | `/cases/{case}/documents` | `CaseDocumentController@index` | `cases.documents.index` |
| GET | `/cases/{case}/documents/{document}` | `CaseDocumentController@show` | `cases.documents.show` |
| GET | `/cases/{case}/documents/{document}/download` | `CaseDocumentController@download` | `cases.documents.download` |
| GET | `/clients` | `ClientController@index` | `clients.index` |
| GET | `/clients/export-excel` | `ClientController@exportExcel` | `clients.export-excel` |
| GET | `/clients/export-count` | `ClientController@exportCount` | `clients.export-count` |
| GET | `/clients/{client}` | `ClientController@show` | `clients.show` |
| POST | `/clients/{client}/avatar` | `ClientController@storeAvatar` | `clients.avatar.store` |
| DELETE | `/clients/{client}/avatar` | `ClientController@destroyAvatar` | `clients.avatar.destroy` |
| GET | `/referrals/{referral}/client-requests` | `ReferralClientRequestController@index` | `referrals.client-requests.index` |
| GET | `/referrals/{referral}/client-requests/attachments/{attachment}/download` | `ReferralClientRequestController@downloadAgencyAttachment` | `referrals.client-requests.attachments.download` |
| POST | `/client-access-links/{accessLink}/revoke` | `ReferralClientRequestController@revoke` | `referrals.client-requests.access.revoke` |
| GET | `/feedbacks` | `FeedbackController@dashboard` | `feedbacks.index` |
| GET | `/feedbacks/servqual-config` | `FeedbackController@servqualConfig` | `feedbacks.servqual-config` |
| GET | `/feedbacks/export-excel` | `FeedbackController@exportExcel` | `feedbacks.export-excel` |
| GET | `/feedbacks/{feedback}` | `FeedbackController@show` | `feedbacks.show` |

### CASE_MANAGER + ADMIN (Documents — Write)

Reads are triple-role (above); writes stay dual-role.

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| POST | `/cases/{case}/documents` | `CaseDocumentController@store` | `cases.documents.store` |
| DELETE | `/cases/{case}/documents/{document}` | `CaseDocumentController@destroy` | `cases.documents.destroy` |

### AGENCY Only

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| POST | `/referrals/{referral}/client-requests` | `ReferralClientRequestController@store` | `referrals.client-requests.store` | `throttle:agency-client-request-create`, `throttle:agency-client-delivery-recipient` |
| POST | `/client-requests/{clientRequest}/messages` | `ReferralClientRequestController@sendMessage` | `referrals.client-requests.messages.store` | — |
| POST | `/client-requests/{clientRequest}/complete` | `ReferralClientRequestController@complete` | `referrals.client-requests.complete` | — |
| POST | `/client-requests/{clientRequest}/cancel` | `ReferralClientRequestController@cancel` | `referrals.client-requests.cancel` | — |
| POST | `/client-requests/{clientRequest}/reopen` | `ReferralClientRequestController@reopen` | `referrals.client-requests.reopen` | `throttle:agency-client-access`, `throttle:agency-client-delivery-recipient` |
| POST | `/client-requests/{clientRequest}/access/issue` | `ReferralClientRequestController@issue` | `referrals.client-requests.access.issue` | `throttle:agency-client-access`, `throttle:agency-client-delivery-recipient` |
| POST | `/client-requests/{clientRequest}/access/reissue` | `ReferralClientRequestController@reissue` | `referrals.client-requests.access.reissue` | `throttle:agency-client-access`, `throttle:agency-client-delivery-recipient` |
| GET | `/services` | `AgencyServiceController@index` | `agency.services.index` | — |
| POST | `/services` | `AgencyServiceController@store` | `agency.services.store` | — |
| PATCH | `/services/{service}` | `AgencyServiceController@update` | `agency.services.update` | — |
| DELETE | `/services/{service}` | `AgencyServiceController@destroy` | `agency.services.destroy` | — |

### AGENCY Only — Survey Form Builder

| Method | URI | Controller | Name (`survey.forms.*`) |
|--------|-----|-----------|------------------------|
| GET | `/survey-forms` | `SurveyFormController@index` | `survey.forms.index` |
| GET | `/survey-forms/create` | `SurveyFormController@create` | `survey.forms.create` |
| POST | `/survey-forms` | `SurveyFormController@store` | `survey.forms.store` |
| GET | `/survey-forms/{form}/edit` | `SurveyFormController@edit` | `survey.forms.edit` |
| PATCH | `/survey-forms/{form}` | `SurveyFormController@update` | `survey.forms.update` |
| PATCH | `/survey-forms/{form}/activate` | `SurveyFormController@activate` | `survey.forms.activate` |
| DELETE | `/survey-forms/{form}` | `SurveyFormController@destroy` | `survey.forms.destroy` |

### CASE_MANAGER + ADMIN + AGENCY — Survey Responses

| Method | URI | Controller | Name (`survey.responses.*`) |
|--------|-----|-----------|----------------------------|
| GET | `/surveys` | `SurveyResponseController@index` | `survey.responses.index` |
| GET | `/surveys/{invitation}` | `SurveyResponseController@show` | `survey.responses.show` |

### Overdue Referrals (Triple-Role, Outside `verified`)

These two routes sit outside the `auth` + `verified` mega-group but still require `auth` + `role:ADMIN,CASE_MANAGER,AGENCY`.

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/overdue-referrals` | `OverdueReferralController@index` | `overdue-referrals.index` |
| POST | `/overdue-referrals/send-reminders` | `OverdueReferralController@sendReminders` | `overdue-referrals.send-reminders` |

### OFW Portal (`role:OFW`, 7 Routes)

| Method | URI | Controller | Name (`ofw.*`) |
|--------|-----|-----------|----------------|
| GET | `/my-cases` | `OfwDashboardController@index` | `ofw.dashboard` |
| GET | `/my-cases/notifications` | `OfwDashboardController@notifications` | `ofw.notifications` |
| PATCH | `/my-cases/notifications/{id}/read` | `OfwDashboardController@markNotificationAsRead` | `ofw.notifications.mark-as-read` |
| GET | `/my-cases/{case}/agencies/{referral}/milestones` | `OfwDashboardController@agencyMilestones` | `ofw.case.milestones` |
| GET | `/my-cases/profile` | `OfwProfileController@edit` | `ofw.profile.edit` |
| PUT | `/my-cases/profile` | `OfwProfileController@update` | `ofw.profile.update` |
| GET | `/my-cases/{id}` | `OfwDashboardController@show` | `ofw.case.show` |

---

## Admin Routes (ADMIN + IP Whitelist)

All prefixed with `/admin`, named with the `admin.` prefix, behind `role:ADMIN` + `ip.whitelist`. One exception: `GET /admin/agencies/{agency}` (`admin.agencies.show`) allows `role:ADMIN,CASE_MANAGER` (read-only for non-admin) with `ip.whitelist`.

### Single-Agency Detail (ADMIN + CASE_MANAGER, Read-Only)

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/agencies/{agency}` | `AdminAgencyController@show` | `admin.agencies.show` |

### User Management (~13 Named + 1 Unnamed)

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/users` | `AdminUserController@index` | `admin.users.index` |
| POST | `/admin/users` | `AdminUserController@store` | `admin.users.store` |
| POST | `/admin/users/invite` | `AdminUserController@invite` | `admin.users.invite` |
| GET | `/admin/users/invite` | aborts 404 | — (intentionally no GET invite page; the invite is submitted from the users modal) |
| POST | `/admin/users/invites/{inviteId}/resend` | `AdminUserController@resendInvite` | `admin.users.invites.resend` (`whereUuid`) |
| DELETE | `/admin/users/invites/{inviteId}` | `AdminUserController@cancelInvite` | `admin.users.invites.cancel` (`whereUuid`) |
| GET | `/admin/users/{user}` | `AdminUserController@show` | `admin.users.show` (`whereUuid`) |
| PATCH | `/admin/users/{user}` | `AdminUserController@update` | `admin.users.update` (`whereUuid`) |
| DELETE | `/admin/users/{user}` | `AdminUserController@destroy` | `admin.users.destroy` (`whereUuid`) |
| PATCH | `/admin/users/{user}/reactivate` | `AdminUserController@reactivate` | `admin.users.reactivate` (`whereUuid`) |
| PATCH | `/admin/users/{user}/verify` | `AdminUserController@verify` | `admin.users.verify` (`whereUuid`) |
| POST | `/admin/users/{user}/reset-mfa` | `AdminUserController@resetMfa` | `admin.users.reset-mfa` (`whereUuid`) |
| POST | `/admin/users/{user}/email-change/send-otp` | `AdminUserController@sendEmailChangeOtp` | `admin.users.email-change.send-otp` (`whereUuid`) |
| POST | `/admin/users/{user}/email-change/verify-otp` | `AdminUserController@verifyEmailChangeOtp` | `admin.users.email-change.verify-otp` (`whereUuid`) |

### Agency Management

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/agencies` | `AdminAgencyController@index` | `admin.agencies.index` |
| POST | `/admin/agencies` | `AdminAgencyController@store` | `admin.agencies.store` |
| PATCH | `/admin/agencies/{agency}` | `AdminAgencyController@update` | `admin.agencies.update` |
| DELETE | `/admin/agencies/{agency}` | `AdminAgencyController@destroy` | `admin.agencies.destroy` |
| PATCH | `/admin/agencies/{agency}/reactivate` | `AdminAgencyController@reactivate` | `admin.agencies.reactivate` |

### Service Management

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/services` | `AdminServiceController@index` | `admin.services.index` |
| POST | `/admin/services` | `AdminServiceController@store` | `admin.services.store` |
| PATCH | `/admin/services/{service}` | `AdminServiceController@update` | `admin.services.update` |
| DELETE | `/admin/services/{service}` | `AdminServiceController@destroy` | `admin.services.destroy` |

### Case Configuration

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/case-categories` | `AdminCaseCategoryController@index` | `admin.case-categories.index` |
| POST | `/admin/case-categories` | `AdminCaseCategoryController@store` | `admin.case-categories.store` |
| PATCH | `/admin/case-categories/{caseCategory}` | `AdminCaseCategoryController@update` | `admin.case-categories.update` |
| DELETE | `/admin/case-categories/{caseCategory}` | `AdminCaseCategoryController@destroy` | `admin.case-categories.destroy` |
| PATCH | `/admin/case-categories/{caseCategory}/reactivate` | `AdminCaseCategoryController@reactivate` | `admin.case-categories.reactivate` |
| GET | `/admin/case-statuses` | `AdminCaseStatusController@index` | `admin.case-statuses.index` |
| POST | `/admin/case-statuses` | `AdminCaseStatusController@store` | `admin.case-statuses.store` |
| PATCH | `/admin/case-statuses/{caseStatus}` | `AdminCaseStatusController@update` | `admin.case-statuses.update` |
| DELETE | `/admin/case-statuses/{caseStatus}` | `AdminCaseStatusController@destroy` | `admin.case-statuses.destroy` |
| GET | `/admin/case-issues` | `AdminCaseIssueController@index` | `admin.case-issues.index` |
| POST | `/admin/case-issues` | `AdminCaseIssueController@store` | `admin.case-issues.store` |
| PATCH | `/admin/case-issues/{caseIssue}` | `AdminCaseIssueController@update` | `admin.case-issues.update` |
| DELETE | `/admin/case-issues/{caseIssue}` | `AdminCaseIssueController@destroy` | `admin.case-issues.destroy` |
| PATCH | `/admin/case-issues/{caseIssue}/reactivate` | `AdminCaseIssueController@reactivate` | `admin.case-issues.reactivate` |

### System Settings

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/system-settings` | `SystemSettingsController@index` | `admin.system-settings.index` |
| POST | `/admin/system-settings` | `SystemSettingsController@update` | `admin.system-settings.update` |
| POST | `/admin/system-settings/reindex-chatbot` | `SystemSettingsController@reindexChatbot` | `admin.system-settings.reindex-chatbot` |

### Data Export

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/data-export` | `DataExportController@index` | `admin.data-export.index` |
| GET | `/admin/data-export/export` | `DataExportController@export` | `admin.data-export.export` |

### System Administration

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/system/logs` | `LogViewerController@index` | `admin.system.logs` |
| GET | `/admin/system/logs/entries` | `LogViewerController@entries` | `admin.system.logs.entries` |
| GET | `/admin/system/logs/download` | `LogViewerController@download` | `admin.system.logs.download` |
| GET | `/admin/system/maintenance` | `MaintenanceController@index` | `admin.system.maintenance` |
| POST | `/admin/system/maintenance/toggle` | `MaintenanceController@toggle` | `admin.system.maintenance.toggle` |
| GET | `/admin/system/security` | `SecuritySettingsController@index` | `admin.system.security` |
| POST | `/admin/system/security` | `SecuritySettingsController@update` | `admin.system.security.update` |
| GET | `/admin/system/active-sessions` | `ActiveSessionsController@index` | `admin.system.active-sessions` |
| POST | `/admin/system/active-sessions/{session}/terminate` | `ActiveSessionsController@terminate` | `admin.system.active-sessions.terminate` |
| GET | `/admin/system/email-logs` | `EmailLogController@index` | `admin.system.email-logs.index` |
| POST | `/admin/system/email-logs/{emailLog}/resend` | `EmailLogController@resend` | `admin.system.email-logs.resend` |

---

## Exception Rendering (`bootstrap/app.php`)

| Condition | JSON / `api/*` Response | Web / Inertia Response |
|-----------|------------------------|------------------------|
| 503 | (no special JSON branch) | `errors.503` view with `Retry-After`-derived retry minutes |
| 404 (`NotFoundHttpException`) | `{"message": "Resource not found."}` 404 | `Errors/NotFound` page 404 |
| 404 (model not found) | `{"message": "Resource not found."}` 404 | default 404 handler |
| 403 (`AccessDeniedHttpException`) | `{"message": "Forbidden."}` 403 | `Errors/Forbidden` page 403 |
| 401 (`AuthenticationException`) | `{"message": "Unauthenticated."}` 401 | redirect to `login` |
| 422 (`ValidationException`) | `{"message": "Validation failed.", "errors": {...}}` 422 | Inertia default handling |
| 429 (`TooManyRequestsHttpException`) | `{"message": "Too many requests. Please slow down."}` 429 | `Errors/TooManyRequests` page 429 |
| 405 (`MethodNotAllowedHttpException`) | `{"message": "Method not allowed."}` 405 | redirect `/` |
| Catch-all (production) | `{"message": "An unexpected error occurred.", "incident_id": ...}` 500 | `Errors/ServerError` page 500 with `incidentId` |

In debug mode, Inertia requests that raise bubble a 409 with `X-Inertia-Location` so the full debug page renders on reload; production logs the trace with an incident ID and shows nothing sensitive.

---

## Rate Limiters (Named, `AppServiceProvider`)

28 named limiters. Prefer named limiters over inline `throttle:60,1` values: named limiters keep independent counters per feature so unrelated endpoints never share a budget.

| Limiter | Budget | Used By |
|---------|--------|---------|
| `login` | 10/min | `POST /login` |
| `otp` | 5/min | email-change OTP send/verify |
| `tracking` | 10/min | `/track/send-otp`, `/track/verify-otp` |
| `totp-challenge` | 3/min | `POST /login/mfa/totp` |
| `recovery-code` | 3/min | `POST /login/mfa/recovery` |
| `intake-otp` | 5/min | `/intake/verify-email` |
| `intake-duplicate` | 10/min | `/intake/check-duplicate` |
| `intake-submit` | 5/min | `/intake/submit`, `/intake/register`, `/track/register` |
| `address-lookup` | 60/min | `/api/address/*` (×5) |
| `chatbot` | 30/min | `POST /chatbot/message` |
| `contact-form` | 5/min | `POST /contact` |
| `csp-report` | 120/min | `POST /api/csp/report` |
| `readiness` | 60/min | `GET /api/readyz` |
| `resend-webhook` | 300/min | `POST /api/webhooks/resend` |
| `survey-view` | 30/min | `GET /survey/{token}` |
| `survey-submit` | 10/min | `POST /survey/{token}` |
| `track-request-message` | 20/min | `/track/request/messages` |
| `track-request-replacement` | 5/min | `/track/request/replacement` |
| `track-request-exchange` | 10/min | `/track/request/exchange` |
| `invite-register` | 10/min | `POST /invite/{token}` |
| `password-reset-request` | 5/min | `POST /forgot-password` |
| `email-verification` | 6/min | email verify / verification-notification |
| `reports-view` | 120/min | `GET /reports` |
| `api-global` | 60/min | session-auth `/api/*` helpers |
| `api-mutations` | 10/min | (reserved for authenticated mutations) |
| `agency-client-request-create` | 5/hour | agency client-request creation |
| `agency-client-access` | 3/hour | agency access issue/reissue, reopen |
| `agency-client-delivery-recipient` | 5/hour | per-recipient delivery cap (stacked with the above) |

---

## Route Count Summary

| Category | Approx. Count |
|----------|---------------|
| Public (landing, partners, contact, privacy, terms) | 7 |
| Public survey (token) | 2 |
| Public intake | 5 |
| Tracking portal + client-request track side | 12 |
| Helpdesk | 3 |
| Chatbot | 1 |
| Public stateless API (`api.php`) | 8 |
| Authentication — guest | 13 |
| Authentication — auth | 10 |
| Authenticated all roles (dashboard, profile, MFA, referrals, reports, notifications, onboarding, session API) | ~50 |
| CASE_MANAGER + ADMIN (cases ~21, quick issue, stakeholders, audit export, case audit-log API) | ~26 |
| Triple-role shared reads (audit, case show, documents read, clients, client-requests read, feedbacks) | ~18 |
| Documents write (dual-role) | 2 |
| AGENCY (client-requests 7, services 4, survey forms 7) | 18 |
| Survey responses dashboard | 2 |
| Overdue referrals (outside `verified`) | 2 |
| OFW portal | 7 |
| Admin (agencies 5 + single-agency show, services 4, users ~13, system-settings 3, case-categories 5, case-statuses 4, case-issues 5, data-export 2, system 11) | ~52 |
| **Total named** | **~165–175** |

For the exact count on any checkout: `php artisan route:list --except-vendor`.
