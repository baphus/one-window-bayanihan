## Context

The feedback system currently uses free-text `service_name` strings across `servqual_configs`, `feedback_invitations`, and `feedback` tables. This creates fragile string matching that breaks on typos or naming inconsistencies. Agencies have a basic flat list of feedback responses with no aggregation, no service-level breakdown, and no response rate visibility. The SERVQUAL config management UI doesn't clearly show which form applies to which service.

**Current state:**
- `servqual_configs`: agency_id + service_name (string) + questions (JSON) + is_active
- `feedback_invitations`: case_id + agency_id + referral_id + service_name (string) + form_snapshot
- `feedback`: case_id + agency_id + referral_id + service_name (string) + overall_rating + comments
- Agency feedback page: flat paginated list, no dashboard
- Admin: no cross-agency feedback view

**Stakeholders:** Agency staff (view their feedback), Admins (view all agencies), Clients (submit feedback via public form — unchanged).

## Goals / Non-Goals

**Goals:**
- Replace string-based service matching with FK relationships
- One default SERVQUAL form per agency, with optional per-service overrides
- Auto-resolve correct form when invitation is created for a referral
- Agency dashboard with aggregate metrics, per-service breakdown, response rates
- Admin cross-agency dashboard with filtering
- Clean form management UI showing assignments

**Non-Goals:**
- Changing the public feedback submission flow (client-facing form unchanged)
- Modifying SERVQUAL question structure or dimensions
- Real-time feedback notifications
- Feedback comparison across agencies (admin can filter, not compare side-by-side)
- Migrating existing feedback data's service_name to service_id (best-effort backfill only)

## Decisions

### D1: FK to services table (not string matching)

**Decision:** Add `service_id` UUID FK to `servqual_configs`, `feedback_invitations`, and `feedback`.

**Rationale:** The codebase consistently uses UUID FKs with foreign key constraints. String matching is fragile — a typo in `service_name` breaks the form resolution chain. FK constraints enforce data integrity at the DB level.

**Alternatives considered:**
- String matching with normalize/trim: Still fragile, no DB-level enforcement
- Separate mapping table: Over-engineered for a 1:1 relationship

### D2: Default + Override pattern for form assignment

**Decision:** One default form per agency (service_id = NULL), with optional per-service overrides (service_id = FK).

**Rationale:** Most agencies will use one form for all services. The override pattern lets them customize per-service when needed without forcing complexity on everyone.

**Constraint:** Only one default (service_id IS NULL) allowed per agency. Enforced by application logic + unique partial index.

**Alternatives considered:**
- Priority-based system: Too complex for this use case
- Separate "form assignment" table: Adds a join for no real benefit

### D3: Form snapshot frozen at invitation creation

**Decision:** Copy `questions` JSON into `form_snapshot` when invitation is created. Client sees the frozen snapshot, not live config.

**Rationale:** Already implemented this way. Prevents mid-flight form changes from affecting pending invitations. Ensures feedback responses map to the exact questions the client saw.

### D4: Denormalize service_name alongside service_id

**Decision:** Keep `service_name` as a display field, populate it from the service relationship at write time.

**Rationale:** Avoids joins for display in tables/emails. Service names rarely change, and if they do, historical feedback should show the name at time of submission.

### D5: Dashboard replaces index page

**Decision:** Replace `Feedback/Index.jsx` with `Feedback/Dashboard.jsx`. The flat list had no analytical value.

**Rationale:** Agencies need aggregates, not raw records. The show page already handles individual feedback detail. A separate admin list view is handled by the admin dashboard table.

### D6: Aggregate queries via Laravel Collections + DB

**Decision:** Use DB-level queries for counts/averages (performance), Laravel collections for complex groupings (flexibility).

**Rationale:** Dashboard loads once per page view. DB queries for the heavy lifting (counts, averages, response rates), collections for service breakdowns and formatting.

### D7: Admin sees all agencies, can filter

**Decision:** Admin dashboard shows all-agency summary table + per-agency feedback table with dropdown filters.

**Rationale:** Admins need cross-agency visibility for oversight. Filtering (not comparison) is sufficient for their use case.

## Risks / Trade-offs

**[Risk] Migration breaks existing feedback data** → Best-effort backfill: match `service_name` to `services.name` where `agcy_id` matches. Unmatched records get `service_id = NULL` (still displayable via `service_name`).

**[Risk] Agencies forget to create a default form** → Validation: invitation creation fails gracefully if no form found. Log warning. Agency sees "No feedback form configured" in dashboard.

**[Risk] Performance of aggregate queries on large datasets** → Add indexes on `feedback(agency_id, created_at)`, `feedback_servqual_responses(feedback_id)`. Dashboard queries are bounded by agency scope.

**[Trade-off] Denormalized service_name** → Slight data duplication, but avoids joins on every display and preserves historical accuracy.

## Migration Plan

1. **Add columns** (nullable first): `service_id` on `servqual_configs`, `feedback_invitations`, `feedback`; `name` on `servqual_configs`
2. **Backfill** `service_id` by matching `service_name` to `services.name` where `agcy_id` matches
3. **Make service_id non-nullable** on `feedback_invitations` and `feedback` (with fallback to NULL for orphaned records)
4. **Add unique constraint** on `servqual_configs(agency_id, service_id)` where service_id IS NOT NULL
5. **Update application code** to use new FK relationships
6. **Deploy frontend** dashboard pages
7. **Remove old index route** after dashboard is live

**Rollback:** Revert to string-based matching if FK migration fails. The `service_name` columns are preserved.
