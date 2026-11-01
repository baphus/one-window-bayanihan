# API Contracts

> **Version:** 2.2.0 | **Source:** current route files, middleware, controllers and `php artisan route:list --except-vendor --json`
>
> **Current correction (2026-11-01):** route inventory reconciled with the current application, including the Administrator Monitoring group (`/admin/system-health`, `/admin/email-logs`, `/admin/failed-jobs`, `/admin/security-log`) and every `/audit-logs` route. Earlier version notes below are historical, not current route totals.
>
> **What changed in 2.1.0:** recounted every group against the current route files; corrected the auth tables (the previous revision named a `LoginOtpController` flow that no longer exists — login is `AuthenticatedSessionController`, MFA challenge is `MfaChallengeController`, invites are `RegisterViaInviteController`); added the survey-token, intake, expanded track, session-auth ClientSelect, OFW portal, client-request, survey-form, and expanded admin sections that were missing; split document read/write and audit-log visibility by role; added the `/api/readyz` vs `/up` distinction, the Resend webhook, the exception-rendering contract, and the full named-throttle table. Previous version: 2.0.0 (2026-07-11, ~164 routes).

## Overview

Application route definitions are organized in three files; framework routes are configured separately:

- `routes/web.php` — Application routes (public pages, session-authenticated app, session-authenticated `api/*` helpers)
- `routes/auth.php` — Authentication routes (required at the end of `web.php`)
- `routes/api.php` — Public stateless API routes (readiness probe, CSP reports, mail webhooks, registration email availability)

**Important:** Session-authenticated API-style endpoints (`/api/clients*`, `/api/referrals/{referral}/messages`, `/api/*/activity`) live in `web.php`, not `api.php`. Do not assume every API-looking route is in `routes/api.php`.

**Exact counts:** named-route totals drift as features land. For the authoritative count run:

```bash
php artisan route:list --except-vendor
```

Route totals depend on the checkout and installed vendor packages. Use the command above for application routes; do not subtract guessed development-package totals.

---

## Public Routes (No Authentication)

### Landing & Info Pages

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/` | `HomeController@index` | — | Landing page |
| GET | `/partners` | `Closure` | partners | web |
| GET | `/partners/{agency}` | `Closure` | partners.show | web |
| GET | `/contact` | `Closure` | contact | web |
| POST | `/contact` | `ContactController@store` | contact.store | web, turnstile, throttle:contact-form |
| GET | `/privacy` | `Closure` | privacy | web |
| GET | `/terms` | `Closure` | terms | web |

### Public Survey (Token-Based)

Token-based feedback submission — no auth required. Tokens are unguessable; possession of the token is the authorization.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/survey/{token}` | `PublicSurveyController@show` | survey.public.show | web, throttle:survey-view |
| POST | `/survey/{token}` | `PublicSurveyController@submit` | survey.public.submit | web, throttle:survey-submit |

### Case Filing

Public intake routes are retired. Citizens register and verify email, then file from their signed-in portal. New cases are saved as `PENDING_REVIEW`. Case numbers and tracking codes already exist before approval. No server-side draft/save/publish workflow remains.

### Case Tracking Portal

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/track` | `TrackController@index` | track.index | web |
| POST | `/track/send-otp` | `TrackController@sendOtp` | track.send-otp | web, turnstile, throttle:tracking |
| GET | `/track/verify-otp` | `Closure` | track.verify-otp.get | web |
| POST | `/track/verify-otp` | `TrackController@verifyOtp` | track.verify-otp | web, throttle:tracking |
| GET | `/track/case` | `TrackController@show` | track.show | web, throttle:tracking |
| GET | `/track/case/{tracker_number}/referrals/{referral}/milestones` | `TrackController@milestones` | track.milestones | web, throttle:tracking |
| POST | `/track/register` | `TrackRegistrationController@store` | track.register | web, throttle:intake-submit |
| GET | `/track/request` | `ReferralClientRequestController@show` | track.request.index | web, throttle:tracking |
| POST | `/track/request/exchange` | `ReferralClientRequestController@exchange` | track.request.exchange | web, throttle:track-request-exchange |
| POST | `/track/request/messages` | `ReferralClientRequestController@clientMessage` | track.request.messages.store | web, throttle:track-request-message |
| POST | `/track/request/replacement` | `ReferralClientRequestController@replacement` | track.request.replacement | web, throttle:track-request-replacement |
| GET | `/track/request/attachments/{attachment}/download` | `ReferralClientRequestController@downloadAttachment` | track.request.attachments.download | web, throttle:tracking |

### Helpdesk (Knowledge Base)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/help` | `Closure` | helpdesk.index | web |
| GET | `/help/search` | `Closure` | helpdesk.search | web |
| GET | `/help/{slug}` | `Closure` | helpdesk.show | web |

### Chatbot

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| POST | `/chatbot/message` | `ChatbotController@message` | chatbot.message | web, turnstile, throttle:chatbot |

`ChatbotController` is a one-liner: it delegates to `ChatbotConversationService::reply()` with the validated payload and `ChatbotAudience::forUser($request->user())` (guest-safe; audience is server-resolved, never trusted from the client).

The endpoint accepts `message` (up to 1,000 characters), optional `history` (up to 20 `{role: user|bot, text}` entries, each up to 1,000 characters), and optional `lastContext` (`source_type`, `source_label`, `article_title`). Context is a hint; the server re-resolves audience permissions and re-reads evidence every turn.

