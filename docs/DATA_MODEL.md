# Bayanihan One Window — Data Model

> **Source:** SRS v1.2 (May 19, 2026) — Database migrations, Models directory
> **Last Updated:** 2026-05-28

---

## 1. Entity Relationship Overview

The database consists of **39 tables** organized across 10 functional domains. All primary keys use UUID v4. All entities with soft delete use the flag-based pattern (`is_deleted`, `deleted_at`, `deleted_by`) rather than Laravel's `SoftDeletes` trait.

---

## 2. Core Domain: Case Management

### 2.1 `cases` — Unified Master Case File

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | Primary identifier |
| `case_number` | varchar(255) | UNIQUE, NOT NULL | System-generated case number |
| `tracker_number` | varchar(255) | UNIQUE, NOT NULL | Public-facing tracker number |
| `client_type` | enum | 'OFW', 'NEXT_OF_KIN' | Type of beneficiary |
| `summary` | text | nullable | Case narrative |
| `status` | enum | 'OPEN', 'CLOSED', default 'OPEN' | Case lifecycle status |
| `user_id` | uuid | FK → users, NOT NULL | DMW Case Manager who created the case |
| `is_deleted` | boolean | default false | Soft delete flag |
| `deleted_at` | timestamp | nullable | Soft delete timestamp |
| `deleted_by` | uuid | FK → users, nullable | Who deleted |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Relationships:**
- Belongs to `User` (creator)
- Has many `Client` (case participants)
- Has many `Referral` (inter-agency referrals)
- Has many `CaseDocument` (supporting documents)
- Has many `Feedback` (post-closure evaluation)

### 2.2 `clients` — OFW / Next-of-Kin Profiles

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `first_name` | varchar(255) | NOT NULL | |
| `last_name` | varchar(255) | NOT NULL | |
| `middle_name` | varchar(255) | nullable | |
| `suffix` | varchar(255) | nullable | e.g., Jr., III |
| `date_of_birth` | date | nullable | |
| `sex` | varchar(255) | CHECK (MALE/FEMALE), nullable | |
| `contact_number` | varchar(255) | nullable | Renamed from `contact` |
| `case_id` | uuid | FK → cases, NOT NULL | Linked case |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Relationships:**
- Belongs to `Case`
- Has one `ClientAddress`
- Has one `ClientEmployment`
- Has one `NextOfKin`

### 2.3 `client_addresses` — Client Address Data

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `client_id` | uuid | FK → clients | |
| `house_street` | varchar(255) | nullable | |
| `barangay` | varchar(255) | nullable | |
| `city_municipality` | varchar(255) | nullable | |
| `province` | varchar(255) | nullable | |
| `region` | varchar(255) | nullable | |
| `country` | varchar(255) | nullable | |

### 2.4 `client_employments` — OFW Employment History

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `client_id` | uuid | FK → clients | |
| `employer_name` | varchar(255) | nullable | |
| `position` | varchar(255) | nullable | |
| `country` | varchar(255) | nullable | OFW destination country |
| `date_hired` | date | nullable | |
| `date_separated` | date | nullable | |

### 2.5 `next_of_kin` — Emergency Contact

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `client_id` | uuid | FK → clients | |
| `full_name` | varchar(255) | nullable | |
| `relationship` | varchar(255) | nullable | |
| `full_address` | varchar(255) | nullable | Renamed from `address` |
| `email` | varchar(255) | nullable | |
| `contact_number` | varchar(255) | nullable | |

---

## 3. Domain: Referral System

### 3.1 `referrals` — Inter-Agency Referrals

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `required_services` | text | NOT NULL | Services requested |
| `notes` | text | nullable | Additional instructions |
| `status` | enum | 'PENDING','PROCESSING','COMPLETED','REJECTED','FOR COMPLIANCE', default 'PENDING' | Current status |
| `decision` | varchar(255) | CHECK (ACCEPT/REJECT), nullable | Agency decision |
| `decision_reason` | text | nullable | Mandatory justification |
| `case_id` | uuid | FK → cases, NOT NULL | Parent case |
| `agcy_id` | uuid | FK → agencies, NOT NULL | Target agency |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Relationships:**
- Belongs to `Case`
- Belongs to `Agency`
- Has many `Milestone`
- Has many `ReferralAttachment`
- Has many `ReferralComment`

