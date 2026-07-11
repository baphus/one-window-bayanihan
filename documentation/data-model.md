# Data Model

## Overview

39 tables organized across 10 functional domains. All primary keys use UUID v4. All entities with soft delete use the flag-based pattern (`is_deleted`, `deleted_at`, `deleted_by`).

## Entity Relationships

```
User ─┬── has many ──► Case (as creator)
      ├── has many ──► Referral (via agency)
      ├── has many ──► AuditLog
      ├── has many ──► Milestone
      └── has many ──► Notification

Case ─┬── has many ──► Client
      ├── has many ──► Referral
      ├── has many ──► CaseDocument
      └── has many ──► Feedback

Client ─┬── has one ──► ClientAddress
        ├── has one ──► ClientEmployment
        └── has one ──► NextOfKin

Referral ─┬── belongs to ──► Agency
          ├── has many ──► Milestone
          ├── has many ──► ReferralAttachment
          ├── has many ──► ReferralComment
          └── has many ──► ReferralComplianceRequirement

Agency ─┬── has many ──► User
        └── has many ──► Service (via pivot)

Service ─┬── has many ──► Agency (via pivot)
         └── has many ──► ServiceRequirement
```

## Domain: Case Management

### `cases` — Unified Master Case File

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `case_number` | varchar(255) | UNIQUE, system-generated |
| `tracker_number` | varchar(255) | UNIQUE, public-facing |
| `client_type` | enum | 'OFW', 'NEXT_OF_KIN' |
| `summary` | text | nullable, case narrative |
| `status` | enum | 'OPEN', 'CLOSED' |
| `user_id` | uuid | FK → users (creator) |
| `is_deleted` | boolean | Soft delete flag |
| `deleted_at` | timestamp | Soft delete timestamp |
| `deleted_by` | uuid | FK → users |

### `clients` — OFW / Next-of-Kin Profiles

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `first_name` | varchar(255) | NOT NULL |
| `last_name` | varchar(255) | NOT NULL |
| `middle_initial` | varchar(1) | nullable |
| `suffix` | varchar(255) | nullable (Jr., III) |
| `date_of_birth` | date | nullable |
| `sex` | varchar(255) | CHECK (MALE/FEMALE) |
| `contact_number` | varchar(255) | nullable |
| `case_id` | uuid | FK → cases |

### `client_addresses`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `client_id` | uuid FK → clients |
| `house_street` | varchar(255) nullable |
| `barangay` | varchar(255) nullable |
| `city_municipality` | varchar(255) nullable |
| `province` | varchar(255) nullable |
| `region` | varchar(255) nullable |
| `country` | varchar(255) nullable |

### `client_employments`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `client_id` | uuid FK → clients |
| `employer_name` | varchar(255) nullable |
| `position` | varchar(255) nullable |
| `country` | varchar(255) nullable |
| `date_hired` | date nullable |
| `date_separated` | date nullable |

### `next_of_kin`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `client_id` | uuid FK → clients |
| `full_name` | varchar(255) nullable |
| `relationship` | varchar(255) nullable |
| `full_address` | varchar(255) nullable |
| `email` | varchar(255) nullable |
| `contact_number` | varchar(255) nullable |

## Domain: Referral System

### `referrals` — Inter-Agency Referrals

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `required_services` | text | NOT NULL |
| `notes` | text | nullable |
| `status` | enum | 'PENDING','PROCESSING','COMPLETED','REJECTED','FOR COMPLIANCE' |
| `decision` | varchar(255) | CHECK (ACCEPT/REJECT) |
| `decision_reason` | text | nullable, mandatory for decisions |
| `case_id` | uuid | FK → cases |
| `agcy_id` | uuid | FK → agencies |

### `milestones` — Append-Only Progress Records

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `title` | varchar(255) | NOT NULL |
| `description` | text | nullable |
| `refr_id` | uuid | FK → referrals |
| `user_id` | uuid | FK → users (recorder) |

**⚠️ Append-only (BR-007):** No UPDATE/DELETE routes exist for milestones.

### `referral_attachments` — Versioned Documents

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `referral_id` | uuid | FK → referrals |
| `file_name` | varchar(255) | Original filename |
| `file_path` | text | Storage URL |
| `file_type` | varchar(255) | MIME type |
| `size` | bigint | Bytes |
| `user_id` | uuid | FK → users (uploader) |
| `version_group_id` | uuid | Groups file versions |
| `version_number` | int | Default 1 |

### `referral_comments` — Threaded Communication

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `referral_id` | uuid | FK → referrals |
| `user_id` | uuid | FK → users |
| `comment` | text | NOT NULL |
| `parent_id` | uuid | FK → self (nullable, for replies) |

### `referral_compliance_requirements`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `referral_id` | uuid FK → referrals |
| `name` | varchar(255) |
| `is_fulfilled` | boolean default false |

## Domain: Agency & Service Registry

### `agencies` — Partner Agencies

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | varchar(255) | NOT NULL |
| `description` | text | nullable |
| `contact_info` | varchar(255) | nullable |
| `map_link` | text | nullable |
| `is_active` | boolean | Default true |

### `services` — Service Categories

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | varchar(255) | NOT NULL |
| `description` | text | nullable |
| `processing_days` | int | CHECK (0-365), SLA target |

### `agency_service` — Pivot Table

| Column | Type |
|---|---|
| `agency_id` | uuid FK → agencies |
| `service_id` | uuid FK → services |

### `service_requirements`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `service_id` | uuid FK → services |
| `name` | varchar(255) |
| `is_required` | boolean default false |

## Domain: Auth & Users