Responses contain `reply`, `status`, `sources`, `actions`, and explicitly nullable `lastContext`. Status is `answered`, `greeting`, `clarification`, `unsupported`, or `unavailable`. A source has `source_type`, `slug`, `heading`, `url`, `sections`, and, for helpdesk articles, canonical `article_title`. Article links identify only authorized content actually read and selected as support. Directory references have a null URL and a directory label. No vector-confidence score is returned. Clients must clear stored context on null and clear chat on identity/role changes. See `docs/CHATBOT_PIPELINE_v1.0.0.md` (which supersedes `docs/CHATBOT_AGENT.md`) for the full pipeline, configuration, and verification.

### Public API (`routes/api.php`)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/api/readyz` | `Api\ReadinessController` | monitoring.readyz | api, throttle:readiness |
| POST | `/api/csp/report` | `Api\CspViolationController@report` | (unnamed) | api, throttle:csp-report |
| POST | `/api/webhooks/resend` | `Api\ResendWebhookController` | webhooks.resend | api, throttle:resend-webhook |

Notes:

- **`GET /up` vs `GET /api/readyz`.** `/up` is the shallow framework health endpoint (configured in `bootstrap/app.php`); the hosting platform's liveness probe must use it. `/api/readyz` is the deep readiness probe (database, scheduler heartbeat, queue backlog, image rendering) for external monitoring. It requires the `X-Monitoring-Token` header and returns 404 when no token is configured. They are deliberately different: pointing the liveness probe at the deep check turns a database blip into a restart loop.
- **PSGC address data (no endpoints).** Philippine Standard Geographic Code data lives in the generated `resources/js/data/philippine-addresses.ts` file (regenerate with `npm run addresses:sync`); the frontend reads it directly and `PhilippineAddressService` parses the same file server-side. The former five public `/api/address/*` endpoints were removed (unused — no frontend code called them).
- **Resend webhook.** Authenticated by Svix signature inside the controller (see the mail webhook verifier service), not by session or token. It lives in `api.php` so it bypasses CSRF, sessions, and the MFA middleware the web group appends.

### Health Check

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/up` | Framework shallow health handler (`bootstrap/app.php`) | (framework) | No application role gate; platform liveness probe |

---

## Authentication Routes (`routes/auth.php`)

### Guest-Only (Unauthenticated)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/login` | `Auth\AuthenticatedSessionController@create` | login | web, guest |
| POST | `/login` | `Auth\AuthenticatedSessionController@store` | login.store | web, guest, turnstile, throttle:login |
| GET | `/login/mfa` | `Auth\MfaChallengeController@show` | mfa.challenge.show | web, guest, mfa.pending |
| POST | `/login/mfa/totp` | `Auth\MfaChallengeController@totp` | mfa.challenge.totp | web, guest, mfa.pending, throttle:totp-challenge |
| POST | `/login/mfa/recovery` | `Auth\MfaChallengeController@recovery` | mfa.challenge.recovery | web, guest, mfa.pending, throttle:recovery-code |
| POST | `/login/mfa/cancel` | `Auth\MfaChallengeController@cancel` | mfa.challenge.cancel | web, guest, mfa.pending |
| GET | `/invite/{token}` | `Auth\RegisterViaInviteController@show` | register-via-invite | web, guest |
| POST | `/invite/{token}` | `Auth\RegisterViaInviteController@store` | register-via-invite.store | web, guest, throttle:invite-register |
| GET | `/forgot-password` | `Auth\PasswordResetLinkController@create` | password.request | web, guest |
| POST | `/forgot-password` | `Auth\PasswordResetLinkController@store` | password.email | web, guest, turnstile, throttle:password-reset-request |
| GET | `/reset-password/{token}` | `Auth\NewPasswordController@create` | password.reset | web, guest |
| POST | `/reset-password` | `Auth\NewPasswordController@store` | password.store | web, guest, throttle:password-reset-request |
| GET | `/forgot-email` | `Closure` | forgot-email | web, guest |

Notes:

- The login POST is named `login.store`, not `login`: two routes sharing the name `login` breaks `route:cache` ("Unable to prepare route [login] for serialization"). `route('login')` still resolves to the same `/login` path for form actions.
- Staff registration uses admin-issued invites (`/invite/{token}`); citizens use the account-first registration flow.

### Authenticated

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/confirm-password` | `Auth\ConfirmablePasswordController@show` | password.confirm | web, auth |
| POST | `/confirm-password` | `Auth\ConfirmablePasswordController@store` | (unnamed) | web, auth, throttle:password-confirm |
| GET | `/verify-email` | `Auth\EmailVerificationPromptController` | verification.notice | web, auth |
| GET | `/verify-email/{id}/{hash}` | `Auth\VerifyEmailController` | verification.verify | web, auth, signed, throttle:email-verification |
| POST | `/email/verification-notification` | `Auth\EmailVerificationNotificationController@store` | verification.send | web, auth, throttle:email-verification |
| PUT | `/password` | `Auth\PasswordController@update` | password.update | web, auth, throttle:password-confirm |
| GET | `/profile/email-change` | `EmailChangeController@init` | profile.email-change.init | web, auth |
| POST | `/profile/email-change/send-otp` | `EmailChangeController@sendOtp` | profile.email-change.send-otp | web, auth, throttle:otp |
| POST | `/profile/email-change/verify-otp` | `EmailChangeController@verifyOtp` | profile.email-change.verify-otp | web, auth, throttle:otp |
| POST | `/logout` | `Closure` | logout | web, auth |

Logout writes through `AuditLogger::logout()` before invalidating the session, clearing MFA-pending state and clearing Inertia history. It does not rely on a framework event listener for the audit row.

---

## Authenticated Routes (See Per-Route Role Gates)

Routes below use middleware shown in each row; staff gates apply to referrals and reports. Four notification routes explicitly opt out of `verified` via `withoutMiddleware('verified')`, allowing unverified users to read their notifications.

### Dashboard & Profile

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/dashboard` | `DashboardController@index` | dashboard | web, auth, verified |
| GET | `/profile` | `ProfileController@edit` | profile.edit | web, auth, verified |
| PATCH | `/profile` | `ProfileController@update` | profile.update | web, auth, verified |
| DELETE | `/profile` | `ProfileController@destroy` | profile.destroy | web, auth, verified |

