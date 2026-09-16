# Data Model

> **Version:** 2.2.0 | **Updated:** 2026-09-15 | **Source:** `database/migrations/` (59 files), `app/Models/*.php` (40 models + 4 concerns), `config/audit.php`, `config/filesystems.php`, `phpunit.xml`

## Overview

- **Database:** PostgreSQL 17 (production) / 15 (local container)
- **Primary Keys:** UUID v4 (via `UsesUuid` trait)
- **Soft Deletes:** Flag-based (`is_deleted`, `deleted_at`, `deleted_by`) — NOT vanilla `SoftDeletes` usage (see Conventions)
- **Timestamps:** `created_at`, `updated_at` (Laravel standard; append-only tables vary — see notes)
- **Extensions:** `pg_trgm` (trigram search), `pgcrypto` (UUID generation)
- **Row-Level Security:** Enabled on core tables via migrations (`app.user_role` / `app.current_user_id` session context)
- **Migration convention:** New tables use UUID primary keys (`$table->uuid('id')->primary()`), foreign keys are declared on uuid-typed columns with `foreignUuid()->constrained()`, and migrations never contain seed data — reference rows belong in `database/seeders/` (see `ReferenceDataSeeder`). Guarded by `tests/Feature/Database/MigrationConventionTest.php`.
- **Test database:** PostgreSQL database `bayanihan_test` (`phpunit.xml`); `DB_SSLMODE=disable` in test config. Queue/cache/session/storage are overridden to sync/array/local, with fake S3-compatible credentials; storage fakes in tests use the `object-storage` disk.
- **File storage:** S3-compatible object storage (`object-storage` disk, default for uploads; canonical `STORAGE_*` env vars with legacy `SUPABASE_S3_*` fallbacks). A second S3-compatible disk exists for alternate object-storage targets (see `config/filesystems.php`). Audit bundles use the `audit-archives` disk, which inherits the active object-storage credentials unless overridden via `AUDIT_ARCHIVE_*`.

## Conventions

### UUID Primary Keys

All business tables use UUID v4 primary keys via `App\Models\Concerns\UsesUuid`: the `creating` hook fills an empty key with `(string) Str::uuid()`, `getIncrementing()` returns `false`, and `getKeyType()` returns `'string'`. Route model binding therefore expects string UUIDs. Pivot-style tables use composite keys instead (`referral_services` on `(referral_id, service_id)`; `agency_thread_reads` on `(user_id, case_id, peer_agency_id)`).

### Soft Delete (Flag-based)

Business models use `App\Models\Concerns\SoftDeleteFlag`, which wraps Laravel's `SoftDeletes` trait: `delete()` sets `is_deleted = true`, stamps `deleted_at`, and sets `deleted_by` to `auth()->id()` (null in CLI/queue with no auth context) via a quiet save; `restore()` clears `is_deleted` and `deleted_by`. Queries that must exclude deleted rows use the flag (`where('is_deleted', false)` / `scopeNotDeleted()`); `deleted_at` retains its standard `SoftDeletes` semantics underneath.

24 of 40 models use `SoftDeleteFlag`: Agency, AuditLog, CaseCategory, CaseDocument, CaseFile, CaseIssue, CaseStatus, Client, ClientAddress, ClientEmployment, Milestone, NextOfKin, Referral, ReferralAttachment, ReferralClientMessage, ReferralClientMessageAttachment, ReferralClientRequest, ReferralClientRequestItem, ReferralComment, ReferralMessage, ReferralServiceRequirement, Service, ServiceRequirement, User.

The remaining 16 models do **not** use the flag trait — deletion semantics for these are unverified, treat as hard-delete until confirmed: AgencyThreadRead, AuditArchive, AuditChainCheckpoint, CaseEvent, CaseNotification, EmailEvent, EmailLog, GeneratedDocument, Notification, ReferralClientAccessLink, ReferralServiceRequirement's pivot sibling `referral_services` (no model), SurveyForm, SurveyInvitation, SurveyQuestion, SurveyResponse, SystemSetting, UserInvite. (Minor-table hard deletes flagged unverified: AgencyThreadRead, Notification, EmailEvent, GeneratedDocument, SystemSetting, UserInvite.)

### Cascade Soft Deletes

`App\Models\Concerns\CascadeSoftDeletes` cascades soft-delete and restore to named relations (each child must also use `SoftDeleteFlag`); force-delete cascades are handled by the purge command, not the trait.

- `CaseFile` (`cases` table): cascades `referrals`, `documents`.
- `Referral`: cascades `comments`, `attachments`.

### Audit Hooks

- **Observed models (26)** — `config/audit.php:87-114`, the single source of truth consumed by `AppServiceProvider` to register `App\Observers\AuditObserver` (coverage enforced by `AuditModelCoverageTest`): CaseFile, Client, ClientAddress, ClientEmployment, NextOfKin, Referral, Milestone, ReferralAttachment, User, Service, ServiceRequirement, CaseCategory, CaseIssue, CaseStatus, ReferralClientRequest, ReferralClientRequestItem, ReferralClientMessage, ReferralClientAccessLink, ReferralComment, ReferralServiceRequirement, Agency, CaseDocument, SurveyForm, SurveyQuestion, SurveyInvitation, SurveyResponse.
- **Write path:** `AuditObserver` (created/updated/deleted/restored) persists via `AuditLog::create($data)`. There is **no** `AuditLog::log()` helper method — do not reference one.
- **Per-model hooks:** models define `public static array $auditExclude` (columns stripped before logging) and `getAuditModuleName()` (module label, e.g. `CaseFile` → `'case'`, `Referral` → `'referral'`).
- **Redaction:** `AuditLog::saving()` scrubs `old_value`/`new_value` recursively against the central policy (`config/audit.php:134-164`: exact keys + substring patterns, case-insensitive), sanitizes CR/LF in `description`, stamps `category` via `AuditCategory`, and fail-fasts on unknown action verbs (`App\Enums\AuditAction`).
- **Hash chain:** `AuditLog::creating()` links `prev_hash` to the previous row's `chainDigest()` (SHA-256 over frozen field list) under a transactional `pg_advisory_xact_lock`; `trg_audit_logs_append_only` blocks UPDATE/DELETE at the DB level. `chain_seq` is the insertion-order key. Pre-fix forks are baselined by `chain_verified_from`.
- **Retention/archival:** hot window 365 days (`AUDIT_RETENTION_DAYS`); older rows are archived to monthly NDJSON bundles + manifests on the `audit-archives` disk (`audit:archive`), then pruned only once archived (`audit:prune`, anchored by `audit_chain_checkpoints`). CSV export requires an explicit date range (default 30 days, capped at the retention window) and rejects result sets above 100000 rows (`AUDIT_EXPORT_MAX_ROWS`).