### `users` — System Users

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | varchar(255) | NOT NULL |
| `email` | varchar(255) | UNIQUE, NOT NULL |
| `password` | varchar(255) | Bcrypt |
| `role` | varchar(255) | CASE_MANAGER, AGENCY, ADMIN |
| `agcy_id` | uuid | FK → agencies (nullable) |
| `position` | varchar(255) | nullable |
| `contact_number` | varchar(255) | nullable |
| `is_active` | boolean | Default true |
| `email_verified_at` | timestamp | nullable |

### Spatie Permission Tables

- `permissions` — Permission definitions
- `roles` — Role definitions
- `model_has_roles` — User-role mapping
- `model_has_permissions` — User-permission mapping
- `role_has_permissions` — Role-permission mapping

## Domain: Audit

### `audit_logs` — Immutable Action Log

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `action` | enum | CREATE, UPDATE, DELETE, VIEW, LOGIN, LOGOUT |
| `module` | varchar(255) | Affected module |
| `entity_id` | uuid | nullable, affected record |
| `description` | text | nullable, human-readable |
| `old_value` | jsonb | nullable, previous state |
| `new_value` | jsonb | nullable, new state |
| `user_id` | uuid | FK → users |
| `timestamp` | timestamp | Default CURRENT_TIMESTAMP |
| `ip_address` | varchar(255) | Request IP |
| `user_agent` | text | Browser info |
| `request_id` | varchar(255) | Correlation ID |

## Domain: Feedback

### `feedback` — Case Satisfaction

| Column | Type |
|---|---|
| `id` | uuid PK |
| `case_id` | uuid FK → cases |
| `agency_id` | uuid FK → agencies (nullable) |
| `service_name` | varchar(255) nullable |
| `overall_rating` | int nullable |
| `comments` | text nullable |

### `feedback_servqual_responses` — SERVQUAL Dimensions

| Column | Type |
|---|---|
| `id` | uuid PK |
| `feedback_id` | uuid FK → feedback (CASCADE) |
| `question_id` | varchar(255) |
| `question_text` | text |
| `dimension` | varchar(255) |
| `expectation` | int nullable |
| `perception` | int nullable |

### `servqual_configs` — Survey Configuration

| Column | Type |
|---|---|
| `id` | uuid PK |
| `agency_id` | uuid FK → agencies |
| `service_name` | varchar(255) |
| `questions` | json |

Unique: `(agency_id, service_name)`

## Domain: System & Configuration

### `system_settings` — Key-Value Config

| Column | Type |
|---|---|
| `id` | uuid PK |
| `key` | varchar(255) UNIQUE |
| `value` | text |

### `case_statuses` — Dynamic Statuses

| Column | Type |
|---|---|
| `id` | uuid PK |
| `name` | varchar(255) |
| `slug` | varchar(255) UNIQUE |
| `type` | varchar(255) |
| `color` | varchar(255) nullable |
| `sort_order` | int default 0 |
| `is_system` | boolean default false |
| `is_active` | boolean default true |

### `case_documents` — Case-Level Files

| Column | Type |
|---|---|
| `id` | uuid PK |
| `file_name` | varchar(255) |
| `file_path` | text |
| `file_type` | varchar(50) nullable |
| `case_id` | uuid FK → cases |
| `user_id` | uuid FK → users |

## Domain: Helpdesk

### `helpdesk_articles` — Knowledge Base

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `title` | varchar(255) | NOT NULL |
| `slug` | varchar(255) | UNIQUE |
| `content_markdown` | text | NOT NULL |
| `excerpt` | text | nullable |
| `category_id` | uuid | FK → helpdesk_categories |
| `status` | enum | 'draft', 'published' |
| `featured` | boolean | Default false |
| `visibility` | enum | 'public', 'authenticated', 'role_restricted' |
| `target_roles` | jsonb | nullable |
| `author_id` | uuid | FK → users |
| `published_at` | timestamp | nullable |

### `helpdesk_categories`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `name` | varchar(255) |
| `slug` | varchar(255) UNIQUE |
| `description` | text nullable |
| `parent_id` | uuid FK → self (hierarchical) |
| `icon` | varchar(255) nullable |
| `sort_order` | int default 0 |

### `helpdesk_tags`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `name` | varchar(255) |
| `slug` | varchar(255) UNIQUE |

### `helpdesk_article_revisions`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `article_id` | uuid FK → helpdesk_articles |
| `title` | varchar(255) |
| `content_markdown` | text |
| `edited_by` | uuid FK → users |
| `edit_notes` | varchar(255) |

### `helpdesk_article_feedback`

| Column | Type |
|---|---|
| `id` | uuid PK |
| `article_id` | uuid FK → helpdesk_articles |
| `helpful` | boolean |
| `comment` | text nullable |
| `user_id` | uuid FK → users (nullable) |

## Common Patterns

### Soft Delete (Flag-Based)

Every entity with soft deletion uses:

```php
$table->boolean('is_deleted')->default(false);
$table->timestamp('deleted_at')->nullable();
$table->uuid('deleted_by')->nullable();
```

This is **not** Laravel's `SoftDeletes` trait — it's a custom pattern that tracks who performed the deletion.

### UUID Primary Keys

All tables use UUID v4 generated by PHP (not the database):

```php
$table->uuid('id')->primary();
```

### Foreign Keys

```php
$table->foreign('col')->references('id')->on('table')->onDelete('restrict');
```

## Key Indexes

| Table | Index | Type |
|---|---|---|
| `cases` | `case_number` | UNIQUE |
| `cases` | `tracker_number` | UNIQUE |
| `referrals` | `case_id` | Foreign key |
| `referrals` | `agcy_id` | Foreign key |
| `audit_logs` | `user_id` | Foreign key |
| `audit_logs` | `module` + `action` | Composite |
| `helpdesk_articles` | `slug` | UNIQUE |
| `helpdesk_categories` | `slug` | UNIQUE |