### MFA Management

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/profile/mfa/status` | `MfaController@status` | profile.mfa.status | web, auth, verified |
| POST | `/profile/mfa/generate` | `MfaController@generateSecret` | profile.mfa.generate | web, auth, verified |
| POST | `/profile/mfa/verify` | `MfaController@verifyAndEnable` | profile.mfa.verify | web, auth, verified |
| POST | `/profile/mfa/disable` | `MfaController@disable` | profile.mfa.disable | web, auth, verified, throttle:password-confirm |
| POST | `/profile/mfa/recovery-codes/regenerate` | `MfaController@regenerateRecoveryCodes` | profile.mfa.recovery-codes.regenerate | web, auth, verified, throttle:password-confirm |

### Referrals (Staff Roles)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/referrals` | `ReferralController@index` | referrals.index | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/referrals/create` | `ReferralController@create` | referrals.create | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals` | `ReferralController@store` | referrals.store | web, auth, verified |
| GET | `/referrals/export-excel` | `ReferralController@exportExcel` | referrals.export-excel | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/referrals/export-count` | `ReferralController@exportCount` | referrals.export-count | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/referrals/{referral}` | `ReferralController@show` | referrals.show | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| PATCH | `/referrals/{referral}/status` | `ReferralController@updateStatus` | referrals.update-status | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals/{referral}/milestones` | `ReferralController@addMilestone` | referrals.milestones.store | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals/{referral}/services` | `ReferralController@addService` | referrals.services.add | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| DELETE | `/referrals/{referral}/services/{service}` | `ReferralController@removeService` | referrals.services.remove | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals/{referral}/services/{service}/requirements` | `ReferralController@addRequirement` | referrals.services.requirements.add | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| PATCH | `/referrals/{referral}/services/{service}/requirements/{requirement}` | `ReferralController@updateRequirement` | referrals.services.requirements.update | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| DELETE | `/referrals/{referral}/services/{service}/requirements/{requirement}` | `ReferralController@deleteRequirement` | referrals.services.requirements.delete | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals/{referral}/comments` | `ReferralController@addComment` | referrals.comments.store | web, auth, verified |
| POST | `/referrals/{referral}/comments/{comment}/reply` | `ReferralController@replyToComment` | referrals.comments.reply | web, auth, verified |
| GET | `/api/referrals/{referral}/messages` | `ReferralMessageController@index` | api.referrals.messages.index | web, auth, verified, throttle:api-global |
| POST | `/referrals/{referral}/messages` | `ReferralMessageController@store` | referrals.messages.store | web, auth, verified |
| POST | `/referrals/{referral}/messages/read` | `ReferralMessageController@markRead` | referrals.messages.read | web, auth, verified |
| POST | `/referrals/{referral}/attachments` | `ReferralController@addAttachment` | referrals.attachments.store | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals/{referral}/attachments/{attachment}/replace` | `ReferralController@replaceAttachment` | referrals.attachments.replace | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/referrals/{referral}/attachments/{attachment}/remove` | `ReferralController@deleteAttachment` | referrals.attachments.delete | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/referrals/{referral}/attachments/{attachment}/download` | `ReferralController@downloadAttachment` | referrals.attachments.download | web, auth, verified |
| GET | `/referrals/{referral}/attachments/{versionGroupId}/versions` | `ReferralController@getAttachmentVersions` | referrals.attachments.versions | web, auth, verified |

### Reports

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/reports` | `ReportsController@index` | reports.index | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY, throttle:reports-view |
| GET | `/reports/export-pdf` | `ReportsController@exportPdf` | reports.export-pdf | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY, throttle:reports-export |
| GET | `/reports/export-excel` | `ReportsController@exportExcel` | reports.export-excel | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY, throttle:reports-export |

Pre-flight row-cap guards, small-cell suppression, role scoping, and the attempt/outcome audit trail for the two exports are documented in `docs/REPORTS_EXPORT_v1.1.0.md` (which supersedes the pre-rebuild `docs/REPORTS_EXPORT_DESIGN_v1.0.0.md` for current behavior; the design record is retained as history).

### Notifications (Without `verified`)

