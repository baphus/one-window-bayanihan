## Context

The current overdue referrals page (`Admin/OverdueReferrals/Index`) serves all three roles with a uniform table — case number, client, agency, service, created date, overdue days, status, and action buttons (View + Remind). This design is being replaced by a unified rich dashboard that:

- Gives **all roles** an at-a-glance understanding of overdue referrals
- Surfaces urgency through severity bands (mild/moderate/severe) based on inactivity
- Shows status breakdown to identify bottlenecks
- Provides **role-appropriate actions** — agency focals get "View Details" (to take action on the referral detail page), admins/case managers additionally get "Send Reminder"

**Existing data model** — `Referral` with statuses `PENDING → PROCESSING → FOR_COMPLIANCE → COMPLETED` / `REJECTED`. Each has a `caseFile` (with `client` and `user` for the case manager), an `agency`, `milestones`, and `complianceRequirements`. The system setting `referral_overdue_days` defaults to 7.

**Key insight from codebase exploration**: The frontend already computes an *inactivity-based* overdue definition in `Case/Show.jsx` and `Referral/Show.jsx` (days since last milestone or status change), but the backend uses *creation-based* age. The new dashboard uses inactivity-based staleness for severity bands and sorting — a better measure of true stagnation.

## Goals / Non-Goals

**Goals:**

- Replace the old admin table with a rich dashboard used by ALL roles (ADMIN, CASE_MANAGER, AGENCY)
- Provide urgency context through severity bands (mild / moderate / severe) based on days since last activity
- Show status breakdown to surface bottlenecks
- Surface per-referral context: last milestone, compliance progress, referring case manager, agency name
- Role-scoped queries: ADMIN sees all, CASE_MANAGER sees their cases, AGENCY sees their agency
- Role-aware actions: agency gets "View Details" only; admin/cm get "View Details" + "Send Reminder"
- Preserve the existing send-reminder functionality for admin/cm (per-row + batch)

**Non-Goals:**

- Not adding inline quick actions (accept/reject/add milestone) to the list — those remain on the referral detail page
- Not adding new routes — reuses `/overdue-referrals`
- Not introducing real-time updates or WebSockets
- Not modifying the referral detail page (`Referral/Show`)

## Decisions

### D1: Inactivity-based overdue instead of creation-based

**Decision:** The dashboard measures overdue as "days since last activity" (latest milestone's `created_at`, or the referral's `updated_at` if no milestones, or `created_at` if no updates), not "days since creation."

**Rationale:** A referral created 45 days ago that received a milestone yesterday is being actively handled. A referral created 12 days ago with zero activity is stale. Inactivity tracks true stagnation better than absolute age.

**Alternatives considered:**
- *Creation-based* — Simpler but misrepresents urgency for referrals with recent activity.
- *Both* — Overcomplicates the severity bands.

### D2: Single service method for all roles, not per-role methods

**Decision:** One unified method `ReferralService::getOverdueReferralsDashboard(string $userRole, ?string $userId, ?string $userAgencyId, int $overdueDays, array $filters)` that applies role-scoping internally:

```php
public function getOverdueReferralsDashboard(
    string $userRole,
    ?string $userId,
    ?string $userAgencyId,
    int $overdueDays = 7,
    array $filters = [],   // sort_by, status_filter
): array
{
    $query = Referral::with([
        'caseFile.client:id,first_name,last_name',
        'caseFile.user:id,name',              // referring case manager
        'agency:id,name',
        'milestones' => fn($q) => $q->latest()->limit(1),
    ])
    ->withCount([
        'complianceRequirements as compliance_total',
        'complianceRequirements as compliance_fulfilled'
            => fn($q) => $q->where('status', 'COMPLIED'),
    ])
    ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
    ->whereRaw('EXTRACT(EPOCH FROM (NOW() - COALESCE(
        (SELECT MAX(created_at) FROM milestones WHERE refr_id = referrals.id),
        GREATEST(referrals.updated_at, referrals.created_at)
    ))) / 86400 > ?', [$overdueDays]);

    // Role-scoping
    match ($userRole) {
        'ADMIN' => null,                      // no filter — sees all
        'CASE_MANAGER' => $query->whereIn(
            'case_id', CaseFile::where('user_id', $userId)->select('id')
        ),
        'AGENCY' => $query->where('agcy_id', $userAgencyId),
    };

    // Apply filters/sort
    // sort_by: most_stale (default), status, client_name
    // status_filter: all (default), pending, processing, for_compliance

    // ... paginate, compute per-row severity + stats
}
```

**Rationale:** One method, one query pattern, one set of computed attributes. The role scoping is a single `match()` block. Avoids code duplication across three separate methods.