### PostgreSQL-Specific SQL

Reports, dashboard, and referral code leans on PostgreSQL functions — not portable to SQLite/MySQL:

- `to_char(..., 'YYYY-MM')` month bucketing (`ReportsService`, `ReportsExportService`, `AuditArchiveService` period grouping).
- `EXTRACT(EPOCH FROM ...)` age/average-day arithmetic (`ReportsService`, `ReferralService` overdue sort, `DashboardService`).
- `COUNT(*) FILTER (WHERE ...)` aggregates with age bands 0–2 / 3–5 / 6–10 / 11+ days (`DashboardService`).
- `age()` deliberately avoided on the text age column (`ReportsService` computes age groups in PHP).
- Case-number allocation is a single atomic `INSERT ... ON CONFLICT DO UPDATE ... RETURNING` against `case_number_counters` (no advisory lock, no read-modify-write).

## Table Summary

`NEW` = created after the v2.1.0 freeze (2026-07-17). `DROPPED` = removed since v2.1.0.

| # | Table | Purpose | Migration |
|---|-------|---------|-----------|
| 1 | `users` | System users (all roles) | Framework |
| 2 | `password_reset_tokens` | Password reset tokens | Framework |
| 3 | `sessions` | Database sessions | Framework |
| 4 | `cache` / `cache_locks` | Database cache | Framework |
| 5 | `jobs` / `job_batches` / `failed_jobs` | Queue system | Framework |
| 6 | `notifications` | Laravel notifications | Framework |
| 7 | `agencies` | Partner agencies | Core Reference |
| 8 | `services` | Agency services catalog | Core Reference |
| 9 | `service_requirements` | Documents needed per service | Core Reference |
| 10 | `case_statuses` | Case/referral status definitions | Core Reference |
| 11 | `case_categories` | Case classification categories | Core Reference |
| 12 | `case_issues` | Case issue types | Core Reference |
| 13 | `system_settings` | Key-value system config | Core Reference |
| 14 | `clients` | OFW client profiles | Case |
| 15 | `cases` | Case files (model: `CaseFile`) | Case |
| 16 | `client_addresses` | Client addresses (names, not codes) | Case |
| 17 | `client_employments` | Client employment history | Case |
| 18 | `next_of_kin` | Client emergency contacts | Case |
| 19 | `case_category` | Canonical case-to-category assignments | `2026_07_17_000001` (landed; was pending in v2.1.0) |
| 20 | `referrals` | Referrals to agencies | Referral |
| 21 | `milestones` | Referral progress milestones | Referral |
| 22 | `referral_attachments` | Referral file attachments (versioned) | Referral |
| 23 | `referral_comments` | Referral discussion threads | Referral |
| 24 | `case_documents` | Case/referral file uploads | Referral |
| 25 | `case_notifications` | Client-facing notifications | Referral |
| 26 | ~~`referral_compliance_requirements`~~ | **DROPPED** `2026_07_18_000001` (replaced by JSON, then by rows below) | — |
| 27 | `referral_services` | **NEW** Referral↔service pivot (composite PK) | `2026_07_28_000003` |
| 28 | `referral_service_requirements` | **NEW** Per-referral requirement snapshots | `2026_07_28_000004` |
| 29 | `referral_client_requests` | **NEW** Client information/document requests | `2026_07_19_000002` |
| 30 | `referral_client_request_items` | **NEW** Line items of a client request | `2026_07_19_000002` |
| 31 | `referral_client_access_links` | **NEW** Token-hashed client access links | `2026_07_19_000002` |
| 32 | `referral_client_messages` | **NEW** Agency↔client inbox messages | `2026_07_19_000002` |
| 33 | `referral_client_message_attachments` | **NEW** Inbox message attachments | `2026_08_13_000002` |
| 34 | `referral_messages` | **NEW** Agency-to-agency message threads | `2026_09_08_000001` |
| 35 | `agency_thread_reads` | **NEW** Per-user agency thread read markers (composite PK) | `2026_09_08_000001` |
| 36 | ~~`feedback`~~ | **DROPPED** `2026_09_16_000002` (Gen 1 SERVQUAL stack retired; live feedback runs on `survey_*` below) | — |
| 37 | ~~`feedback_servqual_responses`~~ | **DROPPED** `2026_09_16_000002` | — |
| 38 | ~~`servqual_configs`~~ | **DROPPED** `2026_09_16_000002` | — |
| 39 | ~~`feedback_invitations`~~ | **DROPPED** `2026_09_16_000002` | — |
| 40 | `survey_forms` | **NEW** Survey forms (one active per agency) | `2026_07_14_000001` |
| 41 | `survey_questions` | **NEW** Survey form questions | `2026_07_14_000001` |
| 42 | `survey_invitations` | **NEW** Hashed-token survey invitations | `2026_07_14_000001` + `000002` |
| 43 | `survey_responses` | **NEW** Survey answers | `2026_07_14_000001` |
| 44 | `case_events` | **NEW** Append-only client-facing case history | `2026_07_12_000001` |
| 45 | `audit_logs` | Immutable audit trail | Monitoring |
| 46 | `audit_archives` | **NEW** Finalized archive bundle registry | `2026_07_12_000002` |
| 47 | `audit_chain_checkpoints` | **NEW** Post-prune chain anchors | `2026_07_12_000002` |
| 48 | `email_logs` | Email delivery tracking (+ provider columns) | Monitoring + `2026_07_27_000003` |
| 49 | `email_events` | **NEW** Append-only provider delivery events | `2026_07_27_000003` |
| 50 | `case_number_counters` | **NEW** Monthly (`YYYYMM`) case-number allocation | `2026_07_27_000001` → monthly `2026_07_28_000001` |
| 51 | `generated_documents` | **NEW** Async export/report job records | `2026_07_24_051214` |
| 52 | `user_invites` | **NEW** Staff invitation tokens | `2026_07_20_000001` |
| 53 | ~~`chatbot_embeddings`~~ | **DROPPED** `2026_09_16_000001` (retired vector corpus; no Eloquent model was ever used — chatbot uses the file-based helpdesk corpus) | — |