All four routes below carry `withoutMiddleware('verified')` — users with unverified emails can still read notifications.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/notifications` | `NotificationController@index` | notifications.index | web, auth |
| GET | `/notifications/unread-count` | `NotificationController@unreadCount` | notifications.unread-count | web, auth |
| PATCH | `/notifications/{id}/read` | `NotificationController@markAsRead` | notifications.mark-as-read | web, auth |
| PATCH | `/notifications/mark-all-read` | `NotificationController@markAllAsRead` | notifications.mark-all-read | web, auth |
| GET | `/notifications/page` | `Closure` | notifications.page | web, auth, verified |

### Onboarding

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| POST | `/onboarding/checklist/mark` | `OnboardingController@markChecklistItem` | onboarding.checklist.mark | web, auth, verified |
| POST | `/onboarding/checklist/dismiss` | `OnboardingController@dismissChecklist` | onboarding.checklist.dismiss | web, auth, verified |
| POST | `/onboarding/skip-profile` | `OnboardingController@skipProfile` | onboarding.skip-profile | web, auth, verified |

### Session-Authenticated API (in `web.php`)

Staff-only (CASE_MANAGER and ADMIN), session-based, not stateless: these live in `web.php` under `auth` + `verified` + `throttle:api-global` with the `/api` prefix.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/api/clients` | `Api\ClientSelectController@search` | api.clients.index | web, auth, verified, throttle:api-global, role:CASE_MANAGER,ADMIN |
| GET | `/api/clients/email-check` | `Api\ClientSelectController@checkEmail` | api.clients.email-check | web, auth, verified, throttle:api-global, role:CASE_MANAGER,ADMIN |
| GET | `/api/clients/{client}` | `Api\ClientSelectController@show` | api.clients.show | web, auth, verified, throttle:api-global, role:CASE_MANAGER,ADMIN |

---

## Role-Gated Routes

### Case Category Input

`category_ids` is the canonical input backed by `case_category`; `category_id` remains a compatibility mirror. Approval requires active category assignments. See request validators and `CaseService` for mutation-specific requirements.

### Cases and Stakeholders (See Per-Route Role Gates)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/cases` | `CaseController@index` | cases.index | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/cases/create` | `CaseController@create` | cases.create | web, auth, verified, role:CASE_MANAGER,ADMIN,OFW |
| POST | `/cases` | `CaseController@store` | cases.store | web, auth, verified, role:CASE_MANAGER,ADMIN,OFW |
| GET | `/cases/trash` | `CaseController@trashIndex` | cases.trash | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/cases/export-excel` | `CaseController@exportExcel` | cases.export-excel | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/cases/export-count` | `CaseController@exportCount` | cases.export-count | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/cases/{case}/export-pdf` | `CaseController@exportPdf` | cases.export-pdf | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/cases/{case}/archive` | `CaseController@archive` | cases.archive | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/cases/{case}/unarchive` | `CaseController@unarchive` | cases.unarchive | web, auth, verified, role:CASE_MANAGER,ADMIN |
| DELETE | `/cases/{case}/delete-archived` | `CaseController@deleteArchived` | cases.delete-archived | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/cases/{case}/restore` | `CaseController@restore` | cases.restore | web, auth, verified, role:CASE_MANAGER,ADMIN |
| PATCH | `/cases/{case}` | `CaseController@update` | cases.update | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/cases/{case}/toggle-status` | `CaseController@toggleStatus` | cases.toggle-status | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/case-issues/quick` | `CaseIssueController@quickStore` | case-issues.quick | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/stakeholders` | `StakeholderController@index` | stakeholders.index | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/stakeholders/{stakeholder}` | `StakeholderController@show` | stakeholders.show | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/audit-logs/export` | `AuditLogController@export` | audit-logs.export | web, auth, verified, role:ADMIN |

Audit viewer, actor options and export routes require ADMIN. Case creation and filing also allow OFW; other case-management actions in this section remain staff-only.

### Shared Reads (See Per-Route Role Gates)

Rows are scoped per role inside the controllers — admin sees all, case managers see office-wide cases, agencies see their agency's referrals and the cases those referrals belong to.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/audit-logs` | `AuditLogController@index` | audit-logs.index | web, auth, verified, role:ADMIN |
| GET | `/cases/{case}` | `CaseController@show` | cases.show | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/cases/{case}/documents` | `CaseDocumentController@index` | cases.documents.index | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/cases/{case}/documents/{document}` | `CaseDocumentController@show` | cases.documents.show | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/cases/{case}/documents/{document}/download` | `CaseDocumentController@download` | cases.documents.download | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/clients` | `ClientController@index` | clients.index | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/clients/export-excel` | `ClientController@exportExcel` | clients.export-excel | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/clients/export-count` | `ClientController@exportCount` | clients.export-count | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/clients/{client}` | `ClientController@show` | clients.show | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| POST | `/clients/{client}/avatar` | `ClientController@storeAvatar` | clients.avatar.store | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| DELETE | `/clients/{client}/avatar` | `ClientController@destroyAvatar` | clients.avatar.destroy | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/referrals/{referral}/client-requests` | `ReferralClientRequestController@index` | referrals.client-requests.index | web, auth, verified, role:ADMIN,AGENCY |
| GET | `/referrals/{referral}/client-requests/attachments/{attachment}/download` | `ReferralClientRequestController@downloadAgencyAttachment` | referrals.client-requests.attachments.download | web, auth, verified, role:ADMIN,AGENCY |
| POST | `/client-access-links/{accessLink}/revoke` | `ReferralClientRequestController@revoke` | referrals.client-requests.access.revoke | web, auth, verified, role:ADMIN,AGENCY |
| GET | `/survey-forms` | `SurveyFormController@index` | survey.forms.index | web, auth, verified, role:AGENCY |
| GET | `/surveys` | `SurveyResponseController@index` | survey.responses.index | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/survey/{token}` | `PublicSurveyController@show` | survey.public.show | web, throttle:survey-view |

### CASE_MANAGER + ADMIN (Documents — Write)

Reads are triple-role (above); writes stay dual-role.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| POST | `/cases/{case}/documents` | `CaseDocumentController@store` | cases.documents.store | web, auth, verified, role:CASE_MANAGER,ADMIN |
| DELETE | `/cases/{case}/documents/{document}` | `CaseDocumentController@destroy` | cases.documents.destroy | web, auth, verified, role:CASE_MANAGER,ADMIN |

### AGENCY Only

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| POST | `/referrals/{referral}/client-requests` | `ReferralClientRequestController@store` | referrals.client-requests.store | web, auth, verified, role:AGENCY, throttle:agency-client-request-create, throttle:agency-client-delivery-recipient |
| POST | `/client-requests/{clientRequest}/messages` | `ReferralClientRequestController@sendMessage` | referrals.client-requests.messages.store | web, auth, verified, role:AGENCY |
| POST | `/client-requests/{clientRequest}/complete` | `ReferralClientRequestController@complete` | referrals.client-requests.complete | web, auth, verified, role:AGENCY |
| POST | `/client-requests/{clientRequest}/cancel` | `ReferralClientRequestController@cancel` | referrals.client-requests.cancel | web, auth, verified, role:AGENCY |
| POST | `/client-requests/{clientRequest}/reopen` | `ReferralClientRequestController@reopen` | referrals.client-requests.reopen | web, auth, verified, role:AGENCY, throttle:agency-client-access, throttle:agency-client-delivery-recipient |
| POST | `/client-requests/{clientRequest}/access/issue` | `ReferralClientRequestController@issue` | referrals.client-requests.access.issue | web, auth, verified, role:AGENCY, throttle:agency-client-access, throttle:agency-client-delivery-recipient |
| POST | `/client-requests/{clientRequest}/access/reissue` | `ReferralClientRequestController@reissue` | referrals.client-requests.access.reissue | web, auth, verified, role:AGENCY, throttle:agency-client-access, throttle:agency-client-delivery-recipient |
| GET | `/services` | `AgencyServiceController@index` | agency.services.index | web, auth, verified, role:AGENCY |
| POST | `/services` | `AgencyServiceController@store` | agency.services.store | web, auth, verified, role:AGENCY |
| PATCH | `/services/{service}` | `AgencyServiceController@update` | agency.services.update | web, auth, verified, role:AGENCY |
| DELETE | `/services/{service}` | `AgencyServiceController@destroy` | agency.services.destroy | web, auth, verified, role:AGENCY |

### AGENCY Only — Survey Form Builder

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/survey-forms` | `SurveyFormController@index` | survey.forms.index | web, auth, verified, role:AGENCY |
| GET | `/survey-forms/create` | `SurveyFormController@create` | survey.forms.create | web, auth, verified, role:AGENCY |
| POST | `/survey-forms` | `SurveyFormController@store` | survey.forms.store | web, auth, verified, role:AGENCY |
| GET | `/survey-forms/{form}/edit` | `SurveyFormController@edit` | survey.forms.edit | web, auth, verified, role:AGENCY |
| PATCH | `/survey-forms/{form}` | `SurveyFormController@update` | survey.forms.update | web, auth, verified, role:AGENCY |
| PATCH | `/survey-forms/{form}/activate` | `SurveyFormController@activate` | survey.forms.activate | web, auth, verified, role:AGENCY |
| DELETE | `/survey-forms/{form}` | `SurveyFormController@destroy` | survey.forms.destroy | web, auth, verified, role:AGENCY |

