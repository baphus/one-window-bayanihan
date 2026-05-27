# Bayanihan One Window — Audit Strategy

> **Source:** SRS v1.2 (May 19, 2026) — §4.10 Audit Log Management, §5.3.7 Audit Logging and Accountability
> **Last Updated:** 2026-05-28

---

## 1. Audit Philosophy

Bayanihan One Window implements an **immutable, append-only audit trail** for all security-relevant and operationally significant events. The audit system supports:

- **Accountability** — every action traced to a specific user
- **Traceability** — full history of case and referral lifecycle
- **Governance** — DMW oversight of inter-agency actions
- **Security monitoring** — detection of unauthorized access attempts
- **Incident investigation** — forensic analysis capability

---

## 2. Audit Architecture

### 2.1 Database Schema

**Table:** `audit_logs`

| Column | Type | Purpose |
|---|---|---|
| `id` | uuid PK | Unique event identifier |
| `action` | enum | CREATE, UPDATE, DELETE, VIEW, LOGIN, LOGOUT |
| `module` | varchar(255) | Affected system module (e.g., 'cases', 'referrals', 'users') |
| `entity_id` | uuid nullable | Affected record ID |
| `description` | text nullable | Human-readable summary of the event |
| `old_value` | jsonb nullable | Previous state (for UPDATE/DELETE) |
| `new_value` | jsonb nullable | New state (for CREATE/UPDATE) |
| `user_id` | uuid FK → users | Who performed the action |
| `timestamp` | timestamp | When the event occurred (default CURRENT_TIMESTAMP) |
| `is_deleted` | boolean | Soft delete flag (always false for audit records) |
| `deleted_at` | timestamp | Always null (never soft-deleted) |
| `deleted_by` | uuid | Always null |

### 2.2 Key Constraints

- `action IN ('CREATE', 'UPDATE', 'DELETE', 'VIEW', 'LOGIN', 'LOGOUT')`
- Records are INSERT-only — no application code ever updates or deletes audit records
- The `audit_logs` table itself has VIEW auditing (access to audit logs is logged)

---

## 3. Audited Events

### 3.1 Authentication Events

| Event | Action | Module | Data Captured |
|---|---|---|---|
| Successful login | LOGIN | auth | user_id, timestamp, IP (via metadata) |
| Failed login attempt | LOGIN | auth | attempted email, timestamp |
| Successful OTP verification | LOGIN | auth | user_id, timestamp |
| Failed OTP attempt | LOGIN | auth | user_id, attempt count |
| Logout | LOGOUT | auth | user_id, timestamp |
| Session timeout | LOGOUT | auth | user_id (if available) |

### 3.2 Case Events

| Event | Action | Module | Data Captured |
|---|---|---|---|
| Case created | CREATE | cases | new_value (full case data) |
| Case updated | UPDATE | cases | old_value + new_value (changed fields) |
| Case viewed | VIEW | cases | entity_id, user_id |
| Case published | UPDATE | cases | status change: DRAFT → OPEN |
| Case archived | DELETE | cases | old_value (pre-deletion state) |
| Case unarchived | UPDATE | cases | restoration data |
| Case closed | UPDATE | cases | status change: OPEN → CLOSED |

### 3.3 Referral Events

| Event | Action | Module | Data Captured |
|---|---|---|---|
| Referral created | CREATE | referrals | new_value (referral details) |
| Referral status changed | UPDATE | referrals | old_value + new_value (status transition) |
| Referral viewed | VIEW | referrals | entity_id, user_id |
| Referral accepted | UPDATE | referrals | decision: ACCEPT, decision_reason |
| Referral rejected | UPDATE | referrals | decision: REJECT, decision_reason |
| Milestone added | CREATE | milestones | new_value (milestone content) |

### 3.4 Document Events

| Event | Action | Module | Data Captured |
|---|---|---|---|
| Document uploaded | CREATE | documents | file_name, file_type, size |
| Document accessed | VIEW | documents | entity_id, user_id |
| Document replaced | UPDATE | attachments | old + new version info |

