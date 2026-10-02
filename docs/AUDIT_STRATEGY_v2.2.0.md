# Audit Strategy

> **Version:** 2.2.1 | **Updated:** 2026-10-03 | **Source:** `audit_logs` migrations, `App\Enums\AuditAction`, `App\Enums\AuditModule`, `AuditObserver`, `AuditCategory`, `AuditLogController`, `AuditLogFormatter`, `config/audit.php`, `resources/js/lib/audit.jsx`
>
> Supersedes `AUDIT_STRATEGY_v2.1.0.md` (v2.1.0); its body is retained as history per document versioning policy and carries a dated erratum banner. This is a delta document — everything in v2.1.0 still holds unless restated below.

## Changelog

**2.2.1 (2026-10-03)** — verification numbers refreshed and v2.1.0's access-control
statement corrected (no schema or code change):

- **Test-count claim dated.** The "1239 passing (5153 assertions)" figure below is now
  labelled a 2026-07-21 snapshot, with the actual 2026-10-03 run recorded beside it.
- **Access control corrected (v2.1.0 §Access Control said "AGENCY: no audit access").**
  AGENCY does have a scoped audit viewer: `routes/web.php:161-163` grants
  `role:CASE_MANAGER,ADMIN,AGENCY` on `GET /audit-logs`, and
  `AuditLogController::scopedEntityIds` (`app/Http/Controllers/AuditLogController.php:291-315`)
  resolves agency rows to that agency's referrals plus their parent cases (an agency user with
  no `agcy_id` gets a sentinel UUID and therefore sees nothing, `:303-305`). Export remains
  ADMIN-only — `AuditLogController::export` calls `abort_unless($user->isAdmin(), 403)`
  (`:112`) and the viewer exposes `canExport` only for admins (`:94`).
- **Action vocabulary recounted.** `App\Enums\AuditAction` and the `audit_logs_action_check`
  constraint now hold **12** verbs, not 10: `RESTORE` and `PURGE` were appended on 2026-07-24
  (`2026_07_24_000002_add_restore_purge_to_audit_logs_action_check.php`) and are now listed in
  the event-vocabulary table below. The parity test is
  `tests/Feature/AuditVocabularyTest.php:22-41`.

**2.2.0 (2026-07-21)** — standardization, redaction hardening, and readability pass (no schema change; the frozen `chainDigest()` is untouched).

- **Vocabulary standardized on enums.** `App\Enums\AuditAction` (the 12 CHECK-constraint verbs — 10 as of 2026-07-21, plus `RESTORE` and `PURGE` added by `2026_07_24_000002_add_restore_purge_to_audit_logs_action_check.php`) and `App\Enums\AuditModule` (canonical module identity + legacy-alias normalization + display label + default category) are now the single source of truth. All ~13 write sites emit enum values. `AuditLog::saving` rejects any unknown action with a clear exception before it reaches the DB CHECK. A parity test asserts `AuditAction` and the `audit_logs_action_check` constraint never diverge (`tests/Feature/AuditVocabularyTest.php:22-41`).
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
| Actions | `App\Enums\AuditAction` | CREATE, UPDATE, DELETE, LOGIN, LOGOUT, LOGIN_FAILED, EXPORT, ARCHIVE, UNARCHIVE, PUBLISH, RESTORE, PURGE |
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

## Access control (corrects v2.1.0)

| Role | Viewer | Export |
|---|---|---|
| `ADMIN` | all rows | yes — `AuditLogController::export` is `abort_unless($user->isAdmin(), 403)` (`:112`) and the index payload sets `canExport` only for admins (`:94`) |
| `CASE_MANAGER` | own cases + those cases' referrals | no |
| `AGENCY` | **scoped, not none** — own agency's referrals, their milestones/attachments, and the parent cases | no |

The route is `role:CASE_MANAGER,ADMIN,AGENCY` on `GET /audit-logs`
(`routes/web.php:161-163`); scoping happens in `buildFilteredQuery`
(`AuditLogController.php:195`) over `scopedEntityIds` (`:291-315`). v2.1.0's
"AGENCY: no audit access (route-enforced)" is superseded by this section —
see the banner on `AUDIT_STRATEGY_v2.1.0.md`.

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

Test inventory (2026-10-03): **234 `*Test.php` files — 216 Feature, 18 Unit** — plus two
non-test support files (`tests/Feature/ReferralClientInbox/ReferralClientInboxTestCase.php`,
`tests/Feature/TrackingService/Traits/CreatesTrackingCase.php`). PHPUnit runs against
PostgreSQL database `bayanihan_test` with `DB_SSLMODE=disable` (`phpunit.xml:26-28`).

Full-suite numbers are dated, not current:

- **2026-07-21 snapshot (historical — the figure this document carried before v2.2.1):**
  1239 passing, 5153 assertions.
- **2026-10-03 run:** 1764 tests, 1763 passing, 7642 assertions (538 s). The single failure is
  pre-existing and unrelated to audit — `Tests\Feature\Console\ToolkitNamespaceTest::test_boost_provider_discovered`,
  which fails because the declared dev dependency `laravel/boost` (`composer.json:30`) is not
  present under `vendor/` in this checkout. No clean full-suite number is claimed.
- **Audit slice, 2026-10-03:** `php artisan test --filter=Audit --compact` → 341 tests,
  341 passing, 1386 assertions.

See v2.1.0 for the operations runbook and lifecycle, which are unchanged.