### CASE_MANAGER + ADMIN + AGENCY — Survey Responses

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/surveys` | `SurveyResponseController@index` | survey.responses.index | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |
| GET | `/surveys/{invitation}` | `SurveyResponseController@show` | survey.responses.show | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY |

### Overdue Referrals (Triple-Role, Outside `verified`)

These two routes sit outside the `auth` + `verified` mega-group but still require `auth` + `role:ADMIN,CASE_MANAGER,AGENCY`.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/overdue-referrals` | `Admin\OverdueReferralController@index` | overdue-referrals.index | web, auth, role:ADMIN,CASE_MANAGER,AGENCY |
| POST | `/overdue-referrals/send-reminders` | `Admin\OverdueReferralController@sendReminders` | overdue-referrals.send-reminders | web, auth, role:ADMIN,CASE_MANAGER,AGENCY |

### OFW Portal (`role:OFW`)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/my-cases` | `OfwDashboardController@index` | ofw.dashboard | web, auth, role:OFW |
| GET | `/my-cases/notifications` | `OfwDashboardController@notifications` | ofw.notifications | web, auth, role:OFW |
| PATCH | `/my-cases/notifications/{id}/read` | `OfwDashboardController@markNotificationAsRead` | ofw.notifications.mark-as-read | web, auth, role:OFW |
| GET | `/my-cases/{case}/agencies/{referral}/milestones` | `OfwDashboardController@agencyMilestones` | ofw.case.milestones | web, auth, role:OFW |
| GET | `/my-cases/profile` | `OfwProfileController@edit` | ofw.profile.edit | web, auth, role:OFW |
| PUT | `/my-cases/profile` | `OfwProfileController@update` | ofw.profile.update | web, auth, role:OFW |
| GET | `/my-cases/{id}` | `OfwDashboardController@show` | ofw.case.show | web, auth, role:OFW |