Dropped before v2.1.0 (noted for migration archaeology, no sections below): `case_comments` (`2026_07_02`), `philippine_addresses` (`2026_07_08_000001` — addresses are stateless files now, see `docs/PSGC_ADDRESSES_v1.0.0.md`), `referrals.type` column (`2026_07_03`), `cases.escalated_at` (`2026_07_08_000002`).

---

## Detailed Schema

### users

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | NOT NULL | |
| `email` | string | UNIQUE, NOT NULL | |
| `password` | string | NOT NULL | Hashed |
| `role` | string(50) | NOT NULL | CASE_MANAGER, AGENCY, ADMIN |
| `agcy_id` | uuid | FK → agencies.id, nullable | Agency assignment |
| `client_id` | uuid | FK → clients.id, nullable | Added `2026_07_25_000002` |
| `is_active` | boolean | default: true | |
| `contact_number` | string | nullable | |
| `avatar_url` | text | nullable | Avatar storage path or absolute URL (served as signed URL via `HasAvatar`; legacy absolute URLs returned as-is) |
| `position` | string | nullable | |
| `department` | string | nullable | |
| `office_location` | string | nullable | |
| `bio` | text | nullable | |
| `emergency_contact` | text | nullable | |
| `timezone` | string | default: 'Asia/Manila' | |
| `mfa_secret` | text | nullable | Encrypted TOTP secret |
| `mfa_recovery_codes` | json | nullable | Encrypted recovery codes |
| `mfa_enabled_at` | timestamp | nullable | |
| `notifications_config` | json | nullable | Notification preferences |
| `onboarding_completed_at` | timestamp | nullable | |
| `onboarding_step` | string(100) | nullable | Current onboarding progress |
| `seen_page_guides` | json | nullable | Page guides already shown |
| `checklist_progress` | json | nullable | Getting-started checklist state |
| `profile_completed_at` | timestamp | nullable | |
| `email_verified_at` | timestamp | nullable | |
| `remember_token` | string | nullable | |
| `is_deleted` | boolean | default: false | Soft delete flag |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users.id, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Relations: `belongsTo` agency (`agcy_id`), client (`client_id`); `hasMany` cases, milestones, referral comments/attachments, audit logs.

### agencies

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | NOT NULL | |
| `short` | string | nullable | Abbreviation (e.g., OWWA) |
| `slug` | string | UNIQUE, nullable | URL slug |
| `description` | text | nullable | |
| `contact_info` | string(255) | nullable | |
| `map_link` | text | nullable | Map embed URL |
| `logo_url` | text | nullable | Logo storage path or absolute URL (signed URL via `HasAvatar`) |
| `location_query` | text | nullable | Map search query |
| `is_active` | boolean | default: true | |
| `is_default` | boolean | default: false | |
| `latitude` | decimal(10,7) | nullable | |
| `longitude` | decimal(10,7) | nullable | |
| `is_deleted` | boolean | default: false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users.id | |
| `created_at` / `updated_at` | timestamp | | |

Relations: `hasMany` users (`agcy_id`), referrals (`agcy_id`), services, survey forms, survey invitations, feedback, legacy servqual configs/feedback invitations.

### services

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | NOT NULL | |
| `description` | text | nullable | |
| `agcy_id` | uuid | FK → agencies.id, nullable | |
| `processing_days` | integer | CHECK(0–365), nullable | Expected SLA days |
| `is_deleted` | boolean | default: false | |
| `deleted_at` / `deleted_by` | timestamp/uuid | nullable | |
| `created_at` / `updated_at` | timestamp | | |

Relations: `belongsTo` agency; `hasMany` requirements (`service_requirements.service_id`); `belongsToMany` referrals via `referral_services`.

### service_requirements

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | NOT NULL | Document name |
| `description` | text | nullable | |
| `is_required` | boolean | NOT NULL | |
| `service_id` | uuid | FK → services.id | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### case_statuses

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | NOT NULL | Display name |
| `slug` | string | UNIQUE | Machine name |
| `type` | string | NOT NULL | 'case' or 'referral' |
| `color` | string(7) | nullable | Hex color code |
| `sort_order` | integer | default: 0 | |
| `is_system` | boolean | default: false | Cannot be deleted |
| `is_active` | boolean | default: true | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |

**Seeded system statuses:**
- Case: OPEN, CLOSED
- Referral: PENDING, PROCESSING, FOR_COMPLIANCE, COMPLETED, REJECTED

### case_categories

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | UNIQUE | |
| `description` | text | nullable | |
| `color` | string(7) | nullable | |
| `sort_order` | integer | default: 0 | |
| `is_active` | boolean | default: true | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |

Relations: `belongsToMany` cases via `case_category` pivot.

### case_issues

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | UNIQUE | |
| `description` | text | nullable | |
| `sort_order` | integer | default: 0 | |
| `is_active` | boolean | default: true | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |

### system_settings

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `key` | string | PK | Setting identifier |
| `category` | string | nullable | |
| `value` | text | nullable | |
| `description` | text | nullable | |
| `created_at` / `updated_at` | timestamp | | |

No `SoftDeleteFlag` (deletion semantics unverified).

### clients

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `first_name` | string | NOT NULL | Encrypted (EncryptedString cast) |
| `last_name` | string | NOT NULL | Encrypted |
| `middle_name` | string | nullable | Renamed back from `middle_initial` (`2026_08_13_000001`; was `middle_name` → `middle_initial` on 07-03) |
| `suffix` | string | nullable | |
| `date_of_birth` | date | nullable | Encrypted (EncryptedDate cast) |
| `sex` | string(10) | CHECK('MALE','FEMALE'), nullable | |
| `email` | string | nullable | Encrypted |
| `contact_number` | string | nullable | Encrypted |
| `avatar_url` | string | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

Relations: `hasMany` cases (`client_id`), addresses, employments, next of kin.

### cases

Model: `CaseFile` (`$table = 'cases'`). Cascade soft-deletes `referrals`, `documents`.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `case_number` | string | UNIQUE | Allocated from `case_number_counters` (`OWB-{YYYYMM}-{NNNNN}`) |
| `client_type` | string(20) | NOT NULL | `OFW` or `NEXT_OF_KIN` |
| `vulnerability_indicator` | string | nullable | |
| `nok_vulnerability_indicator` | string | nullable | |
| `tracker_number` | string | UNIQUE | Public tracking code |
| `summary` | text | nullable | |
| `status` | string(50) | default: 'OPEN' | |
| `closed_at` | timestamp | nullable | |
| `consent_given_at` | timestamp | nullable | Data consent timestamp |
| `user_id` | uuid | FK → users.id, nullable | Case manager (nullable since `2026_07_25_000003`) |
| `client_id` | uuid | FK → clients.id, nullable | |
| `category_id` | uuid | FK → case_categories.id, nullable | **Deprecated compatibility mirror** of the deterministic primary category; not the canonical assignment store |
| `case_issue_id` | uuid | FK → case_issues.id, nullable | |
| `draft_client_data` | jsonb | nullable | Unpublished draft data |
| `deletion_reason` | string | nullable | Added `2026_07_24_000001` |
| `source` | string | nullable | `internal` / `self_filed` (added `2026_07_25_000001`) |
| `intake_reviewed_by` | uuid | FK → users.id, nullable | Added `2026_07_25_000001` |
| `escalated_at` | — | **dropped** `2026_07_08_000002` | |
| `escalation_reason` | string | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

Relations: `belongsTo` client, user (case manager), reviewer (`intake_reviewed_by`), category (mirror), caseIssue; `belongsToMany` categories via `case_category`; `hasMany` referrals, documents (`case_documents`), caseEvents.

### case_category

The `case_category` pivot is the canonical source of case category assignments (landed; no longer pending). A case may have multiple rows/categories; `cases.category_id` remains only as a compatibility mirror for older consumers and represents one deterministic primary category.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK, default `gen_random_uuid()` | Pivot row identifier |
| `case_id` | uuid | FK → cases.id, `ON DELETE CASCADE` | |
| `case_category_id` | uuid | FK → case_categories.id, `ON DELETE RESTRICT` | |
| `created_at` / `updated_at` | timestamp | | |

The pair (`case_id`, `case_category_id`) is UNIQUE, with indexes on both foreign keys. The migration backfills one pivot row for every existing case whose legacy `category_id` is non-null. It does not remove or rewrite `cases.category_id`.

#### Category assignment and compatibility rules

- Writes accept either `category_ids` (the canonical multi-category input) or the legacy scalar `category_id`, never both. IDs must be UUIDs, distinct, and refer to active categories. Drafts may omit categories; publishing requires at least one active category.
- `category_ids` is synchronized to the pivot. The mirror is retained in `cases.category_id` and is selected deterministically: preserve the current mirror when it remains assigned; otherwise choose the active category with lowest `sort_order`, then lowest `name`, then lowest `id`. A legacy scalar `category_id` write is treated as a single-category assignment and becomes the mirror.
- Category list filters accept `category_id` or `category_ids`; `category_ids` is normalized to an array, limited to 50 distinct UUIDs, and matches a case when any selected ID is present in either the pivot or the compatibility mirror. Filter input is not a request to mutate assignments.
- Deleting a case cascades its pivot rows. Deleting a referenced category is restricted. Removing a category assignment does not delete the category; the mirror is reselected using the rule above (and a published case cannot be left without an active assignment).

### client_addresses

Address levels store resolved display **names** (PSGC codes are converted to names on write; see `docs/PSGC_ADDRESSES_v1.0.0.md`).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `client_id` | uuid | FK → clients.id | |
| `region` | string | nullable | Name (converted from code) |
| `province` | string | nullable | Name |
| `city_municipality` | string | nullable | Name |
| `barangay` | string | nullable | Name |
| `street` | text | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### client_employments

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `client_id` | uuid | FK → clients.id | |
| `employer_name` | string | nullable | |
| `position` | string | nullable | Current occupation |
| `last_position` | string | nullable | Previous occupation |
| `country` | string | nullable | Current country |
| `last_country` | string | nullable | Previous country |
| `start_date` | date | nullable | |
| `end_date` | date | nullable | |
| `date_of_arrival` | date | nullable | Return to PH |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### next_of_kin

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `client_id` | uuid | FK → clients.id | |
| `first_name` | string | nullable | |
| `last_name` | string | nullable | |
| `middle_initial` | string(1) | nullable | |
| `relationship` | string | nullable | |
| `is_primary` | boolean | default: false | |
| `phone_number` | string(50) | nullable | |
| `email` | string | nullable | |
| `full_address` | text | nullable | |
| `region` / `province` / `city_municipality` / `barangay` | string | nullable | Resolved names (see PSGC doc) |
| `street` | text | nullable | |
| `sort_order` | integer | default: 0 | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### referrals

