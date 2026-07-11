# Audit Strategy

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Source:** `audit_logs` migrations, `AuditObserver`, `AuditLogController`

## Design Principles

1. **Append-only** — Database trigger prevents UPDATE/DELETE on `audit_logs`
2. **Hash chain integrity** — Each entry stores SHA-256 of previous entry (`prev_hash`)
3. **Context-rich** — IP address, user agent, request correlation ID on every entry
4. **Automatic** — `AuditObserver` captures model changes without manual intervention
5. **Queryable** — Composite indexes + GIN indexes for fast filtering and search

## Schema

```sql
CREATE TABLE audit_logs (
    id              UUID PRIMARY KEY,
    action          VARCHAR(50) NOT NULL,      -- CHECK constraint
    module          VARCHAR NOT NULL,          -- 'case', 'referral', 'auth', etc.
    entity_id       UUID,                      -- Related entity PK
    description     TEXT,                      -- Human-readable summary
    old_value       JSONB,                     -- Previous state (diff)
    new_value       JSONB,                     -- New state (diff)
    user_id         UUID REFERENCES users(id),
    ip_address      VARCHAR(45),               -- Request IP
    user_agent      TEXT,                      -- Browser UA string
    request_id      UUID,                      -- Correlation ID
    prev_hash       VARCHAR(64),               -- SHA-256 of previous entry
    timestamp       TIMESTAMP DEFAULT now(),
    is_deleted      BOOLEAN DEFAULT false,
    deleted_at      TIMESTAMP,
    deleted_by      UUID REFERENCES users(id)
);
```

### Allowed Actions (CHECK Constraint)

```
CREATE, UPDATE, DELETE, LOGIN, LOGOUT, ARCHIVE, UNARCHIVE, PUBLISH
```

### Indexes

| Index | Type | Columns | Purpose |
|-------|------|---------|---------|
| `idx_audit_logs_entity_id` | B-tree | `entity_id` | Entity lookup |
| `idx_audit_logs_entity_lookup` | B-tree | `(module, entity_id, timestamp DESC)` | Module+entity queries |
| `idx_audit_logs_action_time` | B-tree | `(action, timestamp DESC)` | Action filtering |
| `idx_audit_logs_user_action` | B-tree | `(user_id, action, timestamp DESC)` | User activity audit |
| `idx_audit_logs_timestamp` | B-tree | `(timestamp DESC)` | Chronological listing |
| `idx_audit_logs_description_trgm` | GIN (pg_trgm) | `description` | Text search (ILIKE) |
| `idx_audit_logs_old_value` | GIN (jsonb_path_ops) | `old_value` | JSON field queries |
| `idx_audit_logs_new_value` | GIN (jsonb_path_ops) | `new_value` | JSON field queries |

### Append-Only Enforcement

```sql
CREATE TRIGGER trg_audit_logs_append_only
BEFORE UPDATE OR DELETE ON audit_logs
FOR EACH ROW EXECUTE FUNCTION block_audit_log_modification();

-- Function raises EXCEPTION: 'audit_logs are append-only: cannot modify or delete rows'
```

## Implementation

### AuditObserver (Automatic Logging)

Registered on 10 models:
- `CaseFile`, `Client`, `ClientAddress`, `ClientEmployment`
- `Referral`, `Milestone`, `ReferralAttachment`
- `Agency`, `User`, `Service`

**Events captured:**
- `created` → action: CREATE
- `updated` → action: UPDATE (with old/new diff)
- `deleted` → action: DELETE
- `restored` → action: CREATE (from restoration)

**Context enrichment:**
- `user_id` from `auth()->id()`
- `ip_address` from `request()->ip()`
- `user_agent` from `request()->userAgent()`
- `request_id` from correlation ID header or generated UUID
- `prev_hash` from SHA-256 of last audit entry

**Excluded fields** (per model `$auditExclude`):
- Timestamps (`created_at`, `updated_at`)
- Internal flags where not security-relevant

### Manual Audit Logging

Used for actions that don't map to Eloquent events:

```php
// Login/Logout (in auth.php)
AuditLog::create([
    'action' => 'LOGOUT',
    'module' => 'auth',
    'entity_id' => $user->id,
    'user_id' => $user->id,
    'timestamp' => now(),
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'request_id' => $correlationId,
]);
```

### Service Layer Logging

Services call `AuditLog::log(...)` for business-significant events:
- Case publishing (PUBLISH)
- Case archiving/unarchiving (ARCHIVE/UNARCHIVE)
- Status changes that don't trigger model `updated` event separately

## Audited Events by Module

| Module | Events | Trigger |
|--------|--------|---------|
| `auth` | LOGIN, LOGOUT | Manual (auth routes) |
| `case` | CREATE, UPDATE, DELETE, PUBLISH, ARCHIVE, UNARCHIVE | Observer + Service |
| `client` | CREATE, UPDATE, DELETE | Observer |
| `client_address` | CREATE, UPDATE, DELETE | Observer |
| `client_employment` | CREATE, UPDATE, DELETE | Observer |
| `referral` | CREATE, UPDATE, DELETE | Observer |
| `milestone` | CREATE, UPDATE, DELETE | Observer |
| `referral_attachment` | CREATE, UPDATE, DELETE | Observer |
| `agency` | CREATE, UPDATE, DELETE | Observer |
| `user` | CREATE, UPDATE, DELETE | Observer |
| `service` | CREATE, UPDATE, DELETE | Observer |

## Access Control

### Who Can View Audit Logs

- **CASE_MANAGER** + **ADMIN**: Full access via `/audit-logs` page
- **AGENCY**: No access to audit log viewer
- **Public**: No access

### Audit Log Controller Features

- Paginated listing with filters (action, module, user, date range)
- Full-text search on description (uses pg_trgm ILIKE)
- JSON diff viewer for old/new values
- Export capability

## Hash Chain Verification

Each audit entry computes `prev_hash` as:

```
SHA-256(previous_entry.id + previous_entry.action + previous_entry.timestamp)
```

**Verification query** (detect tampering):
```sql
SELECT a.id, a.prev_hash,
       encode(sha256(
         (lag(a.id) OVER (ORDER BY a.timestamp))::text ||
         (lag(a.action) OVER (ORDER BY a.timestamp))::text ||
         (lag(a.timestamp) OVER (ORDER BY a.timestamp))::text
       ), 'hex') AS expected_hash
FROM audit_logs a
HAVING a.prev_hash != expected_hash;
```

If any rows return, the chain has been tampered with (should never happen due to trigger).

## Data Retention

- **Current policy:** No automatic deletion (append-only forever)
- **Storage growth:** ~1KB per entry × estimated 1000 entries/month = ~12MB/year
- **Future consideration:** Archive to cold storage after N years if needed
- **Legal requirement:** RA 10173 requires audit trails for data access; no maximum retention specified

## UI Integration

### Audit Log Page (`/audit-logs`)

- Server-side paginated table (50 per page)
- Filters: action type, module, user, date range
- Expandable rows showing old/new JSON diff
- Search by description text

### Audit Timeline (Embedded)

- Case detail pages show audit timeline for that case
- Client profiles show audit history for that client
- Referral detail shows referral-specific audit entries

## Performance Considerations

- Composite indexes ensure fast filtered queries
- GIN indexes on JSONB prevent slow full-table scans
- `timestamp DESC` ordering matches common query patterns
- Trigger is lightweight (single RAISE EXCEPTION on prohibited operations)
- Observer skips logging during database seeding (checked via `app()->runningInConsole()` when appropriate)
