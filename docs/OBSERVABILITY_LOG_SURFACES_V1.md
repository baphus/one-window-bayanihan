# Observability & Log Surfaces — Design Spec

**Status:** proposed — awaiting sign-off
**Relationship to `SYSTEM_SETTINGS_V1.md`:** separate project. Shares no code with the settings
registry. Sequences **before** that spec's Stage 3 (retention), because email-log retention only
means something once a page shows what is being pruned.

---

## 1. Problem

Three log stores exist and two have no UI at all:

| Store | What it holds | Admin UI |
|---|---|---|
| `audit_logs` | every write, old/new values, IP, user agent, hash chain | `/audit-logs` (exists) |
| `email_logs` | recipient, subject, status, error, provider message ID, `delivered_at` | **none** |
| `failed_jobs` | failed job payload, exception, stack trace (168h) | **none** |
| `activity_events` | case-scoped activity feed | `/activity` (exists) |

`email_logs` is the clearest gap: 12 columns, an entire Resend delivery-webhook pipeline
reconciling real delivery status (`ResendWebhookController`, `EmailEventSubscriber`), a scheduled
pruner, and `AuditModule::EMAIL_LOG` reserved for it — all with no way to look at any of it from
inside the app. "The client says they never got the link" is currently unanswerable without a
database client.

## 2. Pages

Sidebar gets a new **Monitoring** group. `Audit Logs` moves out of Administration into it.

| # | Page | Route | Data source | Notes |
|---|---|---|---|---|
| L1 | Email Logs | `/admin/email-logs` | `email_logs` | Filter status/recipient/date; detail view with full error; link to related entity where one exists |
| L2 | System Health | `/admin/system-health` | `MailTransportHealth`, scheduler heartbeat, queue backlog, `/api/readyz`, `audit:verify` | Reads values already computed; introduces no new instrumentation |
| L3 | Failed Jobs | `/admin/failed-jobs` | `failed_jobs` | Payload + stack trace; retry action |
| L4 | Security Log | `/admin/security-log` | `audit_logs` filtered by an explicit event set | See §4 |