### 3.2 `milestones` — Append-Only Progress Records

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `title` | varchar(255) | NOT NULL | Milestone title |
| `description` | text | nullable | Detailed update |
| `refr_id` | uuid | FK → referrals, NOT NULL | Linked referral |
| `user_id` | uuid | FK → users, NOT NULL | Who recorded |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Note:** Milestones are **append-only** (BR-007). The application MUST NOT provide update/delete routes for milestones.

### 3.3 `referral_attachments` — Referral Documents

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals | |
| `file_name` | varchar(255) | NOT NULL | Original filename |
| `file_path` | text | NOT NULL | Cloudinary URL/path (renamed from `file_url`) |
| `file_type` | varchar(255) | nullable | MIME type (renamed from `mime_type`) |
| `size` | bigint | nullable | File size in bytes |
| `user_id` | uuid | FK → users | Uploader (renamed from `uploaded_by`) |
| `version_group_id` | uuid | nullable | Groups file versions |
| `version_number` | int | default 1 | Sequential version |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

### 3.4 `referral_comments` — Inter-Agency Communication

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `referral_id` | uuid | FK → referrals | |
| `user_id` | uuid | FK → users | |
| `comment` | text | NOT NULL | |
| `parent_id` | uuid | FK → self (nullable) | For threaded replies |
| `is_deleted` | boolean | default false | |

---

## 4. Domain: Agency & Service Registry

### 4.1 `agencies` — Partner Agencies

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `name` | varchar(255) | NOT NULL | Agency name |
| `description` | text | nullable | |
| `contact_info` | varchar(255) | nullable | |
| `map_link` | text | nullable | Google Maps link |
| `is_active` | boolean | default true | For public listing |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

### 4.2 `services` — Service Categories

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `name` | varchar(255) | NOT NULL | |
| `description` | text | nullable | |
| `processing_days` | int | CHECK (0-365), nullable | SLA target |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

### 4.3 `agency_service` — Agency-Service Mapping

| Column | Type | Constraints |
|---|---|---|
| `agency_id` | uuid | FK → agencies |
| `service_id` | uuid | FK → services |

### 4.4 `service_requirements` — Service Documentation Needs

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `service_id` | uuid | FK → services |
| `name` | varchar(255) | NOT NULL |
| `is_required` | boolean | default false |

---

## 5. Domain: Auth & Users

### 5.1 `users` — System Users

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `name` | varchar(255) | NOT NULL | |
| `email` | varchar(255) | UNIQUE, NOT NULL | |
| `password` | varchar(255) | NOT NULL | Bcrypt hashed |
| `role` | varchar(255) | NOT NULL | CASE_MANAGER, AGENCY, ADMIN |
| `agcy_id` | uuid | FK → agencies, nullable | Agency affiliation |
| `position` | varchar(255) | nullable | Job title |
| `contact_number` | varchar(255) | nullable | |
| `is_active` | boolean | default true | |
| `email_verified_at` | timestamp | nullable | |
| `remember_token` | varchar(100) | nullable | |