### 3.5 Administrative Events

| Event | Action | Module | Data Captured |
|---|---|---|---|
| User created | CREATE | users | new_value (user details, not password) |
| User updated | UPDATE | users | old_value + new_value |
| User deactivated | UPDATE | users | is_active: true → false |
| Agency created | CREATE | agencies | new_value |
| Agency updated | UPDATE | agencies | old_value + new_value |
| Service configured | CREATE/UPDATE | services | configuration changes |
| System setting changed | UPDATE | settings | old_value + new_value |
| Case status configured | CREATE/UPDATE | case-statuses | status configuration data |

### 3.6 Access Denial Events

| Event | Action | Module | Data Captured |
|---|---|---|---|
| 403 Forbidden | VIEW | (module) | attempted URL, user_id |
| 404 on protected route | VIEW | (module) | attempted URL (if authenticated) |
| Rate limit triggered | VIEW | auth | IP, endpoint |

---

## 4. Audit Log Implementation

### 4.1 Model Events

Audit logging is implemented via **Laravel model event observers** and **service-level logging**:

```php
// Example: Case model observer
class AuditLogObserver
{
    public function created(Case $case): void
    {
        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'cases',
            'entity_id' => $case->id,
            'description' => "Case {$case->case_number} created",
            'old_value' => null,
            'new_value' => $case->toArray(),
            'user_id' => auth()->id(),
        ]);
    }
}
```

### 4.2 VIEW Event Tracking

View tracking is implemented at the controller level using a reusable trait or service:

```php
// In CaseController@show
$case->recordView(auth()->user());
```

Or via explicit calls:

```php
AuditLog::log('VIEW', 'cases', $case->id, auth()->id());
```

View events are **deduplicated** — repeated views of the same record within a session are not logged multiple times per SRS requirement.

### 4.3 Audit Helper

```php
// app/Services/AuditLogService.php
class AuditLogService
{
    public static function log(
        string $action,
        string $module,
        ?string $entityId = null,
        ?string $description = null,
        ?array $oldValue = null,
        ?array $newValue = null,
    ): AuditLog {
        return AuditLog::create([
            'action' => $action,
            'module' => $module,
            'entity_id' => $entityId,
            'description' => $description,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'user_id' => auth()->id(),
        ]);
    }
}
```

---

## 5. Audit Log Access

| Aspect | Implementation |
|---|---|
| Access control | Only ADMIN users can access `/audit-logs` |
| Filtering | By user, date range, action type, module |
| Search | Full-text search on `description` field |
| Export | Not yet implemented (planned: CSV export) |
| View audit | Accessing audit log is itself tracked as VIEW event |

### Audit Log UI (`AuditLog/Index.jsx`)

```
┌──────────────────────────────────────────────────────────────────┐
│ Audit Logs                    [Search...]  [Action ▼] [Date ▼]  │
├────────┬──────────┬────────┬──────────┬────────┬────────┬────────┤
│ Time   │ User     │ Action │ Module   │ Entity │ Details│        │
├────────┼──────────┼────────┼──────────┼────────┼────────┼────────┤
│ 10:32  │ juan.c   │ CREATE │ cases    │ C-042  │ Case   │ [View] │
│ 10:35  │ maria.a  │ UPDATE │ referrals│ R-015  │ Accept │ [View] │
│ 10:40  │ admin    │ VIEW   │ audit-   │        │ Audit  │ [View] │
│        │          │        │ logs     │        │ access │        │
└────────┴──────────┴────────┴──────────┴────────┴────────┴────────┘
                          [1] [2] [3] ... [Next ›]
```

---

## 6. Data Retention

| Record Type | Retention Period | Action After Period |
|---|---|---|
| Audit logs (critical) | 5 years | Archive to cold storage |
| Audit logs (views) | 1 year | Purge |
| Old_value / New_value JSONB content | Same as parent record | Purge with parent |
| Failed login attempts | 90 days | Purge |

**Note:** Data retention policy (LEGAL-006) is not yet implemented as automated cleanup. These are proposed retention targets.