**Order:** L2 first — it is the smallest and the most valuable (an admin seeing "scheduler last
ran 4 hours ago" before users complain). Then L1, L3, L4.

### L2 — System Health specifics

Everything it displays is already produced:

- `app/Support/MailTransportHealth.php` — mail transport check
- `routes/console.php:18-20` — scheduler heartbeat written to cache every minute
- `config/monitoring.php` — `queue_backlog`, `failed_jobs`, `scheduler_stale_seconds` thresholds
- `routes/api.php:15` — `monitoring.readyz` (token-gated; the page calls the same underlying checks
  in-process, not over HTTP)

**No new probes, no new tables.** If a value cannot be read from an existing source, it does not
appear on the page.

### Explicitly not built

- **No application-log viewer** over `storage/logs/laravel*.log`. The files contain unredacted
  context that the redaction tap may not cover; `logs:cleanup` deletes them after 7 days anyway;
  Sentry already owns error visibility. The Health page links to Sentry instead of tailing files.
- **No fourth activity timeline.** `audit_logs` and `activity_events` already overlap; adding a
  third view over the same rows is how people stop trusting all of them.

## 3. Security-event definition

`AuditModule::SECURITY` **has zero write sites** — it is an enum case and nothing else. Security
events are recorded under three different modules and would be missed by a naive
`module = 'security'` filter:

| Event | Module | Action | Where |
|---|---|---|---|
| Login | `AUTH` | `LOGIN` | `LogSuccessfulLogin:24` |
| Logout | `AUTH` | `LOGOUT` | `LogSuccessfulLogout:35`, `routes/auth.php:133` |
| Failed login | `AUTH` | `LOGIN_FAILED` | `LogFailedLogin:17` |
| Password change | `AUTH` | `CHANGE_PASSWORD` | `PasswordController:58` |
| Password reset requested / completed | `AUTH` | `REQUEST_PASSWORD_RESET`, `RESET_PASSWORD` | `PasswordResetLinkController:42`, `NewPasswordController:59` |
| Email change (self / admin) | `USER` | `UPDATE` + `category=security` | `EmailChangeController:111`, `UserService:218` |
| Password reset by admin | `USER` | `RESET_PASSWORD` | `UserService:245` |
| MFA enable / disable / regenerate | `MFA` | `ENABLE_MFA`, `DISABLE_MFA`, `REGENERATE_RECOVERY_CODES` | `MfaController:95,136,164` |
| MFA reset by admin | `MFA` | `RESET_MFA` | `UserService:530` |
| Session revocation | `SESSION` | `REVOKE_SESSIONS` | `RevokeMfaEnrolledSessions:28` (unscheduled) |

**Decision:** the security-event set is declared in `config/security_events.php` as
`[(module, action)]` pairs. The Security Log page reads from it. Historical rows are **not**
reclassified — the audit log is hash-chained (`prev_hash`, `chain_seq`) and `audit:verify` runs
weekly; rewriting rows would invalidate the chain.

**Decision:** `AuditModule::SECURITY` is deleted. It is decoration. Its removal is a forward-only
change — no stored row references it.

## 4. Prerequisite fixes (L0)

All forward-only. Prior writes get better context; existing rows are never touched.

| # | Fix | Where | Why |
|---|---|---|---|
| L0a | Failed MFA challenge writes its own `MFA / LOGIN_FAILED` row | `MfaChallengeController:61` fires `Failed` with `['mfa' => true]`; the flag is dropped | Today a wrong TOTP code is recorded as *"Failed sign-in attempt for unknown account"* under `AUTH` — indistinguishable from a wrong password, and with `new_value = {"email": null}` |
| L0b | `UserService::invalidateSessions()` writes a `SESSION` audit row with `{old_session_count, reason, actor}` | `UserService:563-578` currently writes nothing | Admin-forced session termination leaves no trace, while a manual artisan command nobody runs *does* |
| L0c | Add `context()` to admin password reset, admin MFA reset, user purge, account deletion | `UserService:245-250`, `:530-534`, `:371-375`, `ProfileController:189-193` | These are the highest-value security rows and they all have `new_value = NULL`. Compare email change (`UserService:218-224`) which correctly stores `{old, new}` |
| L0d | Delete `SecurityAuditLogger` and its test | `app/Services/SecurityAuditLogger.php`, `tests/Feature/AuditSecurityEventsTest.php` | Dead class, no production caller, and it writes description-only rows (null values) — worse than `AuditLogger` for anything it would touch |
| L0e | Delete `AuditModule::SECURITY`; add `config/security_events.php` | `app/Enums/AuditModule.php` | See §3 |

**Order:** L0a and L0c before L4. L0b, L0d, L0e ride along with any stage.

## 5. Known duplication (accepted, not fixed)

`UserInvite` is in `config/audit.php` `observed_models`, so the observer writes `CREATE`/`UPDATE`
diff rows **and** `UserService` writes purposeful `INVITE`/`RESEND_INVITE`/`CANCEL_INVITE` rows
(`UserService:97,130,150`). Both are correct and both are useful — one says what happened, the
other says what changed.

The Security Log must therefore **de-duplicate by `(entity_id, action, timestamp window)`** or it
will show each invite twice. Documented here so it is a decision, not a bug report later.

## 6. Acceptance rule

- **L1** — a failed send is findable by recipient and its `error_message` is visible without a
  database client.
- **L2** — stopping the scheduler causes the Health page to report stale within the configured
  `scheduler_stale_seconds`.
- **L3** — a failed job's exception and stack trace are readable; retry re-queues it.
- **L4** — an admin who resets another user's MFA sees exactly one row, with the actor named.
  A failed TOTP challenge appears as an MFA event, not an anonymous login failure.
- **L0** — no test asserts on `audit_logs` row ordering that would break when a new module is
  added; `audit:verify` still passes after every change.

## 7. Out of scope

- Reclassifying historical audit rows (breaks the hash chain).
- Log tailing / file browser.
- Alerting delivery (`SystemAlertNotification` has no dispatcher — own ticket).
- Changing the audit log's storage, archiving or pruning behaviour.
- Non-admin log surfaces.