---

## Admin Routes (ADMIN)

All prefixed with `/admin`, named with the `admin.` prefix, behind `role:ADMIN`. One exception: `GET /admin/agencies/{agency}` (`admin.agencies.show`) allows `role:ADMIN,CASE_MANAGER` (read-only for non-admin).

### Single-Agency Detail (ADMIN + CASE_MANAGER, Read-Only)

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/agencies/{agency}` | `AdminAgencyController@show` | admin.agencies.show | web, auth, verified, role:ADMIN,CASE_MANAGER |

### User Management

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/users` | `AdminUserController@index` | admin.users.index | web, auth, verified, role:ADMIN |
| POST | `/admin/users` | `AdminUserController@store` | admin.users.store | web, auth, verified, role:ADMIN |
| POST | `/admin/users/invite` | `AdminUserController@invite` | admin.users.invite | web, auth, verified, role:ADMIN |
| GET | `/admin/users/invite` | `Closure` | admin. | web, auth, verified, role:ADMIN |
| POST | `/admin/users/invites/{inviteId}/resend` | `AdminUserController@resendInvite` | admin.users.invites.resend | web, auth, verified, role:ADMIN |
| DELETE | `/admin/users/invites/{inviteId}` | `AdminUserController@cancelInvite` | admin.users.invites.cancel | web, auth, verified, role:ADMIN |
| GET | `/admin/users/{user}` | `AdminUserController@show` | admin.users.show | web, auth, verified, role:ADMIN |
| PATCH | `/admin/users/{user}` | `AdminUserController@update` | admin.users.update | web, auth, verified, role:ADMIN |
| DELETE | `/admin/users/{user}` | `AdminUserController@destroy` | admin.users.destroy | web, auth, verified, role:ADMIN |
| PATCH | `/admin/users/{user}/reactivate` | `AdminUserController@reactivate` | admin.users.reactivate | web, auth, verified, role:ADMIN |
| PATCH | `/admin/users/{user}/verify` | `AdminUserController@verify` | admin.users.verify | web, auth, verified, role:ADMIN |
| POST | `/admin/users/{user}/reset-mfa` | `AdminUserController@resetMfa` | admin.users.reset-mfa | web, auth, verified, role:ADMIN, throttle:password-confirm |
| POST | `/admin/users/{user}/email-change/send-otp` | `AdminUserController@sendEmailChangeOtp` | admin.users.email-change.send-otp | web, auth, verified, role:ADMIN, throttle:password-confirm |
| POST | `/admin/users/{user}/email-change/verify-otp` | `AdminUserController@verifyEmailChangeOtp` | admin.users.email-change.verify-otp | web, auth, verified, role:ADMIN, throttle:password-confirm |

### Agency Management

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/agencies` | `AdminAgencyController@index` | admin.agencies.index | web, auth, verified, role:ADMIN |
| POST | `/admin/agencies` | `AdminAgencyController@store` | admin.agencies.store | web, auth, verified, role:ADMIN |
| PATCH | `/admin/agencies/{agency}` | `AdminAgencyController@update` | admin.agencies.update | web, auth, verified, role:ADMIN |
| DELETE | `/admin/agencies/{agency}` | `AdminAgencyController@destroy` | admin.agencies.destroy | web, auth, verified, role:ADMIN |
| PATCH | `/admin/agencies/{agency}/reactivate` | `AdminAgencyController@reactivate` | admin.agencies.reactivate | web, auth, verified, role:ADMIN |

### Service Management

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/services` | `AdminServiceController@index` | admin.services.index | web, auth, verified, role:ADMIN |
| POST | `/admin/services` | `AdminServiceController@store` | admin.services.store | web, auth, verified, role:ADMIN |
| PATCH | `/admin/services/{service}` | `AdminServiceController@update` | admin.services.update | web, auth, verified, role:ADMIN |
| DELETE | `/admin/services/{service}` | `AdminServiceController@destroy` | admin.services.destroy | web, auth, verified, role:ADMIN |

