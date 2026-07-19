# API Contracts

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Source:** `routes/web.php`, `routes/auth.php`, `routes/api.php`

## Overview

All routes are defined in three files:
- `routes/web.php` — Application routes (session-authenticated)
- `routes/auth.php` — Authentication routes (included at end of web.php)
- `routes/api.php` — Public stateless API routes (address lookup, CSP reports)

**Important:** Session-authenticated API-style endpoints (`/api/clients`) are in `web.php`, not `api.php`.

---

## Public Routes (No Authentication)

### Landing & Info Pages

| Method | URI | Controller/Handler | Name | Notes |
|--------|-----|-------------------|------|-------|
| GET | `/` | Closure → `Welcome` | — | Landing page with active agencies |
| GET | `/partners` | Closure → `PublicAgencies/Index` | `partners` | Agency directory |
| GET | `/partners/{agency}` | Closure → `PublicAgencies/Show` | `partners.show` | Agency detail (by slug or UUID) |
| GET | `/contact` | Closure → `Contact/Index` | `contact` | Contact page |
| GET | `/privacy` | Closure → `Legal/PrivacyPolicy` | `privacy` | |
| GET | `/terms` | Closure → `Legal/TermsOfService` | `terms` | |

### Case Tracking Portal

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/track` | `TrackController@index` | `track.index` | — |
| POST | `/track/send-otp` | `TrackController@sendOtp` | `track.send-otp` | `throttle:tracking` |
| POST | `/track/verify-otp` | `TrackController@verifyOtp` | `track.verify-otp` | `throttle:tracking` |
| GET | `/track/case` | `TrackController@show` | `track.show` | — |
| GET | `/track/case/{tracker}/referrals/{referral}/milestones` | `TrackController@milestones` | `track.milestones` | — |

### Helpdesk (Knowledge Base)

| Method | URI | Handler | Name | Notes |
|--------|-----|---------|------|-------|
| GET | `/help` | Closure → `Helpdesk/Index` | `helpdesk.index` | `?category=` filter |
| GET | `/help/search` | Closure → `Helpdesk/Search` | `helpdesk.search` | `?q=` query |
| GET | `/help/{slug}` | Closure → `Helpdesk/Show` | `helpdesk.show` | Article by slug |

### Public Feedback

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/feedback/{token}` | `PublicFeedbackController@showForm` | `feedbacks.submit-page` | `throttle:30,1` |
| POST | `/feedback/{token}` | `PublicFeedbackController@submit` | `feedbacks.submit` | `throttle:10,1` |

### Chatbot

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| POST | `/chatbot/message` | `ChatbotController@message` | `chatbot.message` | `throttle:30,1` |

### Public API (`routes/api.php`)

| Method | URI | Controller | Middleware |
|--------|-----|-----------|------------|
| GET | `/api/address/regions` | `PhilippineAddressController@regions` | `throttle:60,1` |
| GET | `/api/address/provinces` | `PhilippineAddressController@provinces` | `throttle:60,1` |
| GET | `/api/address/cities` | `PhilippineAddressController@cities` | `throttle:60,1` |
| GET | `/api/address/barangays` | `PhilippineAddressController@barangays` | `throttle:60,1` |
| GET | `/api/address/resolve` | `PhilippineAddressController@resolve` | `throttle:60,1` |
| POST | `/api/csp/report` | `CspViolationController@report` | `throttle:120,1` |

---

## Authentication Routes (`routes/auth.php`)

