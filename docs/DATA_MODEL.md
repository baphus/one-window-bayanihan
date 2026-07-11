# Data Model

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Source:** `database/migrations/` (21 migration files)

## Overview

- **Database:** PostgreSQL 17 (Supabase production) / 15 (Docker local)
- **Primary Keys:** UUID v4 (via `UsesUuid` trait)
- **Soft Deletes:** Flag-based (`is_deleted`, `deleted_at`, `deleted_by`) — NOT Laravel's `SoftDeletes`
- **Timestamps:** `created_at`, `updated_at` (Laravel standard)
- **Extensions:** `pg_trgm` (trigram search), `pgcrypto` (UUID generation)
- **Row-Level Security:** Enabled on core tables via migrations

## Table Summary

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
| 15 | `cases` | Case files | Case |
| 16 | `client_addresses` | Client addresses | Case |
| 17 | `client_employments` | Client employment history | Case |
| 18 | `next_of_kin` | Client emergency contacts | Case |
| 19 | `referrals` | Referrals to agencies | Referral |
| 20 | `milestones` | Referral progress milestones | Referral |
| 21 | `referral_attachments` | Referral file attachments (versioned) | Referral |
| 22 | `referral_comments` | Referral discussion threads | Referral |
| 23 | `case_documents` | Case file uploads | Referral |
| 24 | `referral_compliance_requirements` | Compliance tracking per referral | Referral |
| 25 | `case_notifications` | Client-facing notifications | Referral |
| 26 | `feedback` | SERVQUAL feedback responses | Feedback |
| 27 | `feedback_servqual_responses` | Individual SERVQUAL question responses | Feedback |
| 28 | `servqual_configs` | Agency feedback form configurations | Feedback |
| 29 | `feedback_invitations` | Token-based feedback invitations | Feedback |
| 30 | `audit_logs` | Immutable audit trail | Monitoring |
| 31 | `email_logs` | Email delivery tracking | Monitoring |

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
| `is_active` | boolean | default: true | |
| `contact_number` | string | nullable | |
| `avatar_url` | text | nullable | Cloudinary URL |
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

### agencies

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `name` | string | NOT NULL | |
| `short` | string | nullable | Abbreviation (e.g., OWWA) |
| `slug` | string | UNIQUE, nullable | URL slug |
| `description` | text | nullable | |
| `contact_info` | string(255) | nullable | |
| `map_link` | text | nullable | Google Maps embed URL |
| `logo_url` | text | nullable | Cloudinary URL |
| `location_query` | text | nullable | Map search query |
| `is_active` | boolean | default: true | |
| `is_default` | boolean | default: false | |
| `latitude` | decimal(10,7) | nullable | |
| `longitude` | decimal(10,7) | nullable | |
| `is_deleted` | boolean | default: false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users.id | |
| `created_at` / `updated_at` | timestamp | | |

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