### Case Configuration

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/case-categories` | `Admin\AdminCaseCategoryController@index` | admin.case-categories.index | web, auth, verified, role:ADMIN |
| POST | `/admin/case-categories` | `Admin\AdminCaseCategoryController@store` | admin.case-categories.store | web, auth, verified, role:ADMIN |
| PATCH | `/admin/case-categories/{caseCategory}` | `Admin\AdminCaseCategoryController@update` | admin.case-categories.update | web, auth, verified, role:ADMIN |
| DELETE | `/admin/case-categories/{caseCategory}` | `Admin\AdminCaseCategoryController@destroy` | admin.case-categories.destroy | web, auth, verified, role:ADMIN |
| PATCH | `/admin/case-categories/{caseCategory}/reactivate` | `Admin\AdminCaseCategoryController@reactivate` | admin.case-categories.reactivate | web, auth, verified, role:ADMIN |
| GET | `/admin/case-statuses` | `Admin\AdminCaseStatusController@index` | admin.case-statuses.index | web, auth, verified, role:ADMIN |
| POST | `/admin/case-statuses` | `Admin\AdminCaseStatusController@store` | admin.case-statuses.store | web, auth, verified, role:ADMIN |
| PATCH | `/admin/case-statuses/{caseStatus}` | `Admin\AdminCaseStatusController@update` | admin.case-statuses.update | web, auth, verified, role:ADMIN |
| DELETE | `/admin/case-statuses/{caseStatus}` | `Admin\AdminCaseStatusController@destroy` | admin.case-statuses.destroy | web, auth, verified, role:ADMIN |
| GET | `/admin/case-issues` | `Admin\AdminCaseIssueController@index` | admin.case-issues.index | web, auth, verified, role:ADMIN |
| POST | `/admin/case-issues` | `Admin\AdminCaseIssueController@store` | admin.case-issues.store | web, auth, verified, role:ADMIN |
| PATCH | `/admin/case-issues/{caseIssue}` | `Admin\AdminCaseIssueController@update` | admin.case-issues.update | web, auth, verified, role:ADMIN |
| DELETE | `/admin/case-issues/{caseIssue}` | `Admin\AdminCaseIssueController@destroy` | admin.case-issues.destroy | web, auth, verified, role:ADMIN |
| PATCH | `/admin/case-issues/{caseIssue}/reactivate` | `Admin\AdminCaseIssueController@reactivate` | admin.case-issues.reactivate | web, auth, verified, role:ADMIN |

### System Settings

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/system-settings` | `SystemSettingsController@index` | admin.system-settings.index | web, auth, verified, role:ADMIN |
| POST | `/admin/system-settings` | `SystemSettingsController@update` | admin.system-settings.update | web, auth, verified, role:ADMIN |
| POST | `/admin/system-settings/reindex-chatbot` | `SystemSettingsController@reindexChatbot` | admin.system-settings.reindex-chatbot | web, auth, verified, role:ADMIN |

### Monitoring

Administrator-only pages under the sidebar's Monitoring group.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/system-health` | `Admin\SystemHealthController@index` | admin.system-health.index | web, auth, verified, role:ADMIN |
| GET | `/admin/email-logs` | `Admin\EmailLogController@index` | admin.email-logs.index | web, auth, verified, role:ADMIN |
| GET | `/admin/email-logs/{emailLog}` | `Admin\EmailLogController@show` | admin.email-logs.show | web, auth, verified, role:ADMIN |
| GET | `/admin/failed-jobs` | `Admin\FailedJobController@index` | admin.failed-jobs.index | web, auth, verified, role:ADMIN |
| GET | `/admin/failed-jobs/{uuid}` | `Admin\FailedJobController@show` | admin.failed-jobs.show | web, auth, verified, role:ADMIN |
| POST | `/admin/failed-jobs/{uuid}/retry` | `Admin\FailedJobController@retry` | admin.failed-jobs.retry | web, auth, verified, role:ADMIN |
| GET | `/admin/security-log` | `Admin\SecurityLogController@index` | admin.security-log.index | web, auth, verified, role:ADMIN |
| GET | `/audit-logs` | `AuditLogController@index` | audit-logs.index | web, auth, verified, role:ADMIN |
| GET | `/audit-logs/actors` | `AuditLogController@actors` | audit-logs.actors | web, auth, verified, role:ADMIN |
| GET | `/audit-logs/export` | `AuditLogController@export` | audit-logs.export | web, auth, verified, role:ADMIN |

System health reads existing signals only (database, scheduler heartbeat, mail transport, queue backlog, failed-job count, chart rendering); it introduces no new probes. Email logs show status, error, and provider delivery events, with no resend action. The security log is drawn from `audit_logs` using the event set in `config/security_events.php`.

### Data Export (retired)

The admin full-data-export page and its routes were removed. Per-entity exports remain under `/cases/export-excel`, `/clients/export-excel`, `/referrals/export-excel`, and the Reports module.