### Guest-Only (Unauthenticated)

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/login` | Closure → `Auth/Login` | `login` | `guest` |
| POST | `/login` | `LoginOtpController@init` | `login.init` | `guest`, `turnstile`, `throttle:login` |
| POST | `/login/verify-otp` | `LoginOtpController@verifyOtp` | `login.verify-otp` | `guest`, `throttle:otp` |
| POST | `/login/resend-otp` | `LoginOtpController@resendOtp` | `login.resend-otp` | `guest`, `throttle:otp` |
| POST | `/login/verify-totp` | `LoginOtpController@verifyTotp` | `login.verify-totp` | `guest`, `throttle:totp-challenge` |
| POST | `/login/verify-recovery-code` | `LoginOtpController@verifyRecoveryCode` | `login.verify-recovery-code` | `guest`, `throttle:recovery-code` |
| GET | `/register` | `RegisteredUserController@create` | `register` | `guest` |
| POST | `/register` | `RegisteredUserController@store` | — | `guest` |
| GET | `/forgot-password` | `PasswordResetLinkController@create` | `password.request` | `guest` |
| POST | `/forgot-password` | `PasswordResetLinkController@store` | `password.email` | `guest` |
| GET | `/reset-password/{token}` | `NewPasswordController@create` | `password.reset` | `guest` |
| POST | `/reset-password` | `NewPasswordController@store` | `password.store` | `guest` |
| GET | `/forgot-email` | Closure → `Auth/ForgotEmail` | `forgot-email` | `guest` |

### Authenticated

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/confirm-password` | `ConfirmablePasswordController@show` | `password.confirm` | `auth` |
| POST | `/confirm-password` | `ConfirmablePasswordController@store` | — | `auth` |
| GET | `/verify-email` | `EmailVerificationPromptController` | `verification.notice` | `auth` |
| GET | `/verify-email/{id}/{hash}` | `VerifyEmailController` | `verification.verify` | `auth`, `signed`, `throttle:6,1` |
| POST | `/email/verification-notification` | `EmailVerificationNotificationController@store` | `verification.send` | `auth`, `throttle:6,1` |
| PUT | `/password` | `PasswordController@update` | `password.update` | `auth` |
| GET | `/profile/email-change` | `EmailChangeController@init` | `profile.email-change.init` | `auth` |
| POST | `/profile/email-change/send-otp` | `EmailChangeController@sendOtp` | `profile.email-change.send-otp` | `auth`, `throttle:otp` |
| POST | `/profile/email-change/verify-otp` | `EmailChangeController@verifyOtp` | `profile.email-change.verify-otp` | `auth`, `throttle:otp` |
| POST | `/logout` | Closure (audit + logout) | `logout` | `auth` |

---

## Authenticated Routes (All Roles)

All routes below require `auth` middleware (session-based).

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
| GET | `/profile/mfa/recovery-codes` | `MfaController@getRecoveryCodes` | `profile.mfa.recovery-codes` |
| POST | `/profile/mfa/recovery-codes/regenerate` | `MfaController@regenerateRecoveryCodes` | `profile.mfa.recovery-codes.regenerate` |

### Referrals (All Roles)

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/referrals` | `ReferralController@index` | `referrals.index` |
| GET | `/referrals/create` | `ReferralController@create` | `referrals.create` |
| POST | `/referrals` | `ReferralController@store` | `referrals.store` |
| GET | `/referrals/export-excel` | `ReferralController@exportExcel` | `referrals.export-excel` |
| GET | `/referrals/{referral}` | `ReferralController@show` | `referrals.show` |
| PATCH | `/referrals/{referral}/status` | `ReferralController@updateStatus` | `referrals.update-status` |
| POST | `/referrals/{referral}/milestones` | `ReferralController@addMilestone` | `referrals.milestones.store` |
| POST | `/referrals/{referral}/comments` | `ReferralController@addComment` | `referrals.comments.store` |
| POST | `/referrals/{referral}/comments/{comment}/reply` | `ReferralController@replyToComment` | `referrals.comments.reply` |
| POST | `/referrals/{referral}/attachments` | `ReferralController@addAttachment` | `referrals.attachments.store` |
| POST | `/referrals/{referral}/attachments/{attachment}/replace` | `ReferralController@replaceAttachment` | `referrals.attachments.replace` |
| GET | `/referrals/{referral}/attachments/{attachment}/download` | `ReferralController@downloadAttachment` | `referrals.attachments.download` |
| GET | `/referrals/{referral}/attachments/{versionGroupId}/versions` | `ReferralController@getAttachmentVersions` | `referrals.attachments.versions` |
| POST | `/referrals/{referral}/compliance/{compliance}/fulfill` | `ReferralController@fulfillCompliance` | `referrals.compliance.fulfill` |

### Reports

| Method | URI | Controller | Name | Middleware |
|--------|-----|-----------|------|------------|
| GET | `/reports` | `ReportsController@index` | `reports.index` | `throttle:60,1` |
| GET | `/reports/export-pdf` | `ReportsController@exportPdf` | `reports.export-pdf` | |
| GET | `/reports/export-excel` | `ReportsController@exportExcel` | `reports.export-excel` | |

### Notifications

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/notifications` | `NotificationController@index` | `notifications.index` |
| GET | `/notifications/unread-count` | `NotificationController@unreadCount` | `notifications.unread-count` |
| PATCH | `/notifications/{id}/read` | `NotificationController@markAsRead` | `notifications.mark-as-read` |
| PATCH | `/notifications/mark-all-read` | `NotificationController@markAllAsRead` | `notifications.mark-all-read` |
| GET | `/notifications/page` | Closure → `Notifications/Index` | `notifications.page` |