Cascade soft-deletes `comments`, `attachments`. (The `type` column was dropped `2026_07_03_000001`; the `requirements` JSON added `2026_07_18_000001` was dropped `2026_07_28_000002` in favor of the tables below.)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `required_services` | text | NOT NULL | Service names (legacy text; structured links live in `referral_services`) |
| `notes` | text | nullable | |
| `status` | string(50) | default: 'PENDING' | |
| `decision` | string(20) | CHECK('ACCEPT','REJECT'), nullable | |
| `decision_comment` | text | nullable | |
| `case_id` | uuid | FK → cases.id | |
| `agcy_id` | uuid | FK → agencies.id | |
| `first_action_at` | timestamp | nullable | SLA tracking |
| `referral_assigned_at` | timestamp | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

Relations: `belongsTo` caseFile (`case_id`), agency (`agcy_id`); `hasMany` milestones (`refr_id`), attachments, comments (`refr_id`), messages (`referral_messages`), documents (`case_documents.referral_id`), clientRequests, serviceRequirements; `belongsToMany` services via `referral_services`.

### referral_services — NEW (2026-07-28)

Pure pivot linking referrals to catalog services. Composite PK, no `id`, no soft-delete flags.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `referral_id` | uuid | PK, FK → referrals.id `ON DELETE CASCADE` | |
| `service_id` | uuid | PK, FK → services.id `ON DELETE CASCADE` | |
| `created_at` / `updated_at` | timestamp | | |

### referral_service_requirements — NEW (2026-07-28)

Per-referral requirement snapshots copied from the service catalog at referral time.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals.id `ON DELETE CASCADE` | |
| `service_id` | uuid | FK → services.id `ON DELETE CASCADE` | |
| `name` | string | NOT NULL | |
| `description` | text | nullable | |
| `is_required` | boolean | default: true | |
| `sort_order` | integer | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(referral_id, service_id)`.

### referral_client_requests — NEW (2026-07-19)

Agency requests for client information/documents on a referral. `milestones.client_request_id` (nullable unique, `nullOnDelete`) optionally ties a milestone to its originating request.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals.id `RESTRICT` | |
| `creator_user_id` | uuid | FK → users.id `RESTRICT` | |
| `type` | string(32) | CHECK `DOCUMENT_REQUEST`/`QUESTION`/`INFORMATION_UPDATE` | |
| `title` | string | NOT NULL | |
| `instructions` | text | NOT NULL | |
| `status` | string(32) | default: 'OPEN'; CHECK `OPEN`/`IN_PROGRESS`/`CLIENT_RESPONDED`/`COMPLETED`/`CANCELLED` | |
| `due_at` | timestamp | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard (`deleted_by` `RESTRICT`) | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(referral_id, status)`. Relations: `belongsTo` referral, creator; `hasMany` items, messages, accessLinks.

### referral_client_request_items — NEW (2026-07-19)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `request_id` | uuid | FK → referral_client_requests.id `CASCADE` | |
| `label` | string | NOT NULL | |
| `sort_order` | unsigned int | default: 0 | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(request_id, sort_order)`.

### referral_client_access_links — NEW (2026-07-19)

Token-hashed, expirable, revocable client access grants per request. No `SoftDeleteFlag` (revocation via `revoked_at`, not deletion — semantics unverified beyond that).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `request_id` | uuid | FK → referral_client_requests.id `RESTRICT` | |
| `token_hash` | string(255) | UNIQUE, NOT NULL | Only the hash is stored |
| `expires_at` | timestamp | NOT NULL | |
| `revoked_at` | timestamp | nullable | |
| `revoked_by` | uuid | FK → users.id `nullOnDelete`, nullable | |
| `issued_by` | uuid | FK → users.id `RESTRICT`, NOT NULL | |
| `recipient_snapshot` | text | NOT NULL | |
| `first_used_at` / `last_used_at` | timestamp | nullable | |
| `use_count` | unsigned int | default: 0 | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(request_id, expires_at)`.

### referral_client_messages — NEW (2026-07-19)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `request_id` | uuid | FK → referral_client_requests.id `CASCADE` | |
| `body` | text | NOT NULL | Audit-excluded (`$auditExclude` includes `body`) |
| `sender_kind` | string(24) | CHECK `AGENCY_USER`/`CLIENT_ACCESS` | Exactly one of `user_id` / `access_link_id` set (CHECK) |
| `user_id` | uuid | FK → users.id `RESTRICT`, nullable | Set when `AGENCY_USER` |
| `access_link_id` | uuid | FK → referral_client_access_links.id `RESTRICT`, nullable | Set when `CLIENT_ACCESS` |
| `kind` | string(24) | default: 'MESSAGE'; CHECK `MESSAGE`/`SYSTEM`/`REVISION` | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(request_id, created_at)`.

### referral_client_message_attachments — NEW (2026-08-13)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `message_id` | uuid | FK → referral_client_messages.id `CASCADE` | |
| `file_name` / `file_path` | string | NOT NULL | Object-storage path |
| `file_type` | string(128) | nullable | |
| `size` | unsigned bigint | default: 0 | Bytes |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard (`deleted_by` `nullOnDelete`) | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(message_id)`. RLS: admin all, owning-agency all, case-manager read.

### referral_messages — NEW (2026-09-08)

