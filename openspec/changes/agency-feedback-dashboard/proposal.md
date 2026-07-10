## Why

Agencies currently have no meaningful way to view or act on client feedback. The existing feedback index page is a flat list with no aggregation, no service-level breakdown, and no response rate visibility. Additionally, the SERVQUAL config system uses free-text `service_name` strings that don't tie to actual services, making form-to-service assignment fragile and error-prone. Agencies need a proper dashboard to understand service quality, and a clean way to assign feedback forms to specific services.

## What Changes

- **Replace free-text `service_name` with `service_id` FK** on `servqual_configs`, `feedback_invitations`, and `feedback` tables. Keep `service_name` as a denormalized display field.
- **Add `name` field** to `servqual_configs` so agencies can label their forms (e.g., "Legal Aid Client Feedback").
- **Add service-specific form overrides**: One default form per agency (service_id = NULL) with optional per-service overrides. Only one default allowed per agency.
- **Auto-resolve form on invitation creation**: When a referral completes, look up the agency's form for that referral's service (override first, then default).
- **Replace feedback index page with agency dashboard**: Overview cards (total sent, response rate, avg rating, avg SERVQUAL), rating distribution, SERVQUAL dimension scores, per-service breakdown, and recent feedback list.
- **Add admin cross-agency dashboard**: All-agency summary table, per-agency feedback table with filtering by agency, service, date range, and rating.
- **Update form management UI**: Show form assignments clearly — default form vs. service overrides, with assign/unassign controls.

## Capabilities

### New Capabilities
- `feedback-dashboard`: Agency and admin feedback dashboards with aggregate metrics, per-service breakdowns, and filtering.
- `form-service-assignment`: SERVQUAL config-to-service linking via FK, with default/override resolution logic.

### Modified Capabilities

_(none — no existing specs)_

## Impact

**Database:**
- Migration: add `service_id` FK + `name` to `servqual_configs`, add `service_id` to `feedback_invitations` and `feedback`
- Backfill `service_id` from `service_name` where possible

**Backend:**
- `ServqualConfig` model: new fields, relationships, unique constraints
- `AgencyServqualConfigController`: update CRUD for service assignment
- `FeedbackInvitationService`: form resolution logic (override → default)
- `FeedbackController`: new `dashboard()` action with aggregate queries
- New `AdminFeedbackController`: cross-agency dashboard with filters
- `FeedbackController::index`: remove (replaced by dashboard)
- Routes: add dashboard routes, remove old index

**Frontend:**
- New `Feedback/Dashboard.jsx`: agency dashboard page
- New `Feedback/AdminDashboard.jsx`: admin cross-agency dashboard
- Update `Feedback/ServqualConfig/Index.jsx`: show service assignments
- Update `Feedback/ServvalConfig/Form.jsx`: service assignment dropdown
- Remove `Feedback/Index.jsx`: replaced by dashboard
- `Feedback/Show.jsx`: no changes needed

**Tests:**
- Update `FeedbackServiceTest`, `FeedbackInvitationTest`, `FeedbackControllerTest`
- New dashboard tests (aggregate queries, filtering)
- New form resolution tests (override vs default)