### Onboarding

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

### Session-Authenticated API

| Method | URI | Controller | Middleware |
|--------|-----|-----------|------------|
| GET | `/api/clients` | `ClientSelectController@search` | `auth`, `verified`, `throttle:api-global` |
| GET | `/api/clients/{client}` | `ClientSelectController@show` | `auth`, `verified`, `throttle:api-global` |

---

## Role-Gated Routes

### Case category input and filters

For case create, update, and save-draft mutations listed below, `category_ids` is the canonical input: an array of distinct UUIDs identifying active categories, synchronized to the `case_category` pivot. The deprecated scalar `category_id` remains a compatibility input for single-category clients and is converted to a one-element assignment. When category input is supplied, provide one field or the other, never both; sending both is invalid even if one is null or empty. The publish endpoint does not accept category input; it consumes and validates the assignments already stored for the case.

For mutation inputs, an omitted category field means “do not change” on update/save-draft. Draft creation may omit categories, and draft save/update may omit them; a null or empty scalar, or a null/empty `category_ids` array, means no category assignment and is allowed for drafts. A non-draft create requires at least one active category; a non-draft update may omit category fields to retain its assignments, but cannot clear them. Category IDs must be valid UUIDs and active; malformed, duplicate, or inactive IDs are rejected. Publishing consumes the stored pivot assignments and requires at least one active category.

The pivot is canonical. `cases.category_id` is a deprecated compatibility mirror containing the deterministic primary: retain the current mirror when it is still assigned; otherwise choose the active assignment by lowest `sort_order`, then `name`, then `id`. A legacy scalar mutation becomes the sole pivot assignment and its value becomes the mirror.

For `GET /cases`, `GET /cases/export-excel`, `GET /clients`, `GET /clients/export-excel`, `GET /referrals`, and `GET /referrals/export-excel`, `category_id` and `category_ids` are filters, not mutations. Either may be supplied; if both are supplied they are combined, not treated as a mutation conflict. A scalar is normalized into the selected-ID set, null/empty values mean no category filter, and `category_ids` accepts at most 50 distinct UUIDs. These are ANY filters: a result matches when at least one selected ID is present in the canonical case-category pivot or in the legacy `cases.category_id` mirror; client and referral results inherit this case-category match through their associated cases.