Agency-to-agency coordination threads scoped to a case (anchored to a referral). Case managers/admins are deliberately excluded at the RLS layer; referral comments remain the CM↔agency channel.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals.id `CASCADE` | Anchor referral |
| `sender_user_id` | uuid | FK → users.id `RESTRICT` | |
| `body` | text | NOT NULL | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard (`deleted_by` `RESTRICT`) | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(referral_id, created_at)`. RLS `FORCE`: agency-pair policy (reader's agency must work on the same case, and reader's agency sent the message or owns the anchor referral).

### agency_thread_reads — NEW (2026-09-08)

Per-user read markers for agency threads. Composite PK, no soft-delete flags (deletion semantics unverified).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `user_id` | uuid | PK, FK → users.id `CASCADE` | Marker owner |
| `case_id` | uuid | PK, FK → cases.id `CASCADE` | |
| `peer_agency_id` | uuid | PK, FK → agencies.id `CASCADE` | The other agency in the pair |
| `last_read_at` | timestamp | NOT NULL | |

RLS `FORCE`: agency users may touch only their own markers for peer agencies working the same case.

### milestones

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `title` | string | NOT NULL | |
| `description` | text | nullable | |
| `refr_id` | uuid | FK → referrals.id | |
| `client_request_id` | uuid | FK → referral_client_requests.id `nullOnDelete`, UNIQUE, nullable | Added `2026_07_19_000002` |
| `requirements` | json | nullable | Added `2026_07_18_000001` |
| `user_id` | uuid | FK → users.id | Who added it |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### referral_attachments

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals.id | |
| `file_name` | string | NOT NULL | |
| `file_path` | text | NOT NULL | S3-compatible object storage path |
| `file_type` | string(50) | nullable | MIME type |
| `size` | bigint unsigned | nullable | Bytes |
| `user_id` | uuid | FK → users.id, nullable | Uploader |
| `replaces_id` | uuid | FK → self, nullable | Version chain |
| `version_group_id` | uuid | nullable | Groups versions |
| `is_archived` | boolean | default: false | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### referral_comments

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `refr_id` | uuid | FK → referrals.id | |
| `parent_id` | uuid | FK → self, nullable | Threading |
| `content` | text | NOT NULL | |
| `visibility` | string(50) | NOT NULL | e.g., 'all', 'internal' |
| `is_edited` | boolean | default: false | |
| `user_id` | uuid | FK → users.id | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### case_documents

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `file_name` | string | NOT NULL | |
| `file_path` | text | NOT NULL | S3-compatible object storage path |
| `file_type` | string(50) | nullable | |
| `category` | string(255) | nullable | Added `2026_07_18_000001` |
| `size` | bigint unsigned | nullable | |
| `case_id` | uuid | FK → cases.id | |
| `referral_id` | uuid | FK → referrals.id, nullable | Added `2026_07_19_000001` |
| `user_id` | uuid | FK → users.id | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### case_events — NEW (2026-07-12)

Append-only, client-facing case history. Rows are never updated or deleted — corrections are new events; content must be publishable to the client as-is (no staff names, no internals). No `SoftDeleteFlag`.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `sequence` | bigint | `GENERATED`, UNIQUE, NOT NULL | Monotonic insertion order; tiebreaker for shared `occurred_at` seconds |
| `case_id` | uuid | FK → cases.id `RESTRICT` | |
| `referral_id` | uuid | FK → referrals.id `RESTRICT`, nullable | |
| `type` | string(50) | NOT NULL | |
| `title` | string | NOT NULL | |
| `description` | text | nullable | |
| `meta` | jsonb | nullable | |
| `actor_type` | string(20) | default: 'system' | `agency` \| `case_manager` \| `system` |
| `occurred_at` | timestamp | NOT NULL | |
| `created_at` | timestamp | `useCurrent`, no `updated_at` | Append-only |

**Indexes:** `(case_id, occurred_at)`, `(referral_id)`.

### case_notifications

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `case_id` | uuid | FK → cases.id (CASCADE) | |
| `client_email` | string | NOT NULL | |
| `type` | string | NOT NULL | |
| `title` | string | NOT NULL | |
| `message` | text | NOT NULL | |
| `data` | json | nullable | |
| `related_url` | string | nullable | |
| `read_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Indexes:** `(case_id, client_email)`, `(read_at)`. No `SoftDeleteFlag` (deletion semantics unverified).

### feedback, feedback_servqual_responses, servqual_configs, feedback_invitations — DROPPED (2026-09-16)

Gen 1 SERVQUAL stack, dropped by `2026_09_16_000002` (see `docs/FEEDBACK_FEATURE_RESEARCH_2026-09-16.md`). Had no models, routes, or controllers — only export reads. Live feedback runs on `survey_forms` / `survey_questions` / `survey_invitations` / `survey_responses` below. Full pre-drop schema preserved in git history.

### survey_forms — NEW (2026-07-14)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `agency_id` | uuid | FK → agencies.id `CASCADE` | |
| `title` | string(255) | NOT NULL | |
| `description` | text | nullable | |
| `is_active` | boolean | default: false | |
| `activated_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Partial unique index:** `(agency_id) WHERE is_active = true` — one active form per agency. Relations: `hasMany` questions; `hasMany` invitations. No `SoftDeleteFlag` (deletion semantics unverified).

### survey_questions — NEW (2026-07-14)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `survey_form_id` | uuid | FK → survey_forms.id `CASCADE` | |
| `type` | string(20) | NOT NULL | `likert` / `text` / `radio` / `checkbox` / `rating` |
| `label` | text | NOT NULL | |
| `options` | jsonb | nullable | |
| `is_required` | boolean | default: true | |
| `order` | integer | default: 0 | |
| `created_at` / `updated_at` | timestamp | | |

**Index:** `(survey_form_id)`.

### survey_invitations — NEW (2026-07-14)

Tokens stored hashed since `2026_07_14_000002`.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `survey_form_id` | uuid | FK → survey_forms.id `nullOnDelete`, nullable | |
| `case_id` | uuid | FK → cases.id `CASCADE` | |
| `agency_id` | uuid | FK → agencies.id `CASCADE` | |
| `referral_id` | uuid | FK → referrals.id `CASCADE` | |
| `client_name` / `client_email` / `service_name` | string(255) | | Snapshots |
| `token` | string(64) | UNIQUE, NOT NULL | Hashed token |
| `expires_at` | timestamp | NOT NULL | |
| `submitted_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Unique:** `(case_id, agency_id, referral_id)` (`survey_invitations_case_agency_referral_unique`). **Index:** `(token)`. Relations: `hasMany` responses.