### System Administration

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/admin/system/maintenance` | `Admin\MaintenanceController@index` | admin.system.maintenance | web, auth, verified, role:ADMIN |
| POST | `/admin/system/maintenance/toggle` | `Admin\MaintenanceController@toggle` | admin.system.maintenance.toggle | web, auth, verified, role:ADMIN |
| GET | `/admin/system/security` | `Admin\SecuritySettingsController@index` | admin.system.security | web, auth, verified, role:ADMIN |
| POST | `/admin/system/security` | `Admin\SecuritySettingsController@update` | admin.system.security.update | web, auth, verified, role:ADMIN |

---

## Exception Rendering (`bootstrap/app.php`)

| Condition | JSON / `api/*` Response | Web / Inertia Response |
|---|---|---|
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

## Rate Limiters

Named budgets and keys live in `AppServiceProvider`. Route rows above show which limiter applies; related limits can stack. Inspect the implementation for per-user, pending-email, recipient and IP keys rather than treating every budget as per-IP.

## Route Inventory Verification

Run `php artisan route:list --except-vendor --json` for the current application surface. Count returned rows, treating `GET|HEAD` as one route row. Vendor routes, including development tools and framework health/storage routes, are excluded by this flag. The section tables are reference groupings, not additive counts.

## Current Routes Added Since the Earlier Catalogue

`GET /api/register/email-availability` accepts a required valid `email` query parameter and returns `{ "taken": boolean }`. Email is trimmed and lowercased; soft-deleted users still count as taken. This public, throttled endpoint intentionally reveals availability during registration.

Controller authorization can narrow route middleware further.

| Method | URI | Handler | Name | Middleware |
|---|---|---|---|---|
| GET | `/activity` | `ActivityController@index` | activity.index | web, auth, verified, role:CASE_MANAGER,AGENCY,ADMIN |
| GET | `/admin/helpdesk-feedback` | `HelpdeskArticleFeedbackController@index` | admin.helpdesk-feedback.index | web, auth, verified, role:ADMIN |
| GET | `/api/cases/{case}/activity` | `ActivityController@caseActivity` | api.cases.activity | web, auth, verified, role:CASE_MANAGER,AGENCY,ADMIN, throttle:api-global |
| GET | `/api/my-linked-ofws` | `Api\ClientSelectController@linkedOfws` | api.my-linked-ofws | web, auth, verified, throttle:api-global, role:OFW |
| GET | `/api/referrals/{referral}/activity` | `ActivityController@referralActivity` | api.referrals.activity | web, auth, verified, role:CASE_MANAGER,AGENCY,ADMIN, throttle:api-global |
| GET | `/api/register/email-availability` | `Api\EmailAvailabilityController` | api.register.email-availability | api, throttle:email-availability |
| GET | `/audit-logs/actors` | `AuditLogController@actorOptions` | audit-logs.actors | web, auth, verified, role:ADMIN |
| GET | `/cases/review-pending` | `CaseController@reviewPending` | cases.review-pending | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/cases/{case}/review-approve` | `CaseController@reviewApprove` | cases.review-approve | web, auth, verified, role:CASE_MANAGER,ADMIN |
| POST | `/cases/{case}/review-reject` | `CaseController@reviewReject` | cases.review-reject | web, auth, verified, role:CASE_MANAGER,ADMIN |
| PATCH | `/client-request-attachments/{attachment}/review` | `ReferralClientRequestController@reviewAttachment` | referrals.client-requests.attachments.review | web, auth, verified, role:ADMIN,AGENCY |
| GET | `/dashboard/admin-period` | `DashboardController@adminPeriod` | dashboard.admin-period | web, auth, verified, throttle:api-global |
| GET | `/dashboard/agency-period` | `DashboardController@agencyPeriod` | dashboard.agency-period | web, auth, verified, throttle:api-global |
| GET | `/dashboard/case-manager-period` | `DashboardController@caseManagerPeriod` | dashboard.case-manager-period | web, auth, verified, throttle:api-global |
| GET | `/files/temp/{disk}/{path}` | `TemporaryFileController` | files.temp | web, signed, throttle:60,1 |
| GET | `/help/{slug}/feedback` | `HelpdeskArticleFeedbackController@show` | helpdesk.feedback.show | web, throttle:helpdesk-feedback |
| POST | `/help/{slug}/feedback` | `HelpdeskArticleFeedbackController@store` | helpdesk.feedback.store | web, throttle:helpdesk-feedback |
| GET | `/my-cases/intake` | `CaseController@intake` | ofw.intake | web, auth, role:OFW |
| POST | `/my-cases/intake` | `CaseController@storeIntake` | ofw.intake.store | web, auth, role:OFW |
| POST | `/my-cases/profile/avatar` | `OfwProfileController@storeAvatar` | ofw.profile.avatar.store | web, auth, role:OFW |
| DELETE | `/my-cases/profile/avatar` | `OfwProfileController@destroyAvatar` | ofw.profile.avatar.destroy | web, auth, role:OFW |
| GET | `/my-cases/{case}/agencies/{referral}/requests/{request}` | `ReferralClientRequestController@ownerShow` | ofw.case.requests.show | web, auth, role:OFW |
| GET | `/my-cases/{case}/agencies/{referral}/requests/{request}/attachments/{attachment}/download` | `ReferralClientRequestController@ownerDownloadAttachment` | ofw.case.requests.attachments.download | web, auth, role:OFW, throttle:tracking |
| POST | `/my-cases/{case}/agencies/{referral}/requests/{request}/messages` | `ReferralClientRequestController@ownerMessage` | ofw.case.requests.messages.store | web, auth, role:OFW, throttle:track-request-message |
| POST | `/profile/avatar` | `ProfileController@storeAvatar` | profile.avatar.store | web, auth, verified |
| DELETE | `/profile/avatar` | `ProfileController@destroyAvatar` | profile.avatar.destroy | web, auth, verified |
| DELETE | `/referrals/{referral}` | `ReferralController@destroy` | referrals.destroy | web, auth, verified, role:CASE_MANAGER,ADMIN |
| GET | `/register` | `Auth\OfwRegistrationController@create` | register | web, guest |
| POST | `/register` | `Auth\OfwRegistrationController@store` | register.store | web, guest, turnstile, throttle:register |
| GET | `/register/review` | `Auth\OfwRegistrationController@showReview` | register.review | web, guest, pending.registration |
| POST | `/register/review` | `Auth\OfwRegistrationController@storeReview` | register.review.store | web, guest, pending.registration, throttle:otp |
| POST | `/register/review/send-otp` | `Auth\OfwRegistrationController@sendOtp` | register.review.send-otp | web, guest, pending.registration, throttle:otp |
| GET | `/reports/print` | `ReportsController@print` | reports.print | web, auth, verified, role:CASE_MANAGER,ADMIN,AGENCY, throttle:reports-export |
| GET | `/staff/login` | `Auth\AuthenticatedSessionController@createStaff` | staff.login | web, guest |
| POST | `/staff/login` | `Auth\AuthenticatedSessionController@store` | staff.login.store | web, guest, turnstile, throttle:login |
| POST | `/track/logout` | `Closure` | track.logout | web, throttle:tracking |
