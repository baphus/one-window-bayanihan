# System Settings v1 — Design Spec

**Status:** proposed — awaiting sign-off
**Scope:** the system-admin settings surface (`/admin/system-settings`)
**Out of scope:** anything not listed under "Deferred" below

---

## 1. Problem

The system-admin settings page has two fields, and both are wrong in different ways:

| Key | Reality |
|---|---|
| `referral_overdue_days` | Works, but is misfiled — it is referral configuration, not system configuration. |
| `chatbot_last_reindexed_at` | Written by a reindex action; the "knowledge" configuration the docs promise (`docs/UI_PATTERNS.md:232-238`) does not exist. `config/ai-chatbot.php` is env-only. |
| `two_factor_required` | **Contradicts the code.** Stored, shown as a toggle, read by nothing. |
| `otp_expiry_seconds` | Seeded, never read. `OtpService::TTL_MINUTES = 5` is hard-coded. |
| `feedback_enabled` | Read by `SendSurveyRequest:29`, but has no UI to change it. |

The page therefore feels bare *and* untrustworthy: two of its five keys do nothing, and one
of them claims authority over a security policy the code deliberately makes unconditional.

It is also **thin in a second way**: the settings that would answer an admin's recurring
questions ("why did this client's link expire?", "why did my export stop at 6,000 rows?", "how
long do we keep deleted cases?") are all hard-coded constants in services and commands. The gap
is not a shortage of ideas for toggles — it is that the app's real, consequential numbers are
not reachable without a deploy.

The governing principle of this spec:

> **A setting that cannot change behaviour must not appear as a setting.** If the honest
> answer is "this is not configurable", the page says so instead of growing a knob.

## 2. Corrections to earlier assumptions

**MFA is already enforced, unconditionally, in production.**
`User::isInMfaEnforcedRole()` (`app/Models/User.php:121-134`) returns true for every role when
`app()->isProduction()`. `CheckMfaEnrolled` and `EnsureMfaSession` both consult it, and
`config/mfa.php` documents that "in production enforcement cannot be switched off".

`two_factor_required` is therefore **not a missing feature to wire up**. Wiring it into
enforcement would let a settings page disable production MFA — the opposite of correct.

**Decision:** delete the toggle. Do not connect it to enforcement.

**There is no SMS/OTP provider.** `OtpService::generate()` only sends when the identifier is an
email address (`app/Services/OtpService.php:30`); a phone number silently receives nothing.
That is a feature gap, not a setting, and is deferred.

## 3. Architecture

### 3.1 Storage — unchanged

The existing `system_settings` key/value table is kept as-is. No schema change, no new table,
no package.

- `SystemSetting::getValue($key, $default)` remains the read API.
- DB value **overrides** env; env provides the default used when seeding the row.
- The page shows provenance ("from environment") when a value was never overridden, so an
  admin is never left wondering why their change had no effect.

### 3.2 Registry — the only new abstraction

Two plain PHP classes, no container magic:

**`app/Settings/Setting.php`** — immutable value object.

| Property | Meaning |
|---|---|
| `key` | `system_settings.key` |
| `type` | `bool` \| `int` \| `string` \| `email` |
| `group` | one of the UI groups in §4 |
| `default` | used when no row exists |
| `envFallback` | env var name shown as provenance, or `null` |
| `confirmRequired` | `bool` — destructive/security impact |
| `consumer` | `Class::method` that reads the value, or `null` |

**`app/Settings/SettingRegistry.php`** — a single static array of `Setting` objects.

Everything reads from it:

- the page's Inertia props,
- request validation (derived from `type`),
- the audit hook (one place, so a write cannot forget to log),
- the seeder (row defaults),
- the provenance display.

**Invariant:** a registry entry with `consumer === null` fails a test. A setting nothing reads
cannot be introduced silently.

### 3.3 Write path

```
SettingsController@update
  → validate from registry type
  → Setting::confirmRequired ? require confirmation flag
  → SystemSetting::setValue(...)
  → AuditLogger (old → new, from registry, automatically)
```

No new audit enums needed: `AuditModule::SYSTEM_SETTING` and `AuditAction::UPDATE` already exist
and are already classified admin-only.

## 4. UI

Single page `/admin/system-settings`, grouped sections. No new routes per group.

| Group | Contents |
|---|---|
| **General** | contact/support email (read-only, shows `CONTACT_RECIPIENT_EMAIL`) |
| **Referral** | overdue threshold, feedback on/off, survey invitation expiry, client link lifetime |
| **Access** | staff invite expiry |
| **Retention** | trash retention, audit retention, email-log retention, helpdesk feedback retention, generated-report retention |
| **Reports** | export row cap |
| **Security** | MFA status (read-only) + OTP expiry, OTP attempt limit |

- `/admin/system/security` is **removed**; its one remaining read-only line moves into the
  Security group. One fewer page, one fewer controller, one fewer route.
- Settings whose integration is not configured render **disabled with an explanation and a
  pointer to the env var** — never hidden, never silently inert.
- `confirmRequired` settings show a confirmation dialog naming the consequence.

## 5. The v1 slice — ten items

| # | Setting | Group | Change | Consumer |
|---|---|---|---|---|
| 1 | `two_factor_required` | Security | **Delete** toggle, row, and test. Render read-only status from `config/mfa.php` | `User::isInMfaEnforcedRole` (existing) |
| 2 | `otp_expiry_seconds` | Security | **Wire up** — replace `TTL_MINUTES` constant | `OtpService::generate` (`:21`, `:64`) |
| 3 | `referral_overdue_days` | Referral | Move from General to Referral group; no behaviour change | 6 existing call sites |
| 4 | `feedback_enabled` | Referral | Add the missing UI | `SendSurveyRequest:29` |
| 5 | `trash_retention_days` | Retention | New; DB over env | `PurgeTrashedCases:33`, `CleanupOrphanedFiles:91` |
| 6 | `audit_retention_days` | Retention | New; DB over env | `ArchiveAuditLogs:20`, `PruneAuditLogs:24` |
| 7 | `otp_max_attempts` | Security | Wire up — replace `MAX_ATTEMPTS` constant | `OtpService::verify` (`:68`) |
| 8 | `survey_invitation_expiry_days` | Referral | Wire up — replace hard-coded 30 | `SurveyInvitationService:56` |
| 9 | `client_link_lifetime_days` | Referral | Wire up — replace `LINK_LIFETIME_DAYS` | `ReferralClientAccessService:38` |
| 10 | `staff_invite_expiry_days` | Access | Wire up — replace hard-coded 7 | `UserService:88`, `:120` |

### Prerequisite fix — phantom config key

`CleanupOrphanedFiles.php:164` reads `config('reports.retention_days')`, which **does not exist**
in `config/reports.php`. The call silently falls back to the trash-retention value, so generated
reports are deleted on the *case trash* schedule.

**Fix before any Retention UI ships:** add a real `reports.retention_days` key, then expose it as
setting #11 (`report_retention_days`, Retention group, consumer `CleanupOrphanedFiles:164`).
Building the Retention group on a phantom key would repeat the exact failure this spec removes.

### Implementation notes

- **#2, #7** — `OtpService` currently uses `const`. Introduce private `ttlMinutes()` and
  `maxAttempts()` reading the settings with the existing values (5, 5) as fallbacks. `verify()`
  logic is otherwise untouched. Note `OfwRegistrationController.php:341` reads
  `OtpService::TTL_MINUTES` directly and must move to the new accessor.
- **#5, #6** — **must be read at command run time**, not bound into config at boot. A scheduled
  command that captures the value at container boot is the exact failure mode this spec exists
  to remove. Existing `--days` CLI options keep priority over the setting.
- **#8, #9, #10** — single integer at a single read site; `expires_at` is computed at creation, so
  changing the setting affects links/invites **issued after** the change only. Existing rows are
  never retroactively altered. The page must say so.
- **#1** — remove the row in a seeder/cleanup step, not just the UI. A dead row invites a future
  "fix".

### Deliberately excluded from the page

- `referral_client_request` OTP send throttle (`OfwRegistrationController:175`, `RateLimiter`
  5 hits / 300s) — abuse control; should not be admin-lowerable.
- Pagination default (`15` across ~15 controllers) — a per-user preference, already served by
  `ResolvesPerPage`.
- Dashboard/report lookback windows — analysis defaults; changing them silently shifts
  historical figures.
- Case number prefixes (`OWB-`, `OWBAP-`) — baked into existing identifiers.

## 6. Blocking prerequisite — notification preferences

**`users.notifications_config` is ignored.** Users can uncheck "email me on case assigned" and
still receive the email. `ReferralCreated:24` / `ReferralStatusChanged:27` gate mail on
`mail.default !== 'log'` but never consult the user's preference.

`ProfileUpdateRequest.php:35` validates the column as `['nullable', 'array']` — any keys, any
values. Since nothing reads it, nothing constrains it either; the first consumer would inherit
whatever garbage is stored.

**Fix:** extend the registry idea to per-user preferences.

- Declare the allowed set once (`app/Settings/NotificationPreference.php`), each with key, type
  and default (matching `ProfileController:55-60` today).
- Validate `notifications_config` against that declaration on save.
- Read through a single gate: `User::wantsNotification(string $key): bool`, consulted at every
  send site.

Rationale: an admin page that tunes notification frequency while users' own opt-outs are
cosmetic is the one combination that fails an audit.

## 7. Deliberately excluded

- `referral_client_request` OTP send throttle (`OfwRegistrationController:175`, `RateLimiter`
  5 hits / 300s) — abuse control; should not be admin-lowerable.
- Dashboard/report lookback windows — analysis defaults; changing them silently shifts
  historical figures.
- `logs:cleanup` (7 days) — operator-facing logs, not an admin concern.
- Session lifetime (`config/session.php`) — loud failure mode (mass logout) for little gain
  where the deployment already sets it. Revisit only with `confirmRequired` and a
  "this signs out all active sessions" warning.
- `failed_job_retention_hours` — deferred; `queue:prune-failed --hours=168` stays inline until
  the Failed Jobs page (separate project) exists to make it meaningful.
- `SystemAlertNotification` exists and is queued but **has no dispatcher**. An "alert the admin
  on system failure" setting needs a delivery path built first — that is a feature, not a
  setting. Own ticket.

## 8. Acceptance rule

Each item ships only with a test asserting **the setting changes behaviour** — not that the row
persisted. If a behaviour test cannot be written, the item does not ship.

Examples:
- #2 → OTP cached with the configured TTL, not 5 minutes.
- #5 → a case soft-deleted 100 days ago survives when retention is 120.
- #7 → the configured attempt count locks the OTP, not 5.
- #9 → a client link issued after the change expires at the new lifetime; one issued
  before keeps its original `expires_at`.
- #1 → no route or UI exposes a writable 2FA flag.

## 9. Staging

| Stage | Contents | User-visible? |
|---|---|---|
| 0 | Prerequisite: fix `reports.retention_days`; enforce `users.notifications_config` with the typed preference set (§6) | No |
| 1 | Registry + #1 (delete toggle) + #2, #7 (OTP) | No — de-risks everything after |
| 2 | Page re-grouping + #3, #4, #8, #9, #10; remove `/admin/system/security` | Yes |
| 3 | #5, #6, #11 (retention) | Yes |

`main` stays shippable after each stage.

## 10. Deferred — explicitly out of scope

- DB-driven roles and permissions (`config/audit_visibility.php`, route middleware and sidebar
  all read static config; this is a project, not a setting).
- SMS / phone OTP delivery.
- Any secret editable from the page: `MAIL_PASSWORD`, `GEMINI_API_KEY`, `CLOUDINARY_URL`,
  `TURNSTILE_SECRET`, `RESEND_WEBHOOK_SECRET`. The page names the env var instead.
- Notification *system* settings (frequency, digest, channels).
- Branding editor (logo, footer, address are hard-coded across blades and JSX).
- "Revert to previous value" on audit entries — audit already stores old → new; manual re-entry
  is sufficient for v1.
- Report PDF limits (`pdf_preflight_max_rows`, `pdf_appendix_rows`, `pdf_top_n`,
  `chart_max_categories`, `suppression_threshold`) — read once per export and tied to layout;
  revisit if export questions persist after `export_row_cap`.
- Pagination defaults and file-type allow-lists (both require enforcement work beyond v1).
- **Upload size limit.** `config/file-uploads.php` already reads `FILE_UPLOAD_MAX_SIZE`; exposing
  it in the page would mean reconciling the hard-coded per-context limits (20 MB configured vs
  10 MB referrals, 5 MB avatars, 2 MB profile) — several of which are deliberately lower than the
  global cap. That is a file-handling project, not a settings item.
- A "super admin" tier. All `ADMIN` users remain equal, protected by audit + confirmation.

## 11. Non-goals

No new tables. No settings package. No permissions framework. No `system_settings` schema change.
No change to how any existing role is authorised.