### survey_responses — NEW (2026-07-14)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `survey_invitation_id` | uuid | FK → survey_invitations.id `CASCADE` | |
| `survey_question_id` | uuid | FK → survey_questions.id `nullOnDelete`, nullable | |
| `answer` | text | nullable | |
| `selected_options` | jsonb | nullable | |
| `created_at` | timestamp | nullable, no `updated_at` | Write-once |

**Index:** `(survey_invitation_id)`.

### case_number_counters — NEW (2026-07-27, monthly since 2026-07-28)

Authoritative allocation source for `cases.case_number` (`OWB-{YYYYMM}-{NNNNN}`, e.g. `OWB-202607-00001`). Allocation is one atomic `INSERT ... ON CONFLICT DO UPDATE ... RETURNING`, so hard-deleting the year's maximum can never recycle a number into a second case. The per-year design was dropped (a six-digit period overflows smallint) rather than converted; legacy `OWB-{YYYY}-{NNNNN}` numbers keep working and cannot collide (four- vs six-digit period segment, plus the unique index).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `period` | unsigned int | PK | `YYYYMM` in the operating timezone |
| `last_number` | unsigned int | default: 0 | |
| `created_at` / `updated_at` | timestamp | | |

### audit_logs

(`category` added `2026_07_12_000001` + backfill `000005`; `chain_seq` added `000004`; action CHECK expanded `000003` and `2026_07_24_000002` to cover archive/restore/purge verbs; append-only trigger made conditional `2026_07_08_000002`.)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `action` | string(50) | DB CHECK over action verbs (CREATE, UPDATE, DELETE, LOGIN, LOGOUT, ARCHIVE, UNARCHIVE, PUBLISH, RESTORE, PURGE, …) + `AuditAction` fail-fast in `saving()` | |
| `module` | string | NOT NULL | e.g., 'case', 'referral', 'auth' |
| `category` | string | nullable | Stamped centrally by `AuditCategory` |
| `chain_seq` | bigint | — | Insertion-order key (timestamps are second-precision; UUIDs don't sort by time) |
| `entity_id` | uuid | nullable | Related entity |
| `description` | text | nullable | Human-readable (CR/LF-sanitized) |
| `old_value` | jsonb | nullable | Previous state (redacted) |
| `new_value` | jsonb | nullable | New state (redacted) |
| `user_id` | uuid | FK → users.id, nullable | |
| `ip_address` | string(45) | nullable | Request IP |
| `user_agent` | text | nullable | Browser UA |
| `request_id` | uuid | nullable | Correlation ID |
| `prev_hash` | string(64) | nullable | SHA-256 chain link |
| `timestamp` | timestamp | default: now() | |
| `is_deleted` | boolean | default: false | |
| `deleted_at` / `deleted_by` | — | nullable | |

**Indexes:**
- `(module, entity_id, timestamp DESC)` — entity lookup
- `(action, timestamp DESC)` — action filtering
- `(user_id, action, timestamp DESC)` — user activity
- `(timestamp DESC)` — chronological
- GIN `(description gin_trgm_ops)` — text search
- GIN `(old_value jsonb_path_ops)` — JSON queries
- GIN `(new_value jsonb_path_ops)` — JSON queries

**Append-only trigger:** `trg_audit_logs_append_only` prevents UPDATE/DELETE.

### audit_archives — NEW (2026-07-12)

Registry of finalized monthly archive bundles; a period is prunable only once its row exists here.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `period` | string(7) | UNIQUE, NOT NULL | `YYYY-MM` |
| `path` | string | NOT NULL | Bundle location on the `audit-archives` disk |
| `checksum` | string(64) | NOT NULL | SHA-256 of the bundle file |
| `row_count` | unsigned bigint | NOT NULL | |
| `first_entry_at` / `last_entry_at` | timestamp | NOT NULL | |
| `finalized_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

No `SoftDeleteFlag` (deletion semantics unverified).

### audit_chain_checkpoints — NEW (2026-07-12)

Chain anchors written by `audit:prune` so `audit:verify` can validate the oldest surviving row after its predecessor is deleted.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `anchor_hash` | string(64) | NOT NULL | Expected `prev_hash` of the oldest surviving row |
| `pruned_through` | timestamp | NOT NULL | |
| `bundle_manifest_path` | string | nullable | |
| `created_at` | timestamp | `useCurrent`, no `updated_at` | Append-only |

No `SoftDeleteFlag` (deletion semantics unverified).

### email_logs

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `to_email` | string | NOT NULL | |
| `subject` | string | NOT NULL | |
| `mailable_type` | string | NOT NULL | Laravel mailable class |
| `status` | string | NOT NULL | sent, failed, etc. |
| `job_uuid` | uuid | nullable | Queue job reference |
| `provider_message_id` | string | nullable, indexed | Added `2026_07_27_000003`; null for non-provider transports |
| `error_message` | text | nullable | |
| `sent_at` | timestamp | nullable | |
| `delivered_at` | timestamp | nullable | Added `2026_07_27_000003` |
| `created_at` / `updated_at` | timestamp | | |

No `SoftDeleteFlag` (prunable; events outlive their log row by design — deletion semantics otherwise unverified).

### email_events — NEW (2026-07-27)

Append-only provider delivery events, kept separate from `email_logs` so concurrent webhooks (sent/delivered/opened in close succession) cannot lose writes to a shared JSON column. `email_log_id` is `nullOnDelete` so events survive pruning.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `email_log_id` | uuid | FK → email_logs.id `nullOnDelete`, nullable | |
| `provider_message_id` | string | nullable, indexed | |
| `event_type` | string | NOT NULL, indexed | |
| `occurred_at` | timestamp | nullable | |
| `svix_id` | string | UNIQUE, NOT NULL | Provider-retry idempotency key |
| `payload` | jsonb | NOT NULL | |
| `created_at` / `updated_at` | timestamp | | |

**Indexes:** `(email_log_id)`, `(provider_message_id)`, `(event_type)`. No `SoftDeleteFlag` (deletion semantics unverified).

### generated_documents — NEW (2026-07-24)

Async export/report job records (case PDFs, system reports, CSV exports, admin full export).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id `CASCADE` | Requester |
| `case_id` | uuid | FK → cases.id `nullOnDelete`, nullable | |
| `type` | string | NOT NULL | `case_report_pdf` / `system_report_pdf` / `cases_export` / `clients_export` / `referrals_export` / `reports_export` / `admin_full_export` |
| `filename` | string | NOT NULL | |
| `path` | string | nullable | S3-compatible object storage path, null while pending |
| `file_size` | bigint | nullable | Bytes |
| `mime_type` | string | nullable | |
| `status` | string | default: 'pending' | `pending` / `completed` / `failed` |
| `error_message` | text | nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Indexes:** `(user_id)`, `(status)`. No `SoftDeleteFlag` (deletion semantics unverified).

### user_invites — NEW (2026-07-20)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `email` | string | NOT NULL | |
| `role` | string | NOT NULL | |
| `agcy_id` | uuid | FK → agencies.id `set null`, nullable | |
| `token` | string | UNIQUE, NOT NULL | |
| `expires_at` | timestamp | NOT NULL | |
| `created_by` | uuid | FK → users.id `CASCADE` | |
| `consumed_at` / `cancelled_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

No `SoftDeleteFlag` (deletion semantics unverified).

### chatbot_embeddings — DROPPED (2026-09-16)

Retired vector corpus table, dropped by `2026_09_16_000001`. It had no Eloquent model and was already retired from the query path; the chatbot uses an in-memory weighted token match over the cached parsed helpdesk corpus — pre-warm via `php artisan chatbot:index`. The original create migrations (`2026_07_26_120000`, `2026_07_26_134507`) are stubbed no-ops kept for migration identity.

---

## Relationship Diagram

```
users ─┬── agencies (agcy_id)
       ├── clients (client_id)
       ├── cases (user_id + intake_reviewed_by)
       ├── milestones (user_id)
       ├── referral_comments (user_id)
       ├── referral_attachments (user_id)
       ├── referral_messages (sender_user_id)
       ├── referral_client_requests (creator_user_id)
       ├── generated_documents (user_id)
       ├── user_invites (created_by)
       └── audit_logs (user_id)

agencies ─┬── users (agcy_id)
          ├── services (agcy_id)
          ├── referrals (agcy_id)
           ├── survey_forms (agency_id)
           ├── survey_invitations (agency_id)
           └── agency_thread_reads (peer_agency_id)

services ─┬── service_requirements (service_id)
          ├── referral_services >── referrals
          └── referral_service_requirements (service_id)

clients ─┬── users (client_id)
         ├── cases (client_id)
         ├── client_addresses (client_id)
         ├── client_employments (client_id)
         └── next_of_kin (client_id)

cases ─┬── referrals (case_id)
       ├── case_category >── case_categories
       ├── case_documents (case_id)
       ├── case_notifications (case_id)
       ├── case_events (case_id)
       ├── referral_messages via referrals
       ├── agency_thread_reads (case_id)
   └── survey_invitations (case_id)

referrals ─┬── milestones (refr_id)
           ├── referral_attachments (referral_id)
           ├── referral_comments (refr_id)
           ├── referral_messages (referral_id)
           ├── case_documents (referral_id)
           ├── referral_services >── services
           ├── referral_service_requirements (referral_id)
           ├── referral_client_requests (referral_id)
           └── survey_invitations (referral_id)

referral_client_requests ─┬── items (request_id)
                          ├── messages (request_id) ── attachments (message_id)
                          ├── access_links (request_id)
                          └── milestones (client_request_id)

survey_forms ─┬── survey_questions (survey_form_id)
              └── survey_invitations (survey_form_id) ── survey_responses

email_logs ──── email_events (email_log_id, nullOnDelete)
audit_archives / audit_chain_checkpoints ──── audit_logs (by period/prune anchor)
```

## Design Patterns

### PII Encryption

Client PII fields (`first_name`, `last_name`, `email`, `contact_number`, `date_of_birth`) use Laravel's `encrypted` cast for at-rest encryption. Migration `2026_07_09_000001_encrypt_pii_fields.php` converts existing plaintext to encrypted format.

### Audit Hash Chain

Each audit log entry stores `prev_hash` — the SHA-256 `chainDigest()` of the previous entry — creating a tamper-evident chain written under a transactional advisory lock and guarded by the append-only trigger. Verified via `audit:verify` against `audit_chain_checkpoints` after pruning. See Conventions → Audit Hooks.

### File Storage

Uploads (`referral_attachments.file_path`, `case_documents.file_path`, avatars/logos, generated documents, inbox attachments) are S3-compatible object storage paths served as signed/temporary URLs (`HasAvatar` for avatars/logos, with legacy absolute-URL passthrough). Tests fake the `object-storage` disk.