### D3: Replace the existing page, not branch to a second one

**Decision:** `OverdueReferralController::index()` renders a single `Admin/OverdueReferrals/Index` page for all roles. The old table page is replaced by the new dashboard. The route stays the same.

```php
public function index(Request $request)
{
    $user = $request->user();
    $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);

    $dashboardData = $this->referralService->getOverdueReferralsDashboard(
        userRole: $user->role,
        userId: $user->id,
        userAgencyId: $user->agcy_id,
        overdueDays: $overdueDays,
        filters: $request->only(['sort_by', 'status_filter']),
    );

    return Inertia::render('Admin/OverdueReferrals/Index', $dashboardData + [
        'userRole' => $user->role,
        'overdueDays' => $overdueDays,
    ]);
}
```

The old `Admin/OverdueReferrals/Index.jsx` is rewritten in-place. Old `getOverdueReferrals()` is deprecated.

### D4: Role-aware action column (frontend-only branching)

**Decision:** The `OverdueCard` component receives the user's role and renders different actions:

- **AGENCY**: Shows only "View Full Details" link
- **ADMIN / CASE_MANAGER**: Shows "View Full Details" link + "Send Reminder" button

Additionally, the "Send All Reminders" batch action and selection checkboxes appear only for ADMIN/CASE_MANAGER. The "Agency" column in the card is always shown (relevant for admin/cm, informational for agency).

```jsx
// In OverdueCard.jsx
function OverdueCard({ referral, userRole, onSendReminder }) {
  const canRemind = userRole === 'ADMIN' || userRole === 'CASE_MANAGER';
  
  return (
    <div className="...">
      {/* ... card content: severity dot, case, client, service, status, compliance, last activity, agency, case manager ... */}
      
      <div className="actions">
        <Link href={route('referrals.show', referral.id)}>View Full Details →</Link>
        {canRemind && (
          <button onClick={() => onSendReminder(referral.id)}>Send Reminder</button>
        )}
      </div>
    </div>
  );
}
```

### D5: Severity bands

**Decision:** Three-tier severity based on days since last activity:

| Severity | Threshold | Color | Visual |
|---|---|---|---|
| Mild | `overdueDays` to 14d | Amber | Single dot ● |
| Moderate | 15d to 29d | Orange | Double dot ●● |
| Severe | 30d+ | Rose/Red | Triple dot ●●● |

The `overdueDays` config value (default 7) acts as the floor. Severity bands above the floor are hardcoded.

### D6: Page structure — three visual sections

**Decision:** The page has three stacked sections:

1. **Summary Cards** — 4 `KpiCard` components: Total Overdue, Mild count, Moderate count, Severe count
2. **Status Breakdown** — A compact visual showing overdue referral counts by current status (PENDING, PROCESSING, FOR_COMPLIANCE) with a bottleneck insight text
3. **Rich Card List** — Each overdue referral rendered as a card row showing: severity dot + days inactive, case number + client name, required service, status badge, agency name, compliance progress (if FOR_COMPLIANCE), last activity description, referring case manager, and role-aware action buttons

### D7: Header copy adapts to role

**Decision:** The page header subtitle changes based on role:

- **ADMIN**: "Overdue referrals across all agencies — sorted by most stale first"
- **CASE_MANAGER**: "Overdue referrals from your cases — sorted by most stale first"
- **AGENCY**: "Referrals sent to your agency that need attention — sorted by most stale first"

## Data Flow

```
┌──────────────┐     GET /overdue-referrals      ┌──────────────────────────┐
│   Browser    │ ──────────────────────────────▶  │ OverdueReferral         │
│ (any role)   │                                  │ Controller::index       │
│              │ ◀──────────────────────────────  │                          │
│              │   Inertia page render             │  reads user role + ids  │
└──────────────┘                                   │  calls service method   │
                                                   │            │            │
                                                   │            ▼            │
                                                   │  ReferralService        │
                                                   │  ::getOverdue-          │
                                                   │  ReferralsDashboard()   │
                                                   │            │            │
                                                   │     Role-scoped query:  │
                                                   │     ADMIN  → all        │
                                                   │     CM     → own cases  │
                                                   │     AGENCY → own agency │
                                                   │            │            │
                                                   │     Compute per-row:    │
                                                   │     • daysSinceActivity │
                                                   │     • severity          │
                                                   │     • lastActivityDesc  │
                                                   │     • aggregate stats   │
                                                   └────────────┬───────────┘
                                                                │
                                                  ┌─────────────┴─────────────┐
                                                  │                           │
                                                  ▼                           ▼
                                       ┌──────────────────┐       ┌────────────────────┐
                                       │ Summary Stats     │       │ Rich Card List     │
                                       │ (4 KpiCards)      │       │ (role-aware        │
                                       │ Status Breakdown  │       │  actions)          │
                                       └──────────────────┘       └────────────────────┘
```