### clients

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `first_name` | string | NOT NULL | Encrypted (EncryptedString cast) |
| `last_name` | string | NOT NULL | Encrypted |
| `middle_initial` | string(1) | nullable | |
| `suffix` | string | nullable | |
| `date_of_birth` | date | nullable | Encrypted (EncryptedDate cast) |
| `sex` | string(10) | CHECK('MALE','FEMALE'), nullable | |
| `email` | string | nullable | Encrypted |
| `contact_number` | string | nullable | Encrypted |
| `avatar_url` | string | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### cases

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `case_number` | string | UNIQUE | Auto-generated |
| `client_type` | string(20) | NOT NULL | e.g., 'OFW', 'NOK' |
| `vulnerability_indicator` | string | nullable | |
| `nok_vulnerability_indicator` | string | nullable | |
| `tracker_number` | string | UNIQUE | Public tracking code |
| `summary` | text | nullable | |
| `status` | string(50) | default: 'OPEN' | |
| `closed_at` | timestamp | nullable | |
| `consent_given_at` | timestamp | nullable | Data consent timestamp |
| `user_id` | uuid | FK → users.id | Case manager |
| `client_id` | uuid | FK → clients.id, nullable | |
| `category_id` | uuid | FK → case_categories.id, nullable | |
| `case_issue_id` | uuid | FK → case_issues.id, nullable | |
| `draft_client_data` | jsonb | nullable | Unpublished draft data |
| `escalated_at` | timestamp | nullable | (dropped in later migration) |
| `escalation_reason` | string | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### client_addresses

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
| `position` | string | nullable | Current position |
| `last_position` | string | nullable | Previous position |
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
| `region` / `province` / `city_municipality` / `barangay` | string | nullable | |
| `street` | text | nullable | |
| `sort_order` | integer | default: 0 | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### referrals

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `required_services` | text | NOT NULL | Service names |
| `notes` | text | nullable | |
| `status` | string(50) | default: 'PENDING' | |
| `decision` | string(20) | CHECK('ACCEPT','REJECT'), nullable | |
| `decision_comment` | text | nullable | |
| `case_id` | uuid | FK → cases.id | |
| `agcy_id` | uuid | FK → agencies.id | |
| `type` | string(20) | default: 'standard' | (dropped in later migration) |
| `first_action_at` | timestamp | nullable | SLA tracking |
| `referral_assigned_at` | timestamp | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### milestones

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `title` | string | NOT NULL | |
| `description` | text | nullable | |
| `refr_id` | uuid | FK → referrals.id | |
| `user_id` | uuid | FK → users.id | Who added it |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### referral_attachments

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals.id | |
| `file_name` | string | NOT NULL | |
| `file_path` | text | NOT NULL | Supabase storage path |
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
| `file_path` | text | NOT NULL | |
| `file_type` | string(50) | nullable | |
| `size` | bigint unsigned | nullable | |
| `case_id` | uuid | FK → cases.id | |
| `user_id` | uuid | FK → users.id | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

### referral_compliance_requirements

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals.id | |
| `service_name` | string(255) | NOT NULL | |
| `requirement_name` | string(255) | NOT NULL | |
| `status` | string(20) | default: 'PENDING' | |
| `fulfilled_by` | uuid | FK → users.id, nullable | |
| `completed_at` | timestamp | nullable | |
| `is_deleted` / `deleted_at` / `deleted_by` | — | standard | |
| `created_at` / `updated_at` | timestamp | | |

**Indexes:** `(referral_id, status)`, `(status)`

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

**Indexes:** `(case_id, client_email)`, `(read_at)`

### feedback

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `case_id` | uuid | FK → cases.id | |
| `agency_id` | uuid | FK → agencies.id, nullable | |
| `referral_id` | uuid | FK → referrals.id, nullable | |
| `service_id` | uuid | FK → services.id, nullable | |
| `service_name` | string | nullable | Denormalized |
| `overall_rating` | integer | nullable | |
| `comments` | text | nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Unique:** `(case_id, agency_id, referral_id)`

### feedback_servqual_responses

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `feedback_id` | uuid | FK → feedback.id (CASCADE) | |
| `question_id` | string | NOT NULL | |
| `question_text` | text | NOT NULL | Snapshot |
| `dimension` | string | NOT NULL | SERVQUAL dimension |
| `expectation` | integer | nullable | 1-7 scale |
| `perception` | integer | nullable | 1-7 scale |
| `created_at` / `updated_at` | timestamp | | |