### CASE_MANAGER + ADMIN

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/cases` | `CaseController@index` | `cases.index` |
| GET | `/cases/create` | `CaseController@create` | `cases.create` |
| POST | `/cases` | `CaseController@store` | `cases.store` |
| GET | `/cases/drafts` | `CaseController@drafts` | `cases.drafts` |
| GET | `/cases/export-excel` | `CaseController@exportExcel` | `cases.export-excel` |
| DELETE | `/cases/{case}/destroy-draft` | `CaseController@destroyDraft` | `cases.drafts.destroy` |
| GET | `/cases/{case}/edit-draft` | `CaseController@editDraft` | `cases.edit-draft` |
| PUT | `/cases/{case}/save-draft` | `CaseController@updateDraft` | `cases.save-draft` |
| POST | `/cases/{case}/publish` | `CaseController@publish` | `cases.publish` |
| POST | `/cases/{case}/archive` | `CaseController@archive` | `cases.archive` |
| POST | `/cases/{case}/unarchive` | `CaseController@unarchive` | `cases.unarchive` |
| PATCH | `/cases/{case}` | `CaseController@update` | `cases.update` |
| POST | `/cases/{case}/toggle-status` | `CaseController@toggleStatus` | `cases.toggle-status` |
| POST | `/case-issues/quick` | `CaseIssueController@quickStore` | `case-issues.quick` |
| GET | `/stakeholders` | `StakeholderController@index` | `stakeholders.index` |
| GET | `/stakeholders/{stakeholder}` | `StakeholderController@show` | `stakeholders.show` |
| GET | `/audit-logs` | `AuditLogController@index` | `audit-logs.index` |

### CASE_MANAGER + ADMIN + AGENCY

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/cases/{case}` | `CaseController@show` | `cases.show` |
| GET | `/cases/{case}/documents` | `CaseDocumentController@index` | `cases.documents.index` |
| GET | `/cases/{case}/documents/{document}` | `CaseDocumentController@show` | `cases.documents.show` |
| GET | `/cases/{case}/documents/{document}/download` | `CaseDocumentController@download` | `cases.documents.download` |
| GET | `/clients` | `ClientController@index` | `clients.index` |
| GET | `/clients/export-excel` | `ClientController@exportExcel` | `clients.export-excel` |
| GET | `/clients/{client}` | `ClientController@show` | `clients.show` |
| POST | `/clients/{client}/avatar` | `ClientController@storeAvatar` | `clients.avatar.store` |
| DELETE | `/clients/{client}/avatar` | `ClientController@destroyAvatar` | `clients.avatar.destroy` |
| GET | `/feedbacks` | `FeedbackController@dashboard` | `feedbacks.index` |
| GET | `/feedbacks/servqual-config` | `FeedbackController@servqualConfig` | `feedbacks.servqual-config` |
| GET | `/feedbacks/export-excel` | `FeedbackController@exportExcel` | `feedbacks.export-excel` |
| GET | `/feedbacks/{feedback}` | `FeedbackController@show` | `feedbacks.show` |
| GET | `/overdue-referrals` | `OverdueReferralController@index` | `overdue-referrals.index` |
| POST | `/overdue-referrals/send-reminders` | `OverdueReferralController@sendReminders` | `overdue-referrals.send-reminders` |

### CASE_MANAGER Only

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| POST | `/cases/{case}/documents` | `CaseDocumentController@store` | `cases.documents.store` |
| DELETE | `/cases/{case}/documents/{document}` | `CaseDocumentController@destroy` | `cases.documents.destroy` |