---

## 7. Append-Only Enforcement

| Layer | Enforcement |
|---|---|
| **Application** | No UPDATE/DELETE routes for AuditLog or Milestone models |
| **Database** | No UPDATE/DELETE permissions for application DB user on `audit_logs` table |
| **Process** | Corrections are recorded as NEW audit entries referencing the original event |
| **UI** | Audit log interface has no edit/delete controls |

---

## 8. Immutability Verification

To verify the audit log has not been tampered with:

```sql
-- Check for any UPDATE operations (should return 0 rows)
SELECT * FROM audit_logs 
WHERE created_at != updated_at 
  AND action != 'UPDATE';  -- legitimate if an UPDATE event was logged

-- Check for deletion gaps (missing sequence of IDs)
SELECT id FROM audit_logs 
WHERE is_deleted = true;  -- should return 0 rows
```

---

## 9. Audited Modules Coverage

| Module | CREATE | UPDATE | DELETE | VIEW | LOGIN | LOGOUT |
|---|---|---|---|---|---|---|
| auth | — | — | — | — | ✅ | ✅ |
| cases | ✅ | ✅ | ✅ | ✅ | — | — |
| referrals | ✅ | ✅ | — | ✅ | — | — |
| milestones | ✅ | — | — | — | — | — |
| documents | ✅ | ✅ | — | ✅ | — | — |
| users | ✅ | ✅ | — | — | — | — |
| agencies | ✅ | ✅ | — | — | — | — |
| services | ✅ | ✅ | — | — | — | — |
| settings | — | ✅ | — | — | — | — |
| case-statuses | ✅ | ✅ | ✅ | — | — | — |
| audit-logs | — | — | — | ✅ | — | — |
| feedback | ✅ | — | — | — | — | — |

---

## 10. Audit Event Examples

### Case Created

```json
{
  "action": "CREATE",
  "module": "cases",
  "entity_id": "550e8400-e29b-41d4-a716-446655440000",
  "description": "Case C-2026-0042 created for Juan Dela Cruz",
  "old_value": null,
  "new_value": {
    "case_number": "C-2026-0042",
    "tracker_number": "TRK-A7F2",
    "client_type": "OFW",
    "status": "DRAFT",
    "summary": "Repatriated from Saudi Arabia due to contract dispute..."
  },
  "user_id": "660e8400-e29b-41d4-a716-446655440001",
  "timestamp": "2026-05-28T10:32:15.000000Z"
}
```

### Referral Accepted

```json
{
  "action": "UPDATE",
  "module": "referrals",
  "entity_id": "770e8400-e29b-41d4-a716-446655440002",
  "description": "Referral R-2026-0015 accepted by OWWA",
  "old_value": {
    "status": "PENDING",
    "decision": null,
    "decision_reason": null
  },
  "new_value": {
    "status": "PROCESSING",
    "decision": "ACCEPT",
    "decision_reason": "OFW qualifies for OWWA livelihood assistance program"
  },
  "user_id": "880e8400-e29b-41d4-a716-446655440003",
  "timestamp": "2026-05-28T10:35:42.000000Z"
}
```

---

## 11. Reporting & Analysis

| Query | Purpose | SQL/Implementation |
|---|---|---|
| Events by user | Audit a specific user's actions | `AuditLog::where('user_id', $id)->orderBy('timestamp')->get()` |
| Events by case | Full history of a case | `AuditLog::where('entity_id', $caseId)->orWhere('description', 'like', "%{$caseNumber}%")->get()` |
| Failed logins | Detect brute force | `AuditLog::where('action', 'LOGIN')->where('description', 'like', '%failed%')->get()` |
| Unauthorized access | Security monitoring | `AuditLog::where('action', 'VIEW')->where('module', '!=', 'audit-logs')->where('user_id', null)->get()` |
| Activity timeline | When users are active | `AuditLog::select(DB::raw("DATE(timestamp) as date"), DB::raw("COUNT(*) as count"))->groupBy('date')->get()` |
