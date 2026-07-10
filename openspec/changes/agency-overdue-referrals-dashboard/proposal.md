## Why

The current overdue referrals page (`Admin/OverdueReferrals/Index`) is a generic admin-style table shared across ADMIN, CASE_MANAGER, and AGENCY roles. It lacks context, urgency signals, and guidance — it shows a flat list without helping anyone prioritize or understand what action is needed. All roles need a dashboard that turns raw overdue data into actionable awareness, with the right tools for each role's responsibility.

## What Changes

- **Unified rich dashboard replaces the old table** — `Admin/OverdueReferrals/Index` becomes the new rich dashboard (summary cards, status breakdown, rich card list), used by ALL roles (ADMIN, CASE_MANAGER, AGENCY)
- **Role-scoped data** — ADMIN sees all overdue referrals across all agencies; CASE_MANAGER sees overdue referrals for cases they own; AGENCY sees referrals sent to their agency only
- **Role-aware actions per row**:
  - **Agency Focal**: "View Details" — navigates to the referral detail page where they can take action (accept, add milestones, submit compliance)
  - **Case Manager / Admin**: "View Details" + "Send Reminder" — can view the referral or send an email reminder to the agency about it
- **Inactivity-based overdue** — time since last milestone/status change, not time since creation
- **New service method** — `ReferralService::getOverdueReferralsDashboard()` replaces the old `getOverdueReferrals()` with enriched data, severity bands, and role-scoped queries

## Capabilities

### New Capabilities
- `overdue-referrals-dashboard`: Unified overdue referrals dashboard with severity-banded summary cards, status breakdown visualization, rich referral list sorted by inactivity staleness, and role-aware action columns

### Modified Capabilities

None.

## Impact

- **Controllers**: `app/Http/Controllers/Admin/OverdueReferralController.php` — unified `index()` that handles all roles with scoped data
- **Services**: `app/Services/ReferralService.php` — new `getOverdueReferralsDashboard()` method replaces old `getOverdueReferrals()`; old method may be removed or deprecated
- **Frontend**: Replace `resources/js/Pages/Admin/OverdueReferrals/Index.jsx` with the new dashboard page, add sub-components
- **Tests**: `tests/Feature/Security/OverdueReferralsAccessTest.php` — update for unified page behavior
- **No new routes** — reuses existing `/overdue-referrals` route
