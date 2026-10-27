<?php

namespace App\Services\Reports;

use App\Models\Agency;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Services\Reports\Concerns\ScopesReportQueries;
use App\Support\ReportCalendar;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class CaseMetrics
{
    use ScopesReportQueries;

    public function getOverview(?string $fromDate = null, ?string $toDate = null, ?string $agencyId = null): array
    {
        $caseQuery = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        $this->applyLocalDateWindow($caseQuery, 'cases.created_at', $fromDate, $toDate);
        if ($agencyId) {
            $caseQuery->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $caseCounts = (clone $caseQuery)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $totalCases = $caseCounts->sum();

        $refQuery = Referral::query();
        $this->applyLocalDateWindow($refQuery, 'referrals.created_at', $fromDate, $toDate);
        if ($agencyId) {
            $refQuery->where('agcy_id', $agencyId);
        }

        $refCounts = (clone $refQuery)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'totalCases' => (int) $totalCases,
            'openCases' => (int) ($caseCounts['OPEN'] ?? 0),
            'closedCases' => (int) ($caseCounts['CLOSED'] ?? 0),
            'totalReferrals' => (int) $refCounts->sum(),
            'pendingReferrals' => (int) ($refCounts['PENDING'] ?? 0),
            'activeAgencies' => (int) Agency::count(),
        ];
    }

    public function getCaseTrends(int $months = 12, ?string $agencyId = null): array
    {
        $cases = CaseFile::select(
            DB::raw(ReportCalendar::localMonth('created_at').' as month'),
            DB::raw('count(*) as total')
        )
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('created_at', '>=', now()->subMonths($months));

        if ($agencyId) {
            $cases->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $cases = $cases->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $cases->pluck('month')->toArray(),
            'data' => $cases->pluck('total')->toArray(),
        ];
    }

    public function getCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $cases = $this->caseQuery($userId, $role, $agencyId, $dateScope);
        $this->applyGeoFilter($cases, $province, $city, 'cases');

        $result = $cases
            ->select(
                DB::raw(ReportCalendar::localMonth('cases.created_at').' as month'),
                DB::raw('count(*) as total')
            )
            ->whereRaw(ReportCalendar::localDate('cases.created_at').' >= ?', [$this->windowBound($fromDate ?: now()->subMonths(12))])
            ->whereRaw(ReportCalendar::localDate('cases.created_at').' <= ?', [$this->windowBound($toDate ?: now())])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $result->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases Created',
                    'data' => $result->pluck('total')->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                ],
            ],
        ];
    }

    public function getClosedCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $cases = $this->caseQuery($userId, $role, $agencyId, $dateScope);
        $this->applyGeoFilter($cases, $province, $city, 'cases');

        // Closures are bucketed by close month â€” falling back to last update
        // for legacy CLOSED rows without a close timestamp â€” not by filing
        // month, so the series pairs with casesOverTime as filings vs closures.
        $result = $cases
            ->select(
                DB::raw(ReportCalendar::localMonth('COALESCE(cases.closed_at, cases.updated_at)').' as month'),
                DB::raw('count(*) as total')
            )
            ->where('cases.status', 'CLOSED')
            ->whereRaw(ReportCalendar::localDate('COALESCE(cases.closed_at, cases.updated_at)').' >= ?', [$this->windowBound($fromDate ?: now()->subMonths(12))])
            ->whereRaw(ReportCalendar::localDate('COALESCE(cases.closed_at, cases.updated_at)').' <= ?', [$this->windowBound($toDate ?: now())])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $result->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases Closed',
                    'data' => $result->pluck('total')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
        ];
    }

    public function getReopenedStats(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $scoped = $this->eventScopedCases($userId, $role, $agencyId, $province, $city);
        $from = ReportCalendar::toLocalDate($fromDate ?: now()->subYear());
        $to = ReportCalendar::toLocalDate($toDate ?: now());

        $reopened = CaseEvent::where('type', CaseEvent::TYPE_CASE_REOPENED)
            ->whereIn('case_id', (clone $scoped)->select('cases.id'));
        $this->applyLocalDateWindow($reopened, 'occurred_at', $from, $to);
        $reopenedCount = $reopened->count();

        // Repeat-client rate is atemporal: clients with 2+ live cases over
        // all scoped clients. Deleted, draft, and archived cases never count.
        $perClient = (clone $scoped)->whereNotNull('cases.client_id')
            ->select('cases.client_id', DB::raw('count(*) as total'))
            ->groupBy('cases.client_id')
            ->pluck('total');
        $totalClients = $perClient->count();
        $repeatClients = $perClient->filter(fn ($total) => (int) $total > 1)->count();

        return [
            'reopenedCount' => (int) $reopenedCount,
            'repeatClients' => (int) $repeatClients,
            'totalClients' => (int) $totalClients,
            'repeatClientRate' => $totalClients > 0 ? round(($repeatClients / $totalClients) * 100, 1) : 0,
        ];
    }

    public function getCaseEventActorDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $caseIds = (clone $this->eventScopedCases($userId, $role, $agencyId, $province, $city))->select('cases.id');
        $from = ReportCalendar::toLocalDate($fromDate ?: now()->subYear());
        $to = ReportCalendar::toLocalDate($toDate ?: now());

        $actors = CaseEvent::whereIn('case_id', $caseIds);
        $this->applyLocalDateWindow($actors, 'occurred_at', $from, $to);
        $actors = $actors->select('actor_type', DB::raw('count(*) as total'))
            ->groupBy('actor_type')
            ->pluck('total', 'actor_type');

        $allActors = ['agency', 'case_manager', 'system'];

        return [
            'labels' => ['Agency', 'Case manager', 'System'],
            'data' => array_map(fn ($actor) => (int) ($actors[$actor] ?? 0), $allActors),
            'colors' => ['#0b5a8c', '#6366f1', '#94a3b8'],
        ];
    }

    public function getCaseSourceDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null, ?string $fromDate = null, ?string $toDate = null, ?string $province = null, ?string $city = null): array
    {
        // cases.source was dropped in Phase 1a (account-first redesign).
        // Return empty distribution — this metric is no longer trackable.
        return [
            'labels' => ['Internal', 'Self-filed'],
            'data' => [0, 0],
            'colors' => ['#0b5a8c', '#0891b2'],
        ];
    }

    public function getCaseStatusDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        $query = CaseFile::select('status', DB::raw('count(*) as total'))
            ->whereIn('status', ['OPEN', 'CLOSED', 'ARCHIVED']);

        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $this->applyCaseWindow($query, $fromDate, $toDate, $province, $city);

        $results = $query->groupBy('status')
            ->pluck('total', 'status');

        $allStatuses = ['OPEN', 'CLOSED', 'ARCHIVED'];
        $colors = ['#1e3a8a', '#10b981', '#f59e0b'];

        return [
            'labels' => $allStatuses,
            'data' => array_map(fn ($s) => (int) ($results[$s] ?? 0), $allStatuses),
            'colors' => $colors,
        ];
    }

    public function categoryDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        // Category analytics reads the authoritative assignment table. A case
        // counts once per assigned category; deleted, draft, and archived cases
        // are excluded from both counts and percentages.
        //
        // The date and geography filters were previously missing here, so this
        // was the one panel on the report that silently reported all-time,
        // unfiltered figures while every other panel honoured the active
        // filters. Reconciling an exported category count against the
        // dashboard did not add up.
        $query = DB::table('case_category AS assignments')
            ->join('cases', 'cases.id', '=', 'assignments.case_id')
            ->join('case_categories', 'case_categories.id', '=', 'assignments.case_category_id')
            ->where('cases.is_deleted', false)
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->select('case_categories.name', 'case_categories.color', DB::raw('count(DISTINCT cases.id) as total'))
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderBy('case_categories.name');

        $this->applyLocalDateWindow($query, 'cases.created_at', $fromDate, $toDate);

        $this->applyGeoFilter($query, $province, $city, 'cases');

        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $results = $query->get();
        $total = $results->sum('total');

        return $results->map(fn ($item) => [
            'name' => $item->name,
            'color' => $item->color,
            'count' => (int) $item->total,
            'percentage' => $total > 0 ? round(($item->total / $total) * 100, 2) : 0,
        ])->toArray();
    }

    public function getCaseIssueDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->caseQuery($userId, $role, $agencyId, $dateScope);
        $this->applyLocalDateWindow($query, 'cases.created_at', $fromDate, $toDate);
        $this->applyGeoFilter($query, $province, $city, 'cases');

        $issues = (clone $query)
            ->join('case_issues', 'cases.case_issue_id', '=', 'case_issues.id')
            ->where('case_issues.is_deleted', false)
            ->select('case_issues.name', DB::raw('count(*) as total'))
            ->groupBy('case_issues.name', 'case_issues.sort_order')
            ->orderBy('case_issues.sort_order')
            ->orderByDesc('total')
            ->get();

        $chartColors = ['#0b5a8c', '#0b7a75', '#6366f1', '#f59e0b', '#ef4444', '#22c55e', '#8b5cf6', '#ec4899'];

        return $issues->map(fn ($item, $i) => [
            'name' => $item->name,
            'count' => (int) $item->total,
            'color' => $chartColors[$i % count($chartColors)],
        ])->toArray();
    }

    public function getVulnerabilityDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        $query = CaseFile::whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        // Applied before the clones below so every bucket shares one window.
        $this->applyCaseWindow($query, $fromDate, $toDate, $province, $city);

        $categories = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'];
        $counts = [];

        foreach ($categories as $cat) {
            $count = (clone $query)
                ->where(function ($q) use ($cat) {
                    $q->where('cases.vulnerability_indicator', 'LIKE', "%{$cat}%")
                        ->orWhere('cases.nok_vulnerability_indicator', 'LIKE', "%{$cat}%");
                })
                ->count();
            $counts[$cat] = $count;
        }

        // Count cases with no vulnerability set (or only "None")
        // NULL indicators must land here: NOT LIKE on NULL yields NULL, not true.
        $noneCount = (clone $query)
            ->where(function ($q) use ($categories) {
                foreach ($categories as $cat) {
                    $q->where(function ($qq) use ($cat) {
                        $qq->where('cases.vulnerability_indicator', 'NOT LIKE', "%{$cat}%")
                            ->orWhereNull('cases.vulnerability_indicator');
                    })->where(function ($qq) use ($cat) {
                        $qq->where('cases.nok_vulnerability_indicator', 'NOT LIKE', "%{$cat}%")
                            ->orWhereNull('cases.nok_vulnerability_indicator');
                    });
                }
            })
            ->count();
        $counts['None'] = $noneCount;

        $allCategories = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'];
        $colors = ['#f59e0b', '#10b981', '#8b5cf6', '#06b6d4', '#cbd5e1'];

        return [
            'labels' => $allCategories,
            'data' => array_map(fn ($c) => (int) ($counts[$c] ?? 0), $allCategories),
            'colors' => $colors,
        ];
    }

    public function getClientTypeDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        $query = $this->caseQuery($userId, $role, $agencyId);

        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return ['labels' => [], 'data' => [], 'colors' => []];
        }

        $this->applyCaseWindow($query, $fromDate, $toDate, $province, $city);

        $types = (clone $query)
            ->join('clients', 'clients.id', '=', 'cases.client_id')
            ->select('clients.type as client_type', DB::raw('count(*) as total'))
            ->groupBy('clients.type')
            ->pluck('total', 'client_type');

        return [
            'labels' => ['OFW', 'Next of Kin'],
            'data' => [
                (int) ($types['OFW'] ?? 0),
                (int) ($types['NEXT_OF_KIN'] ?? 0),
            ],
            'colors' => ['#6366f1', '#a5b4fc'],
        ];
    }

    /**
     * Normalize a reporting-window bound to a `Y-m-d` string in the operating
     * timezone.
     *
     * Callers hand down `Y-m-d` date strings while the fallbacks inside these
     * methods are Carbon instances, and both were compared straight against a
     * `timestamp` column. PostgreSQL reads a bare date as midnight, so a case
     * filed or closed at 15:00 on the final day of the window sat outside it
     * and a report running "through today" silently dropped it — while every
     * panel using `whereDate()` (see ScopesReportQueries::applyCaseWindow())
     * counted the same row, so two panels on the same page disagreed.
     *
     * A Carbon fallback such as `now()` is a UTC instant, so it is shifted to
     * the operating timezone before its date is read: at 02:00 Manila the UTC
     * date is still yesterday, and a range "through today" would otherwise
     * stop a day early.
     */
    private function windowBound(DateTimeInterface|string $bound): string
    {
        return ReportCalendar::toLocalDate($bound);
    }
}