Spatie permissions tables: `permissions`, `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

---

## 6. Domain: Audit

### 6.1 `audit_logs` — Immutable Action Log

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `action` | enum | 'CREATE','UPDATE','DELETE','VIEW','LOGIN','LOGOUT' | Performed action |
| `module` | varchar(255) | NOT NULL | Affected module |
| `entity_id` | uuid | nullable | Affected record |
| `description` | text | nullable | Human-readable summary |
| `old_value` | jsonb | nullable | Previous state |
| `new_value` | jsonb | nullable | New state |
| `user_id` | uuid | FK → users, nullable | Who performed |
| `timestamp` | timestamp | default CURRENT_TIMESTAMP | When |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |

**Key constraint:** `action IN ('CREATE','UPDATE','DELETE','VIEW','LOGIN','LOGOUT')`

---

## 7. Domain: Feedback

### 7.1 `feedback` — Case Satisfaction

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `case_id` | uuid | FK → cases |
| `agency_id` | uuid | FK → agencies, nullable |
| `service_name` | varchar(255) | nullable |
| `overall_rating` | int | nullable |
| `comments` | text | nullable |

### 7.2 `feedback_servqual_responses` — SERVQUAL Dimensions

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `feedback_id` | uuid | FK → feedback (CASCADE) |
| `question_id` | varchar(255) | |
| `question_text` | text | |
| `dimension` | varchar(255) | |
| `expectation` | int | nullable |
| `perception` | int | nullable |

### 7.3 `servqual_configs` — Survey Configuration

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `agency_id` | uuid | FK → agencies |
| `service_name` | varchar(255) | |
| `questions` | json | |

Unique constraint on `(agency_id, service_name)`.

---

## 8. Domain: System & Configuration

### 8.1 `system_settings` — Key-Value Config

| Column | Type |
|---|---|
| `id` | uuid PK |
| `key` | varchar(255) UNIQUE |
| `value` | text |

### 8.2 `case_statuses` — Dynamic Case Statuses

| Column | Type |
|---|---|
| `id` | uuid PK |
| `name` | varchar(255) NOT NULL |
| `slug` | varchar(255) UNIQUE |
| `type` | varchar(255) |
| `color` | varchar(255) nullable |
| `sort_order` | int default 0 |
| `is_system` | boolean default false |
| `is_active` | boolean default true |
| `is_deleted` | boolean default false |
| `deleted_at` | timestamp nullable |
| `deleted_by` | uuid nullable |

### 8.3 `case_documents` — Case-Level Documents

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `file_name` | varchar(255) | NOT NULL | |
| `file_path` | text | NOT NULL | Cloudinary URL |
| `file_type` | varchar(50) | nullable | |
| `case_id` | uuid | FK → cases | |
| `user_id` | uuid | FK → users | Uploader |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users, nullable | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

### 8.4 `notifications` — Database Notifications

Standard Laravel notifications table with JSON `data` column.

---

## 9. Domain: Helpdesk

### 9.1 `helpdesk_articles` — Knowledge Base Articles

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `title` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | UNIQUE, NOT NULL | |
| `content_markdown` | text | NOT NULL | |
| `excerpt` | text | nullable | |
| `category_id` | uuid | FK → helpdesk_categories | |
| `status` | enum | 'draft', 'published' | |
| `featured` | boolean | default false | |
| `visibility` | enum | 'public', 'authenticated', 'role_restricted' | |
| `target_roles` | jsonb | nullable | |
| `author_id` | uuid | FK → users | |
| `published_at` | timestamp | nullable | |
| `is_deleted` | boolean | default false | |
| `deleted_at` | timestamp | nullable | |
| `deleted_by` | uuid | FK → users | |

### 9.2 `helpdesk_categories`

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | UNIQUE, NOT NULL | |
| `description` | text | nullable | |
| `parent_id` | uuid | FK → self | |
| `icon` | varchar(255) | nullable | |
| `sort_order` | int | default 0 | |
| `is_active` | boolean | default true | |

### 9.3 `helpdesk_tags`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `name` | varchar(255) | |
| `slug` | varchar(255) | UNIQUE |

### 9.4 `helpdesk_article_revisions`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `article_id` | uuid | FK → helpdesk_articles |
| `title` | varchar(255) | |
| `content_markdown` | text | |
| `edited_by` | uuid | FK → users |
| `edit_notes` | varchar(255) | |

### 9.5 `helpdesk_article_feedback`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `article_id` | uuid | FK → helpdesk_articles |
| `helpful` | boolean | |
| `comment` | text | nullable |
| `user_id` | uuid | FK → users, nullable |

---

## 10. Common Column Patterns

All tables follow these conventions:

| Pattern | Implementation |
|---|---|
| UUID PK | `$table->uuid('id')->primary();` |
| Created/Updated | `$table->timestamps();` |
| Soft Delete (flag) | `$table->boolean('is_deleted')->default(false);` |
| | `$table->timestamp('deleted_at')->nullable();` |
| | `$table->uuid('deleted_by')->nullable();` |
| | Foreign key on `deleted_by → users.id` |
| Foreign Keys | `$table->foreign('col')->references('id')->on('table')->onDelete('restrict');` |

---

## 11. Key Indexes

| Table | Index | Type |
|---|---|---|
| `cases` | `case_number` | UNIQUE |
| `cases` | `tracker_number` | UNIQUE |
| `cases` | `user_id` | Foreign key |
| `clients` | `case_id` | Foreign key |
| `referrals` | `case_id` | Foreign key |
| `referrals` | `agcy_id` | Foreign key |
| `audit_logs` | `user_id` | Foreign key |
| `audit_logs` | `module` + `action` | Performance |
| `notifications` | `notifiable_type` + `notifiable_id` | Polymorphic |
| `helpdesk_articles` | `slug` | UNIQUE |
| `helpdesk_categories` | `slug` | UNIQUE |
| `helpdesk_tags` | `slug` | UNIQUE |
