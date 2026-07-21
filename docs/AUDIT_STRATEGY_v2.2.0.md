# Audit Strategy

> **Version:** 2.2.0 | **Updated:** 2026-07-21 | **Source:** `audit_logs` migrations, `App\Enums\AuditAction`, `App\Enums\AuditModule`, `AuditObserver`, `AuditCategory`, `AuditLogController`, `AuditLogFormatter`, `config/audit.php`, `resources/js/lib/audit.jsx`
>
> Supersedes `AUDIT_STRATEGY_v2.1.0.md` (v2.1.0), retained unchanged per document versioning policy. This is a delta document — everything in v2.1.0 still holds unless restated below.

## Changelog

**2.2.0 (2026-07-21)** — standardization, redaction hardening, and readability pass (no schema change; the frozen `chainDigest()` is untouched).

- **Vocabulary standardized on enums.** `App\Enums\AuditAction` (the 10 CHECK-constraint verbs) and `App\Enums\AuditModule` (canonical module identity + legacy-alias normalization + display label + default category) are now the single source of truth. All ~13 write sites emit enum values. `AuditLog::saving` rejects any unknown action with a clear exception before it reaches the DB CHECK. A parity test asserts `AuditAction` and the `audit_logs_action_check` constraint never diverge.
- **Classification centralized.** `AuditCategory` now derives module→category from `AuditModule` and the security-action check from `AuditAction`, replacing three duplicated module/alias tables (previously copied across the category service, the formatter, and the controller). Behavior is unchanged and regression-tested.
- **Miscategorization bug fixed.** Admin email changes were logged under a stray `email` module unknown to the classifier, so they were stamped `data` instead of `security`. They now use the canonical `user` module with an explicit `security` category, matching self-service email changes.
- **LOGOUT is now recorded.** A `LogSuccessfulLogout` listener (with the same 5-second de-dup as login) writes `LOGOUT`/`auth`/`security` on the framework `Logout` event. The action and formatter branch existed but had no producer.
- **Redaction hardened and centralized.** The sensitive-value denylist moved from a hard-coded model array to `config/audit.php` `redact` (shared by the model and its tests). Coverage expanded beyond `password`/`secret`/`token` to `authorization`, `bearer`, `cookie`, `credential`, `api_key`, `private_key`, plus exact keys `otp`, `session_id`, `signature`, `csrf`, `mfa_*`. Redaction remains recursive and case-insensitive, applied to every write path in `AuditLog::saving`. Tests now exercise the real persisted model instead of a re-implemented copy of the logic.
- **Display contract unified (readability).** `AuditLogFormatter::formatForDisplay()` output is documented and frozen. The three frontend renderers (`AuditTimeline`, `AuditLogModal`, `AuditLogTimeline`) now share one `ChangesTable`, activity-type map, and action styling from `resources/js/lib/audit.jsx`. This fixes a latent bug where two views read a non-existent camelCase `formattedModule` key and silently displayed the raw lower-cased module string; field labels are now capitalized at the display layer.
- **Config discoverability.** All `AUDIT_*` variables are documented in `.env.example`. The audited-model list moved to `config/audit.php` `observed_models` (single source; `AuditModelCoverageTest` asserts the observer is wired for every entry, so silently dropping a model fails a test).
- **Latent fatal fixed.** `AdminCaseCategoryController` reactivation called `AuditLog::create` without importing the model (would have thrown on that path); import added.

**2.1.0 (2026-07-12)** — see `AUDIT_STRATEGY_v2.1.0.md`.

## Event vocabulary (source of truth)

| Concern | Type | Values |
|---|---|---|
| Actions | `App\Enums\AuditAction` | CREATE, UPDATE, DELETE, LOGIN, LOGOUT, LOGIN_FAILED, EXPORT, ARCHIVE, UNARCHIVE, PUBLISH |
| Modules | `App\Enums\AuditModule` | canonical singular names (`case`, `client`, `referral`, `user`, `auth`, `mfa`, …) with `tryFromLegacy()` folding legacy spellings (`CASE`, `cases`, `case_files`, `email`→`user`, …) |
| Categories | `App\Services\AuditCategory` | security, data, admin, system (derived from the module + action enums) |

Writers reference `AuditAction::X->value` / `AuditModule::Y->value`. Storage remains a plain string (never an enum object) so the frozen `chainDigest()` byte-for-byte output — and therefore verification of all existing rows — is unaffected; a golden-hash test pins that serialization.

## Redaction policy

Configured in `config/audit.php` → `redact`:
- `keys` — exact field names removed to `[REDACTED]` (case-insensitive).
- `patterns` — substrings; any field whose name contains one is removed (e.g. `token` covers `access_token`, `refresh_token`).

Applied recursively by `AuditLog::redact()` from the `saving` hook, over `old_value` and `new_value`, on every write path. This is defense-in-depth on top of each model's `$auditExclude` (which drops credential/PII columns before they reach the log). No government-ID columns exist in the schema; client PII (`email`, `contact_number`, `date_of_birth`, `sex`) is already excluded on the `User` and `Client` models.

## Frontend display contract

`AuditLogFormatter::formatForDisplay()` returns `message`, `detail`, `changes[{field,fieldLabel,old,new}]`, `action` (raw verb), `module` (human label), `actor`, `timestamp` (ISO-8601), `hasChanges`. The controllers that feed the React views (`AuditLogController` index/case/referral endpoints and `ClientController::show`) attach these onto the Eloquent row and, uniformly, expose the raw `action`/`module` attributes plus `formatted_module` (= the label). So every audit surface receives: raw `action`, raw `module`, `formatted_module` label, `message`, `changes`, `actor`, `timestamp`.

The shared `resources/js/lib/audit.jsx` (`ACTION_STYLES`, `CATEGORY_LABELS`, `getActivityType`, `getEntityLabel`, `ChangesTable`, `normalizeAuditLog`) is the only place these render. Reads use `formatted_module || module`; `getActivityType`/`getEntityLabel` accept either a raw module or a label, so the views are robust to either shape. The contract has no camelCase `formatted*` keys — read the snake_case fields above.

## Standards mapping & document status

This document is an internal engineering strategy artifact (NOT a certification-framework artifact prepared for an assessor). Standards-readiness cross-reference for the controls above:

| Standard | Clause / criterion | Evidenced by |
|---|---|---|
| ISO/IEC 27001:2022 | A.8.15 Logging | append-only trigger, hash chain + `audit:verify`, context-rich entries, retention/archive lifecycle |
| ISO/IEC 27001:2022 | A.8.10 Information deletion / A.8.12 DLP | centralized recursive redaction (this version) |
| SOC 2 | CC7.2 (monitoring), CC7.3 (evaluation) | categorized, human-readable, tamper-evident, exportable-with-self-logging audit trail |
| DPTM | Protect / Accountability | PII excluded from logs; access-controlled viewer; attributable exports |
| ISO 9001:2015 | 7.5 Documented information | this versioned, changelogged strategy doc |

**Review status:** because this document asserts control effectiveness, it requires human review before it is treated as authoritative — it is **not** auto-approved. The `.env.example` additions and enum/config code are mechanical and self-verifying (covered by the test suite).

## Verification

```bash
php artisan test --filter=Audit          # backend audit suite (incl. new vocabulary, redaction, coverage, logout, failed-login tests)
php artisan audit:verify                 # hash-chain integrity
npx vitest run resources/js              # frontend (shared audit lib)
```
Full suite at time of writing: 1239 passing (5153 assertions). See v2.1.0 for the operations runbook and lifecycle, which are unchanged.