### AGENCY Only

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/services` | `AgencyServiceController@index` | `agency.services.index` |
| POST | `/services` | `AgencyServiceController@store` | `agency.services.store` |
| PATCH | `/services/{service}` | `AgencyServiceController@update` | `agency.services.update` |
| DELETE | `/services/{service}` | `AgencyServiceController@destroy` | `agency.services.destroy` |
| GET | `/servqual-configs` | `AgencyServqualConfigController@index` | `servqual-configs.index` |
| GET | `/servqual-configs/create` | `AgencyServqualConfigController@create` | `servqual-configs.create` |
| POST | `/servqual-configs` | `AgencyServqualConfigController@store` | `servqual-configs.store` |
| GET | `/servqual-configs/{config}/edit` | `AgencyServqualConfigController@edit` | `servqual-configs.edit` |
| PATCH | `/servqual-configs/{config}` | `AgencyServqualConfigController@update` | `servqual-configs.update` |
| PATCH | `/servqual-configs/{config}/activate` | `AgencyServqualConfigController@activate` | `servqual-configs.activate` |
| POST | `/servqual-configs/{config}/assign-service` | `AgencyServqualConfigController@assignService` | `servqual-configs.assign-service` |
| POST | `/servqual-configs/{config}/unassign-service` | `AgencyServqualConfigController@unassignService` | `servqual-configs.unassign-service` |
| DELETE | `/servqual-configs/{config}` | `AgencyServqualConfigController@destroy` | `servqual-configs.destroy` |

---

## Admin Routes (ADMIN + IP Whitelist)

All prefixed with `/admin`, named with `admin.` prefix.

### User Management

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/users` | `AdminUserController@index` | `admin.users.index` |
| POST | `/admin/users` | `AdminUserController@store` | `admin.users.store` |
| GET | `/admin/users/{user}` | `AdminUserController@show` | `admin.users.show` |
| PATCH | `/admin/users/{user}` | `AdminUserController@update` | `admin.users.update` |
| DELETE | `/admin/users/{user}` | `AdminUserController@destroy` | `admin.users.destroy` |
| PATCH | `/admin/users/{user}/verify` | `AdminUserController@verify` | `admin.users.verify` |
| POST | `/admin/users/{user}/email-change/send-otp` | `AdminUserController@sendEmailChangeOtp` | `admin.users.email-change.send-otp` |
| POST | `/admin/users/{user}/email-change/verify-otp` | `AdminUserController@verifyEmailChangeOtp` | `admin.users.email-change.verify-otp` |

### Agency Management

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/agencies` | `AdminAgencyController@index` | `admin.agencies.index` |
| GET | `/admin/agencies/{agency}` | `AdminAgencyController@show` | `admin.agencies.show` |
| POST | `/admin/agencies` | `AdminAgencyController@store` | `admin.agencies.store` |
| PATCH | `/admin/agencies/{agency}` | `AdminAgencyController@update` | `admin.agencies.update` |
| DELETE | `/admin/agencies/{agency}` | `AdminAgencyController@destroy` | `admin.agencies.destroy` |

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
| GET | `/admin/case-statuses` | `AdminCaseStatusController@index` | `admin.case-statuses.index` |
| POST | `/admin/case-statuses` | `AdminCaseStatusController@store` | `admin.case-statuses.store` |
| PATCH | `/admin/case-statuses/{caseStatus}` | `AdminCaseStatusController@update` | `admin.case-statuses.update` |
| DELETE | `/admin/case-statuses/{caseStatus}` | `AdminCaseStatusController@destroy` | `admin.case-statuses.destroy` |
| GET | `/admin/case-issues` | `AdminCaseIssueController@index` | `admin.case-issues.index` |
| POST | `/admin/case-issues` | `AdminCaseIssueController@store` | `admin.case-issues.store` |
| PATCH | `/admin/case-issues/{caseIssue}` | `AdminCaseIssueController@update` | `admin.case-issues.update` |
| DELETE | `/admin/case-issues/{caseIssue}` | `AdminCaseIssueController@destroy` | `admin.case-issues.destroy` |

### System Settings

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/system-settings` | `SystemSettingsController@index` | `admin.system-settings.index` |
| POST | `/admin/system-settings` | `SystemSettingsController@update` | `admin.system-settings.update` |

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

### Admin Feedback

| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/admin/feedbacks` | `AdminFeedbackController@dashboard` | `admin.feedbacks.dashboard` |

---

## Health Check

| Method | URI | Notes |
|--------|-----|-------|
| GET | `/up` | Laravel built-in health endpoint (configured in bootstrap/app.php) |

---

## Route Count Summary

| Category | Count |
|----------|-------|
| Public (unauthenticated) | 22 |
| Authentication (guest) | 13 |
| Authentication (auth) | 10 |
| Authenticated (all roles) | 37 |
| Case Manager + Admin | 17 |
| All roles (with role middleware) | 15 |
| Case Manager only | 2 |
| Agency only | 13 |
| Admin (IP whitelisted) | 35 |
| **Total** | **~164** |