## Payload Shape

```php
// Returned by ReferralService::getOverdueReferralsDashboard()
[
    'stats' => [
        'total' => 12,
        'mild_count' => 6,      // floor to 14d inactivity
        'moderate_count' => 4,  // 15-29d
        'severe_count' => 2,    // 30d+
        'pending_count' => 3,
        'processing_count' => 4,
        'for_compliance_count' => 5,
        'bottleneck' => 'for_compliance',  // status with most overdue
    ],
    'referrals' => Paginator<[  // 15 per page
        'id' => 'uuid',
        'case_number' => 'C-042',
        'client_name' => 'Juan Dela Cruz',
        'required_services' => 'Legal Assistance',
        'status' => 'FOR_COMPLIANCE',
        'agency_name' => 'OWWA Region VII',
        'days_since_last_activity' => 45,
        'severity' => 'severe',
        'last_activity_description' => 'Milestone: Forms submitted — 45 days ago',
        'last_activity_date' => '2026-05-26T...',
        'compliance_progress' => '2/3',       // only for FOR_COMPLIANCE
        'compliance_total' => 3,
        'compliance_fulfilled' => 2,
        'case_manager_name' => 'Maria Santos',
    ]>,
    'overdueDays' => 7,
    'userRole' => 'AGENCY',                    // passed through for frontend
]
```

## Component Tree

```
<AppLayout>
  <Head title="Overdue Referrals" />

  <!-- Page Header (role-aware subtitle) -->
  <div.page-header>
    <h1>Overdue Referrals</h1>
    <p>Referrals sent to your agency that need attention — sorted by most stale first</p>
    {canRemind && (
      <div.batch-actions>
        <button onClick={sendAllReminders}>Send All Reminders</button>
      </div>
    )}
  </div>

  <!-- Section 1: Summary Cards -->
  <div.kpi-grid>
    <KpiCard title="Total Overdue" value={stats.total} icon="warning" iconBg="bg-rose-50" iconColor="text-rose-700" />
    <KpiCard title="Mild (7–14d)" value={stats.mild_count} icon="schedule" iconBg="bg-amber-50" iconColor="text-amber-700" />
    <KpiCard title="Moderate (15–29d)" value={stats.moderate_count} icon="hourglass_bottom" iconBg="bg-orange-50" iconColor="text-orange-700" />
    <KpiCard title="Severe (30d+)" value={stats.severe_count} icon="emergency" iconBg="bg-rose-50" iconColor="text-rose-700" />
  </div>

  <!-- Section 2: Status Breakdown -->
  <section.status-breakdown>
    <h2>Status Breakdown</h2>
    <StatusBar statuses={...} total={stats.total} />
    <p.insight>{bottleneckInsight}</p>
  </section>

  <!-- Section 3: Sort/Filter + List -->
  <div.sort-filter-bar>
    <select sort onChange={...} />
    <select filter onChange={...} />
  </div>

  <div.card-list>
    {referrals.data.map(ref => (
      <OverdueCard
        key={ref.id}
        referral={ref}
        userRole={userRole}
        onSendReminder={handleSendReminder}
        selected={selectedIds.includes(ref.id)}
        onSelect={toggleSelect}
      />
    ))}
  </div>

  <Pagination ... />
</AppLayout>
```

**Sub-components:**
- `Admin/OverdueReferrals/Index.jsx` — main page (rewritten in-place)
- `Admin/OverdueReferrals/OverdueCard.jsx` — rich card row with role-aware actions
- Reuses: `KpiCard`, `StatusBadge`, `Pagination` (from `UnifiedTable`)

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| **Replacing the old page in-place** could break bookmarks or existing behavior for admin/cm | The route stays the same; only the UI changes. Admin/cm get the same data (all, or scoped to their cases) plus richer context and the same remind functionality. Net improvement. |
| **Agency users seeing an "Agency" column** could be noise | The agency name is displayed in the card body contextually (e.g., "Referred to: OWWA"). For agency users viewing their own, it's a familiar reference; for admin/cm it's essential. |
| **Batch send-reminder UI** (checkboxes + send button) adds complexity to the card list | The checkbox + batch bar is only rendered when `userRole !== 'AGENCY'`. The existing pattern from the old page is preserved. |

## Open Questions

- Should the send-reminder button on individual cards trigger immediately (with confirmation dialog) or add the item to a batch selection? Keeping per-row immediate action + batch bar for consistency with the old page.