### servqual_configs

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `agency_id` | uuid | FK → agencies.id | |
| `service_id` | uuid | FK → services.id, nullable | |
| `name` | string | nullable | Config display name |
| `service_name` | string | NOT NULL | Legacy name field |
| `questions` | json | NOT NULL | Question definitions |
| `is_active` | boolean | default: false | |
| `activated_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Partial unique indexes:**
- `(agency_id) WHERE service_id IS NULL` — one default config per agency
- `(agency_id, service_id) WHERE service_id IS NOT NULL` — one config per agency+service

### feedback_invitations

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `case_id` | uuid | FK → cases.id | |
| `agency_id` | uuid | FK → agencies.id | |
| `referral_id` | uuid | FK → referrals.id | |
| `service_id` | uuid | FK → services.id, nullable | |
| `client_email` | string | nullable | |
| `token_prefix` | string(16) | NOT NULL | URL-safe prefix |
| `token_hash` | string(64) | NOT NULL | SHA-256 hash |
| `service_name` | string | nullable | |
| `snapshot_source` | string(32) | default: 'agency_active_form' | |
| `form_snapshot` | json | NOT NULL | Frozen form at invite time |
| `rating_labels` | json | NOT NULL | Scale labels |
| `expires_at` | timestamp | NOT NULL | |
| `submitted_at` | timestamp | nullable | |
| `used_feedback_id` | uuid | FK → feedback.id, UNIQUE, nullable | |
| `created_at` / `updated_at` | timestamp | | |

**Unique:** `(case_id, agency_id, referral_id)`

### audit_logs

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `action` | string(50) | CHECK(CREATE,UPDATE,DELETE,LOGIN,LOGOUT,ARCHIVE,UNARCHIVE,PUBLISH) | |
| `module` | string | NOT NULL | e.g., 'case', 'referral', 'auth' |
| `entity_id` | uuid | nullable | Related entity |
| `description` | text | nullable | Human-readable |
| `old_value` | jsonb | nullable | Previous state |
| `new_value` | jsonb | nullable | New state |
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

### email_logs

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | uuid | PK | |
| `to_email` | string | NOT NULL | |
| `subject` | string | NOT NULL | |
| `mailable_type` | string | NOT NULL | Laravel mailable class |
| `status` | string | NOT NULL | sent, failed, etc. |
| `job_uuid` | uuid | nullable | Queue job reference |
| `error_message` | text | nullable | |
| `sent_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

---

## Relationship Diagram

```
users ─┬── agencies (agcy_id)
       ├── cases (user_id)
       ├── milestones (user_id)
       ├── referral_comments (user_id)
       ├── referral_attachments (user_id)
       └── audit_logs (user_id)

agencies ─┬── services (agcy_id)
           ├── referrals (agcy_id)
           ├── feedback (agency_id)
           ├── servqual_configs (agency_id)
           └── feedback_invitations (agency_id)

services ──── service_requirements (service_id)

clients ─┬── cases (client_id)
          ├── client_addresses (client_id)
          ├── client_employments (client_id)
          └── next_of_kin (client_id)

cases ─┬── referrals (case_id)
       ├── case_documents (case_id)
       ├── case_notifications (case_id)
       └── feedback (case_id)

referrals ─┬── milestones (refr_id)
            ├── referral_attachments (referral_id)
            ├── referral_comments (refr_id)
            ├── referral_compliance_requirements (referral_id)
            └── feedback_invitations (referral_id)
```

## Design Patterns

### Soft Delete (Flag-based)
All business tables use `is_deleted` + `deleted_at` + `deleted_by` instead of Laravel's built-in `SoftDeletes` trait. The `SoftDeleteFlag` trait provides `scopeNotDeleted()` and auto-filters queries.

### UUID Primary Keys
All business tables use UUID v4 primary keys via the `UsesUuid` model trait. This prevents enumeration attacks and supports distributed ID generation.

### PII Encryption
Client PII fields (`first_name`, `last_name`, `email`, `contact_number`, `date_of_birth`) use Laravel's `encrypted` cast for at-rest encryption. Migration `2026_07_09_000001_encrypt_pii_fields.php` converts existing plaintext to encrypted format.

### Audit Hash Chain
Each audit log entry stores `prev_hash` — the SHA-256 hash of the previous entry — creating a tamper-evident chain. Verified via the `AuditObserver`.
