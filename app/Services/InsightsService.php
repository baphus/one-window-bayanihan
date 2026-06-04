<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Feedback;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InsightsService
{
    // ========================================================================
    //  A — KPI CARDS
    // ========================================================================

    /**
     * Return 6 KPI values with previous-period comparison.
     *
     * @return array<string, array{value: mixed, change: float, change_direction: string}>
     */
    public function getKpiCards(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $duration = max($from->diffInDays($to), 1);
        $prevFrom = $from->copy()->subDays($duration);
        $prevTo = $from->copy()->subDay();

        // -- active_cases --
        $activeCases = $this->countActiveCases($user, $from, $to, $filters);
        $prevActiveCases = $this->countActiveCases($user, $prevFrom, $prevTo, $filters);

        // -- pending_referrals --
        $pendingReferrals = $this->countPendingReferrals($user, $from, $to, $filters);
        $prevPendingReferrals = $this->countPendingReferrals($user, $prevFrom, $prevTo, $filters);

        // -- avg_resolution_time --
        $avgResolutionTime = $this->avgResolutionDays($user, $from, $to, $filters);
        $prevAvgResolutionTime = $this->avgResolutionDays($user, $prevFrom, $prevTo, $filters);

        // -- sla_compliance_rate --
        $slaComplianceRate = $this->slaComplianceRate($user, $from, $to, $filters);
        $prevSlaComplianceRate = $this->slaComplianceRate($user, $prevFrom, $prevTo, $filters);

        // -- agency_composite_score --
        $agencyComposite = $this->agencyCompositeScore($user, $from, $to, $filters);
        $prevAgencyComposite = $this->agencyCompositeScore($user, $prevFrom, $prevTo, $filters);

        // -- client_satisfaction --
        $clientSatisfaction = $this->clientSatisfactionScore($user, $from, $to, $filters);
        $prevClientSatisfaction = $this->clientSatisfactionScore($user, $prevFrom, $prevTo, $filters);

        $pctChange = function ($current, $previous): array {
            if ((float) $previous == 0) {
                return ['change' => 0.0, 'change_direction' => 'flat'];
            }
            $diff = (($current - $previous) / $previous) * 100;

            return [
                'change' => round($diff, 1),
                'change_direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
            ];
        };

        return [
            'active_cases' => array_merge(
                ['value' => $activeCases],
                $pctChange($activeCases, $prevActiveCases),
            ),
            'pending_referrals' => array_merge(
                ['value' => $pendingReferrals],
                $pctChange($pendingReferrals, $prevPendingReferrals),
            ),
            'avg_resolution_time' => array_merge(
                ['value' => $avgResolutionTime],
                $pctChange($avgResolutionTime, $prevAvgResolutionTime),
            ),
            'sla_compliance_rate' => array_merge(
                ['value' => $slaComplianceRate],
                $pctChange($slaComplianceRate, $prevSlaComplianceRate),
            ),
            'agency_composite_score' => array_merge(
                ['value' => $agencyComposite],
                $pctChange($agencyComposite, $prevAgencyComposite),
            ),
            'client_satisfaction' => array_merge(
                ['value' => $clientSatisfaction],
                $pctChange($clientSatisfaction, $prevClientSatisfaction),
            ),
        ];
    }

    // ========================================================================
    //  B — CASE TRENDS (daily intake + 7-day moving average)
    // ========================================================================

    public function getCaseTrends(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subDays(90));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        // Use materialized view for ADMIN if available, otherwise live queries
        if ($user->role === 'ADMIN' && Schema::hasTable('mv_daily_case_summary')) {
            try {
                return $this->caseTrendsFromMv($from, $to, $filters);
            } catch (\Throwable) {
                // Fall through to live query
            }
        }

        return $this->caseTrendsFromLive($user, $from, $to, $filters);
    }

    // ========================================================================
    //  C — REFERRAL VOLUME (created vs completed)
    // ========================================================================

    public function getReferralVolume(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subDays(90));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $query = Referral::where('is_deleted', false)
            ->whereBetween('created_at', [$from, $to]);

        $this->applyReferralScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->whereIn('case_id', function ($q) use ($filters) {
                $q->select('id')->from('cases')
                    ->where('category_id', $filters['category_id'])
                    ->where('is_deleted', false);
            });
        }

        // Daily created counts
        $created = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Completed counts (use updated_at for completion date)
        $completed = (clone $query)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('count(*) as total'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $createdData = [];
        $completedData = [];

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateStr = $current->toDateString();
            $labels[] = $dateStr;
            $createdData[] = (int) ($created[$dateStr]->total ?? 0);
            $completedData[] = (int) ($completed[$dateStr]->total ?? 0);
            $current->addDay();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Created',
                    'data' => $createdData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Completed',
                    'data' => $completedData,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    // ========================================================================
    //  D — SLA COMPLIANCE (percentage over time + 90 % target)
    // ========================================================================

    public function getSlaCompliance(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $query = CaseFile::where('is_deleted', false)
            ->whereNotNull('sla_met')
            ->where('sla_target_days', '>', 0);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Group by month
        $rows = (clone $query)
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as period"),
                DB::raw('count(*) as total'),
                DB::raw('count(*) filter (where sla_met = true) as met'),
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->orderBy('period')
            ->get();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = $row->period;
            $data[] = $row->total > 0
                ? round(($row->met / $row->total) * 100, 1)
                : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'SLA Compliance',
                    'data' => $data,
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            'target' => 90,
        ];
    }

    // ========================================================================
    //  E — STATUS DISTRIBUTION (doughnut)
    // ========================================================================

    public function getStatusDistribution(User $user, array $filters = []): array
    {
        $query = CaseFile::select('status', DB::raw('count(*) as total'))
            ->where('is_deleted', false);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $results = $query->groupBy('status')
            ->pluck('total', 'status');

        $allStatuses = ['OPEN', 'CLOSED', 'DRAFT'];
        $colors = ['#6366f1', '#22c55e', '#f59e0b'];

        return [
            'labels' => $allStatuses,
            'datasets' => [
                [
                    'data' => array_map(fn ($s) => (int) ($results[$s] ?? 0), $allStatuses),
                    'backgroundColor' => $colors,
                    'borderColor' => array_map(fn ($c) => $c, $colors),
                ],
            ],
        ];
    }

    // ========================================================================
    //  F — CATEGORY DISTRIBUTION (bar)
    // ========================================================================

    public function getCategoryDistribution(User $user, array $filters = []): array
    {
        $query = CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as total'))
            ->join('case_categories', 'case_categories.id', '=', 'cases.category_id')
            ->where('cases.is_deleted', false);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['status'])) {
            $query->where('cases.status', $filters['status']);
        }

        $results = $query->groupBy('case_categories.name', 'case_categories.color')
            ->orderByDesc('total')
            ->get();

        if ($results->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $results->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases',
                    'data' => $results->pluck('total')->toArray(),
                    'backgroundColor' => $results->pluck('color')->map(fn ($c) => $c ?: '#6366f1')->toArray(),
                ],
            ],
        ];
    }

    // ========================================================================
    //  G — SERVICE DISTRIBUTION (from referral required_services)
    // ========================================================================

    public function getServiceDistribution(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $query = Referral::where('is_deleted', false)
            ->whereNotNull('required_services')
            ->where('required_services', '!=', '')
            ->whereBetween('created_at', [$from, $to]);

        $this->applyReferralScope($query, $user);

        // Use PostgreSQL unnest to split comma-separated service values
        try {
            $results = (clone $query)
                ->select(
                    DB::raw("trim(unnest(string_to_array(required_services, ','))) as service"),
                    DB::raw('count(*) as total')
                )
                ->groupBy(DB::raw("trim(unnest(string_to_array(required_services, ',')))"))
                ->orderByDesc('total')
                ->get();
        } catch (\Throwable) {
            // Fall back to grouping by the full required_services text
            $results = (clone $query)
                ->select('required_services as service', DB::raw('count(*) as total'))
                ->groupBy('required_services')
                ->orderByDesc('total')
                ->get();
        }

        if ($results->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        $colors = ['#6366f1', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];

        return [
            'labels' => $results->pluck('service')->toArray(),
            'datasets' => [
                [
                    'label' => 'Referrals',
                    'data' => $results->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $results->count()),
                ],
            ],
        ];
    }

    // ========================================================================
    //  H — GEOGRAPHIC DISTRIBUTION (by province)
    // ========================================================================

    public function getGeographicDistribution(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null);
        $to = $this->resolveDate($filters['to'] ?? null);

        $query = CaseFile::select(DB::raw('COALESCE(pa.name, ca.province) as province'), DB::raw('count(*) as total'))
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->leftJoin('philippine_addresses as pa', function ($join) {
                $join->on('pa.code', '=', 'ca.province')
                    ->where('pa.type', '=', 'province');
            })
            ->where('cases.is_deleted', false)
            ->whereNotNull('ca.province')
            ->where('ca.province', '!=', '');

        $this->applyCaseScope($query, $user);

        if ($from) {
            $query->whereDate('cases.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('cases.created_at', '<=', $to);
        }
        if (! empty($filters['category_id'])) {
            $query->where('cases.category_id', $filters['category_id']);
        }

        $results = $query->groupBy(DB::raw('COALESCE(pa.name, ca.province)'))
            ->orderByDesc('total')
            ->get();

        if ($results->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $results->pluck('province')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases',
                    'data' => $results->pluck('total')->toArray(),
                    'backgroundColor' => 'rgba(99, 102, 241, 0.7)',
                ],
            ],
        ];
    }

    // ========================================================================
    //  I — CLIENT TYPE SPLIT (pie)
    // ========================================================================

    public function getClientTypeSplit(User $user, array $filters = []): array
    {
        $query = CaseFile::select('client_type', DB::raw('count(*) as total'))
            ->where('is_deleted', false);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $results = $query->groupBy('client_type')
            ->pluck('total', 'client_type');

        $labels = ['OFW', 'Next of Kin'];
        $colors = ['#6366f1', '#a5b4fc'];

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => [
                        (int) ($results['OFW'] ?? 0),
                        (int) ($results['NEXT_OF_KIN'] ?? 0),
                    ],
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                ],
            ],
        ];
    }

    // ========================================================================
    //  INTERNALS — KPI sub-queries
    // ========================================================================

    private function countActiveCases(User $user, Carbon $from, Carbon $to, array $filters): int
    {
        $query = CaseFile::whereNotIn('status', ['CLOSED', 'DRAFT'])
            ->where('is_deleted', false)
            ->whereBetween('created_at', [$from, $to]);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return (int) (clone $query)->count();
    }

    private function countPendingReferrals(User $user, Carbon $from, Carbon $to, array $filters): int
    {
        $query = Referral::whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->where('is_deleted', false)
            ->whereBetween('created_at', [$from, $to]);

        $this->applyReferralScope($query, $user);

        return (int) (clone $query)->count();
    }

    private function avgResolutionDays(User $user, Carbon $from, Carbon $to, array $filters): float
    {
        $query = CaseFile::where('status', 'CLOSED')
            ->where('is_deleted', false)
            ->whereBetween('created_at', [$from, $to]);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $avg = (clone $query)
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        return round((float) ($avg ?? 0), 1);
    }

    private function slaComplianceRate(User $user, Carbon $from, Carbon $to, array $filters): float
    {
        $query = CaseFile::where('is_deleted', false)
            ->where('sla_target_days', '>', 0)
            ->whereNotNull('sla_met')
            ->whereBetween('created_at', [$from, $to]);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $total = (int) (clone $query)->count();
        if ($total === 0) {
            return 0.0;
        }

        $met = (int) (clone $query)->where('sla_met', true)->count();

        return round(($met / $total) * 100, 1);
    }

    private function agencyCompositeScore(User $user, Carbon $from, Carbon $to, array $filters): float
    {
        $query = Feedback::whereBetween('created_at', [$from, $to])
            ->whereNotNull('overall_rating');

        switch ($user->role) {
            case 'CASE_MANAGER':
                $query->whereIn('case_id', function ($q) use ($user) {
                    $q->select('id')->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false);
                });
                break;
            case 'AGENCY':
                $query->where('agency_id', $user->agcy_id);
                break;
        }

        // For composite, average per-agency ratings then average those
        $agencyAvgs = (clone $query)
            ->select('agency_id', DB::raw('AVG(overall_rating) as avg_rating'))
            ->whereNotNull('agency_id')
            ->groupBy('agency_id')
            ->get()
            ->pluck('avg_rating')
            ->filter();

        if ($agencyAvgs->isEmpty()) {
            return 0.0;
        }

        return round((float) $agencyAvgs->avg(), 2);
    }

    private function clientSatisfactionScore(User $user, Carbon $from, Carbon $to, array $filters): float
    {
        $query = Feedback::whereBetween('created_at', [$from, $to])
            ->whereNotNull('overall_rating');

        switch ($user->role) {
            case 'CASE_MANAGER':
                $query->whereIn('case_id', function ($q) use ($user) {
                    $q->select('id')->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false);
                });
                break;
            case 'AGENCY':
                $query->where('agency_id', $user->agcy_id);
                break;
        }

        $avg = (clone $query)->avg('overall_rating');

        return round((float) ($avg ?? 0), 2);
    }

    // ========================================================================
    //  INTERNALS — case trends helpers
    // ========================================================================

    private function caseTrendsFromMv(Carbon $from, Carbon $to, array $filters): array
    {
        $query = DB::table('mv_daily_case_summary')
            ->select('date', DB::raw('SUM(case_count) as total'))
            ->whereBetween('date', [$from, $to]);

        if (! empty($filters['status'])) {
            $query->where('status_slug', $filters['status']);
        }
        if (! empty($filters['category_id'])) {
            $categoryName = CaseCategory::where('id', $filters['category_id'])->value('name');
            if ($categoryName) {
                $query->where('category_name', $categoryName);
            }
        }

        $daily = $query->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->buildTrendDataset($from, $to, $daily);
    }

    private function caseTrendsFromLive(User $user, Carbon $from, Carbon $to, array $filters): array
    {
        $query = CaseFile::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('is_deleted', false)
            ->whereBetween('created_at', [$from, $to]);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $daily = $query->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->buildTrendDataset($from, $to, $daily);
    }

    /**
     * Build labels + datasets (raw + 7-day moving average) from keyed daily data.
     */
    private function buildTrendDataset(Carbon $from, Carbon $to, $keyed): array
    {
        $labels = [];
        $raw = [];

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateStr = $current->toDateString();
            $labels[] = $dateStr;
            $raw[] = (int) (isset($keyed[$dateStr]) ? $keyed[$dateStr]->total : 0);
            $current->addDay();
        }

        // 7-day moving average
        $movingAvg = [];
        foreach ($raw as $i => $value) {
            if ($i < 6) {
                $movingAvg[] = null;
            } else {
                $sum = 0;
                for ($j = $i - 6; $j <= $i; $j++) {
                    $sum += $raw[$j];
                }
                $movingAvg[] = round($sum / 7, 1);
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Cases',
                    'data' => $raw,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => '7-day MA',
                    'data' => $movingAvg,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    // ========================================================================
    //  INTERNALS — role-aware scoping
    // ========================================================================

    /**
     * Apply role-based scoping to a cases query.
     *
     * - CASE_MANAGER: filter by user_id
     * - AGENCY:       restrict to cases that have referrals to the user's agency
     * - ADMIN:        no filter
     */
    private function applyCaseScope($query, User $user): void
    {
        match ($user->role) {
            'CASE_MANAGER' => $query->where('cases.user_id', $user->id),
            'AGENCY' => $query->whereIn('cases.id', function ($q) use ($user) {
                $q->select('case_id')
                    ->from('referrals')
                    ->where('agcy_id', $user->agcy_id)
                    ->where('is_deleted', false);
            }),
            default => null,
        };
    }

    /**
     * Apply role-based scoping to a referrals query.
     *
     * - CASE_MANAGER: filter by case_id IN (user's cases)
     * - AGENCY:       filter by agcy_id
     * - ADMIN:        no filter
     */
    private function applyReferralScope($query, User $user): void
    {
        match ($user->role) {
            'CASE_MANAGER' => $query->whereIn('case_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false);
            }),
            'AGENCY' => $query->where('agcy_id', $user->agcy_id),
            default => null,
        };
    }

    // ========================================================================
    //  INTERNALS — date / filter helpers
    // ========================================================================

    /**
     * Resolve a filter value to a Carbon instance.
     * Returns null if the value is not provided.
     */
    private function resolveDate(mixed $value, ?Carbon $default = null): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return $default;
    }

    // ========================================================================
    //  J — AGING CASES (age buckets)
    // ========================================================================

    public function getAgingCases(User $user, array $filters = []): array
    {
        $query = CaseFile::select(
            'cases.id',
            'cases.case_number',
            DB::raw("TRIM(CONCAT(COALESCE(clients.first_name, ''), ' ', COALESCE(clients.last_name, ''))) as client_name"),
            'cases.created_at',
            DB::raw('EXTRACT(EPOCH FROM (NOW() - cases.created_at)) / 86400 as age_days'),
            'cases.status',
        )
            ->leftJoin('clients', 'clients.id', '=', 'cases.client_id')
            ->whereNotIn('cases.status', ['CLOSED', 'DRAFT'])
            ->where('cases.is_deleted', false);

        $this->applyCaseScope($query, $user);

        if (! empty($filters['category_id'])) {
            $query->where('cases.category_id', $filters['category_id']);
        }

        $rows = $query->orderByDesc('age_days')
            ->get()
            ->map(fn ($r) => (object) [
                'id' => $r->id,
                'case_number' => $r->case_number,
                'client_name' => trim($r->client_name) ?: 'N/A',
                'created_at' => $r->created_at?->toISOString(),
                'age_days' => (int) round((float) $r->age_days),
                'status' => $r->status,
            ]);

        $buckets = [
            '0-30 days' => 0,
            '31-60 days' => 0,
            '61-90 days' => 0,
            '90+ days' => 0,
        ];
        $colors = ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'];

        foreach ($rows as $row) {
            $days = $row->age_days;
            if ($days <= 30) {
                $buckets['0-30 days']++;
            } elseif ($days <= 60) {
                $buckets['31-60 days']++;
            } elseif ($days <= 90) {
                $buckets['61-90 days']++;
            } else {
                $buckets['90+ days']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
            'colors' => $colors,
            'details' => $rows->toArray(),
        ];
    }

    // ========================================================================
    //  K — STALLED REFERRALS
    // ========================================================================

    public function getStalledReferrals(User $user, array $filters = [], int $staleDays = 7): array
    {
        $query = Referral::select(
            'referrals.id',
            'cases.case_number',
            'agencies.name as agency_name',
            'referrals.status',
            DB::raw('EXTRACT(EPOCH FROM (NOW() - COALESCE(referrals.updated_at, referrals.created_at))) / 86400 as days_since_activity'),
        )
            ->leftJoin('cases', 'cases.id', '=', 'referrals.case_id')
            ->leftJoin('agencies', 'agencies.id', '=', 'referrals.agcy_id')
            ->whereIn('referrals.status', ['PENDING', 'PROCESSING', 'FOR COMPLIANCE'])
            ->where('referrals.is_deleted', false);

        $this->applyReferralScope($query, $user);

        $rows = $query->whereRaw('EXTRACT(EPOCH FROM (NOW() - COALESCE(referrals.updated_at, referrals.created_at))) / 86400 > ?', [$staleDays])
            ->orderByDesc('days_since_activity')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'case_number' => $r->case_number ?? 'N/A',
                'agency_name' => $r->agency_name ?? 'N/A',
                'status' => $r->status,
                'days_since_activity' => (int) round((float) $r->days_since_activity),
            ]);

        return [
            'stalled_count' => $rows->count(),
            'referrals' => $rows->toArray(),
            'threshold_days' => $staleDays,
        ];
    }

    // ========================================================================
    //  L — OVERLOADED AGENCIES
    // ========================================================================

    public function getOverloadedAgencies(User $user, array $filters = [], int $threshold = 10): array
    {
        $query = Referral::select('agencies.name', DB::raw('COUNT(*) as active_count'))
            ->join('agencies', 'agencies.id', '=', 'referrals.agcy_id')
            ->whereNotIn('referrals.status', ['COMPLETED', 'REJECTED'])
            ->where('referrals.is_deleted', false);

        $this->applyReferralScope($query, $user);

        $rows = $query->groupBy('agencies.id', 'agencies.name')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->orderByDesc('active_count')
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'data' => [], 'threshold' => $threshold];
        }

        $colors = ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#3b82f6'];

        return [
            'labels' => $rows->pluck('name')->toArray(),
            'data' => $rows->pluck('active_count')->toArray(),
            'colors' => array_slice($colors, 0, $rows->count()),
            'threshold' => $threshold,
        ];
    }

    // ========================================================================
    //  M — BOTTLENECK ANALYSIS (avg hours per status transition)
    // ========================================================================

    public function getBottleneckAnalysis(User $user, array $filters = []): array
    {
        // Build entity scoping for the CTE
        $entityFilter = '';
        $bindings = [];

        if ($user->role === 'CASE_MANAGER') {
            $entityFilter = 'AND al.entity_id IN (
                SELECT id FROM cases WHERE user_id = ? AND is_deleted = false
                UNION
                SELECT id FROM referrals
                WHERE case_id IN (SELECT id FROM cases WHERE user_id = ? AND is_deleted = false)
                  AND is_deleted = false
            )';
            $bindings = [$user->id, $user->id];
        } elseif ($user->role === 'AGENCY') {
            $entityFilter = 'AND al.entity_id IN (
                SELECT id FROM referrals WHERE agcy_id = ? AND is_deleted = false
                UNION
                SELECT id FROM cases
                WHERE id IN (SELECT case_id FROM referrals WHERE agcy_id = ? AND is_deleted = false)
                  AND is_deleted = false
            )';
            $bindings = [$user->agcy_id, $user->agcy_id];
        }

        $sql = "
            WITH status_changes AS (
                SELECT
                    entity_id,
                    old_value->>'status' AS from_status,
                    new_value->>'status' AS to_status,
                    timestamp,
                    LEAD(timestamp) OVER (PARTITION BY entity_id ORDER BY timestamp) AS next_timestamp
                FROM audit_logs al
                WHERE al.module IN ('CASE', 'cases', 'case_files', 'REFERRAL', 'referrals')
                  AND al.action = 'status_change'
                  AND al.is_deleted = false
                  {$entityFilter}
            )
            SELECT
                CONCAT(from_status, ' → ', to_status) AS transition,
                COUNT(*) AS count,
                AVG(EXTRACT(EPOCH FROM (COALESCE(next_timestamp, NOW()) - timestamp)) / 3600) AS avg_hours
            FROM status_changes
            WHERE from_status IS NOT NULL
              AND to_status IS NOT NULL
            GROUP BY CONCAT(from_status, ' → ', to_status)
            ORDER BY avg_hours DESC
        ";

        $rows = DB::select($sql, $bindings);

        if (empty($rows)) {
            return ['labels' => [], 'datasets' => []];
        }

        $colors = ['#6366f1', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];

        return [
            'labels' => array_map(fn ($r) => $r->transition, $rows),
            'datasets' => [
                [
                    'label' => 'Avg Hours',
                    'data' => array_map(fn ($r) => round((float) $r->avg_hours, 1), $rows),
                    'backgroundColor' => array_slice($colors, 0, count($rows)),
                ],
            ],
        ];
    }

    // ========================================================================
    //  N — REJECTION ANALYSIS (grouped by decision reason)
    // ========================================================================

    public function getRejectionAnalysis(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null);
        $to = $this->resolveDate($filters['to'] ?? null);

        // Rejection grouping
        $query = Referral::select('decision', DB::raw('count(*) as total'))
            ->where('status', 'REJECTED')
            ->where('is_deleted', false)
            ->whereNotNull('decision')
            ->where('decision', '!=', '');

        $this->applyReferralScope($query, $user);

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $results = $query->groupBy('decision')
            ->orderByDesc('total')
            ->get();

        // Total referrals for rate calculation
        $totalQuery = Referral::where('is_deleted', false);
        $this->applyReferralScope($totalQuery, $user);
        if ($from) {
            $totalQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $totalQuery->whereDate('created_at', '<=', $to);
        }
        $totalReferrals = (int) (clone $totalQuery)->count();
        $rejectedCount = (int) $results->sum('total');
        $rejectionRate = $totalReferrals > 0 ? round(($rejectedCount / $totalReferrals) * 100, 1) : 0;

        if ($results->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
                'colors' => [],
                'total_rejected' => 0,
                'rejection_rate' => $rejectionRate,
            ];
        }

        $colors = ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#3b82f6'];

        return [
            'labels' => $results->pluck('decision')->toArray(),
            'data' => $results->pluck('total')->toArray(),
            'colors' => array_slice($colors, 0, $results->count()),
            'total_rejected' => $rejectedCount,
            'rejection_rate' => $rejectionRate,
        ];
    }

    // ========================================================================
    //  O — CASE MANAGER SCORECARD
    // ========================================================================

    public function getCaseManagerScorecard(User $user, array $filters = []): array
    {
        if ($user->role === 'AGENCY') {
            return ['labels' => [], 'datasets' => []];
        }

        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $roleFilter = '';
        $bindings = [$from, $to];

        if ($user->role === 'CASE_MANAGER') {
            $roleFilter = 'AND c.user_id = ?';
            $bindings[] = $user->id;
        }

        $sql = "
            WITH cm_agg AS (
                SELECT
                    c.user_id,
                    u.name AS cm_name,
                    COUNT(DISTINCT c.id) AS active_cases,
                    COUNT(*) FILTER (
                        WHERE r.status = 'COMPLETED' AND r.updated_at >= NOW() - INTERVAL '30 days'
                    ) AS resolved_30d,
                    AVG(EXTRACT(EPOCH FROM (r.updated_at - r.created_at)) / 86400)
                        FILTER (WHERE r.status = 'COMPLETED') AS avg_resolution_days,
                    CASE
                        WHEN COUNT(*) FILTER (WHERE c.sla_met IS NOT NULL) > 0
                        THEN AVG(c.sla_met::int) FILTER (WHERE c.sla_met IS NOT NULL)
                        ELSE NULL
                    END AS sla_compliance,
                    COUNT(*) AS total_referrals
                FROM referrals r
                JOIN cases c ON c.id = r.case_id
                JOIN users u ON u.id = c.user_id
                WHERE r.is_deleted = false
                  AND c.is_deleted = false
                  AND r.created_at >= ?
                  AND r.created_at <= ?
                  {$roleFilter}
                GROUP BY c.user_id, u.name
                HAVING COUNT(*) > 0
            )
            SELECT *,
                ROW_NUMBER() OVER (ORDER BY active_cases DESC, total_referrals DESC) AS rank
            FROM cm_agg
            ORDER BY rank
        ";

        $rows = DB::select($sql, $bindings);

        if (empty($rows)) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_map(fn ($r) => $r->cm_name, $rows),
            'datasets' => [
                [
                    'label' => 'Active Cases',
                    'data' => array_map(fn ($r) => (int) $r->active_cases, $rows),
                ],
                [
                    'label' => 'Resolved (30d)',
                    'data' => array_map(fn ($r) => (int) $r->resolved_30d, $rows),
                ],
                [
                    'label' => 'Avg Resolution (days)',
                    'data' => array_map(fn ($r) => round((float) ($r->avg_resolution_days ?? 0), 1), $rows),
                ],
                [
                    'label' => 'SLA Compliance',
                    'data' => array_map(
                        fn ($r) => $r->sla_compliance !== null
                            ? round((float) $r->sla_compliance * 100, 1) : 0,
                        $rows
                    ),
                ],
            ],
            'ranks' => array_map(fn ($r) => (int) $r->rank, $rows),
        ];
    }

    // ========================================================================
    //  P — AGENCY SCORECARD (composite score)
    // ========================================================================

    public function getAgencyScorecard(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $roleFilter = '';
        $bindings = [$from, $to];

        if ($user->role === 'CASE_MANAGER') {
            $roleFilter = 'AND r.case_id IN (SELECT id FROM cases WHERE user_id = ? AND is_deleted = false)';
            $bindings[] = $user->id;
        } elseif ($user->role === 'AGENCY') {
            $roleFilter = 'AND r.agcy_id = ?';
            $bindings[] = $user->agcy_id;
        }

        $sql = "
            WITH agency_stats AS (
                SELECT
                    r.agcy_id,
                    a.name AS agency_name,
                    COUNT(*) AS received,
                    COUNT(*) FILTER (WHERE r.status = 'COMPLETED') AS completed,
                    AVG(EXTRACT(EPOCH FROM (r.updated_at - r.created_at)) / 86400)
                        FILTER (WHERE r.status = 'COMPLETED') AS avg_days,
                    CASE
                        WHEN COUNT(*) FILTER (WHERE r.sla_met IS NOT NULL) > 0
                        THEN AVG(r.sla_met::int) FILTER (WHERE r.sla_met IS NOT NULL)
                        ELSE 0
                    END AS sla_rate
                FROM referrals r
                JOIN agencies a ON a.id = r.agcy_id
                JOIN cases c ON c.id = r.case_id
                WHERE r.is_deleted = false
                  AND c.is_deleted = false
                  AND r.created_at >= ?
                  AND r.created_at <= ?
                  {$roleFilter}
                GROUP BY r.agcy_id, a.name
                HAVING COUNT(*) >= 5
            ),
            agency_satisfaction AS (
                SELECT
                    agency_id,
                    AVG(overall_rating) AS avg_satisfaction
                FROM feedback
                WHERE overall_rating IS NOT NULL
                  AND created_at >= ?
                  AND created_at <= ?
                GROUP BY agency_id
            )
            SELECT
                s.agcy_id,
                s.agency_name,
                s.received,
                s.completed,
                ROUND(
                    CASE WHEN s.received > 0
                        THEN (s.completed::float / s.received) * 100
                        ELSE 0
                    END, 1
                ) AS completion_rate_pct,
                ROUND(COALESCE(s.avg_days, 0), 1) AS avg_days,
                ROUND(COALESCE(sat.avg_satisfaction, 0), 2) AS avg_satisfaction,
                ROUND(COALESCE(s.sla_rate, 0) * 100, 1) AS sla_rate_pct,
                ROUND(
                    COALESCE(s.completed::float / NULLIF(s.received, 0), 0) * 0.3
                    + (1.0 - LEAST(COALESCE(s.avg_days, 30), 30) / 30.0) * 0.2
                    + (COALESCE(sat.avg_satisfaction, 0) / 5.0) * 0.3
                    + COALESCE(s.sla_rate, 0) * 0.2
                , 4) AS composite_score
            FROM agency_stats s
            LEFT JOIN agency_satisfaction sat ON sat.agency_id = s.agcy_id
            ORDER BY composite_score DESC
        ";

        // Bind the extra params for agency_satisfaction CTE
        $bindings[] = $from;
        $bindings[] = $to;

        $rows = DB::select($sql, $bindings);

        if (empty($rows)) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_map(fn ($r) => $r->agency_name, $rows),
            'datasets' => [
                [
                    'label' => 'Completion Rate',
                    'data' => array_map(fn ($r) => (float) $r->completion_rate_pct, $rows),
                ],
                [
                    'label' => 'Avg Resolution (days)',
                    'data' => array_map(fn ($r) => (float) $r->avg_days, $rows),
                ],
                [
                    'label' => 'Satisfaction',
                    'data' => array_map(fn ($r) => (float) $r->avg_satisfaction, $rows),
                ],
                [
                    'label' => 'SLA Rate',
                    'data' => array_map(fn ($r) => (float) $r->sla_rate_pct, $rows),
                ],
                [
                    'label' => 'Composite Score',
                    'data' => array_map(fn ($r) => (float) $r->composite_score, $rows),
                ],
            ],
            'detailed' => json_decode(json_encode($rows), true),
        ];
    }

    // ========================================================================
    //  Q — SERVICE COMPLETION RATE
    // ========================================================================

    public function getServiceCompletionRate(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $query = Referral::select(
            DB::raw("trim(unnest(string_to_array(required_services, ','))) as service"),
            DB::raw('count(*) as total'),
            DB::raw("count(*) filter (where status = 'COMPLETED') as completed"),
        )
            ->where('is_deleted', false)
            ->whereNotNull('required_services')
            ->where('required_services', '!=', '')
            ->whereBetween('created_at', [$from, $to]);

        $this->applyReferralScope($query, $user);

        try {
            $results = $query
                ->groupBy(DB::raw("trim(unnest(string_to_array(required_services, ',')))"))
                ->orderByDesc('total')
                ->get();
        } catch (\Throwable) {
            $fallback = Referral::select(
                'required_services as service',
                DB::raw('count(*) as total'),
                DB::raw("count(*) filter (where status = 'COMPLETED') as completed"),
            )
                ->where('is_deleted', false)
                ->whereNotNull('required_services')
                ->where('required_services', '!=', '')
                ->whereBetween('created_at', [$from, $to]);

            $this->applyReferralScope($fallback, $user);
            $results = $fallback
                ->groupBy('required_services')
                ->orderByDesc('total')
                ->get();
        }

        if ($results->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        $labels = [];
        $completionData = [];

        foreach ($results as $row) {
            $labels[] = $row->service;
            $completionData[] = $row->total > 0
                ? round(($row->completed / $row->total) * 100, 1)
                : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Completion Rate',
                    'data' => $completionData,
                    'backgroundColor' => '#22c55e',
                ],
            ],
        ];
    }

    // ========================================================================
    //  R — FIRST RESPONSE TIME (trend)
    // ========================================================================

    public function getFirstResponseTime(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $roleFilter = '';
        $bindings = [$from, $to];

        if ($user->role === 'CASE_MANAGER') {
            $roleFilter = 'AND r.case_id IN (SELECT id FROM cases WHERE user_id = ? AND is_deleted = false)';
            $bindings[] = $user->id;
        } elseif ($user->role === 'AGENCY') {
            $roleFilter = 'AND r.agcy_id = ?';
            $bindings[] = $user->agcy_id;
        }

        $sql = "
            WITH first_responses AS (
                SELECT
                    r.id,
                    r.created_at,
                    COALESCE(
                        r.first_action_at,
                        (
                            SELECT MIN(al.timestamp)
                            FROM audit_logs al
                            WHERE al.entity_id = r.id
                              AND al.action = 'status_change'
                              AND al.is_deleted = false
                        ),
                        r.updated_at
                    ) AS first_response_at,
                    to_char(r.created_at, 'YYYY-MM') AS period
                FROM referrals r
                JOIN cases c ON c.id = r.case_id
                WHERE r.is_deleted = false
                  AND c.is_deleted = false
                  AND r.created_at >= ?
                  AND r.created_at <= ?
                  {$roleFilter}
            )
            SELECT
                period,
                COUNT(*) AS count,
                AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 3600) AS avg_hours
            FROM first_responses
            WHERE first_response_at IS NOT NULL
              AND created_at IS NOT NULL
            GROUP BY period
            ORDER BY period
        ";

        $rows = DB::select($sql, $bindings);

        if (empty($rows)) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_map(fn ($r) => $r->period, $rows),
            'datasets' => [
                [
                    'label' => 'Avg Hours to First Response',
                    'data' => array_map(fn ($r) => round((float) ($r->avg_hours ?? 0), 1), $rows),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    // ========================================================================
    //  S — SATISFACTION TREND (monthly avg rating)
    // ========================================================================

    public function getSatisfactionTrend(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $query = Feedback::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as period"),
            DB::raw('AVG(overall_rating) as avg_rating'),
            DB::raw('count(*) as count'),
        )
            ->whereNotNull('overall_rating')
            ->whereBetween('created_at', [$from, $to]);

        switch ($user->role) {
            case 'CASE_MANAGER':
                $query->whereIn('case_id', function ($q) use ($user) {
                    $q->select('id')->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false);
                });
                break;
            case 'AGENCY':
                $query->where('agency_id', $user->agcy_id);
                break;
        }

        $rows = $query->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->orderBy('period')
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        $colors = ['#6366f1', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444'];

        return [
            'labels' => $rows->pluck('period')->toArray(),
            'datasets' => [
                [
                    'label' => 'Avg Rating',
                    'data' => $rows->map(fn ($r) => round((float) ($r->avg_rating ?? 0), 2))->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Submissions',
                    'data' => $rows->map(fn ($r) => (int) $r->count)->toArray(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    // ========================================================================
    //  T — SERVQUAL SCORES (5-dimension gaps)
    // ========================================================================

    public function getServqualScores(User $user, array $filters = []): array
    {
        if (! Schema::hasTable('feedback_servqual_responses')) {
            return ['dimensions' => []];
        }

        $from = $this->resolveDate($filters['from'] ?? null);
        $to = $this->resolveDate($filters['to'] ?? null);

        $roleFilter = '';
        $bindings = [];

        if ($user->role === 'CASE_MANAGER') {
            $roleFilter = 'AND c.user_id = ?';
            $bindings[] = $user->id;
        } elseif ($user->role === 'AGENCY') {
            $roleFilter = 'AND f.agency_id = ?';
            $bindings[] = $user->agcy_id;
        }

        $dateFilter = '';
        if ($from) {
            $dateFilter .= ' AND fsr.created_at >= ?';
            $bindings[] = $from;
        }
        if ($to) {
            $dateFilter .= ' AND fsr.created_at <= ?';
            $bindings[] = $to;
        }

        $sql = "
            SELECT
                fsr.dimension,
                ROUND(AVG(fsr.expectation), 2) AS avg_expectation,
                ROUND(AVG(fsr.perception), 2) AS avg_perception,
                ROUND(AVG(fsr.perception) - AVG(fsr.expectation), 2) AS gap,
                COUNT(*) AS response_count
            FROM feedback_servqual_responses fsr
            JOIN feedback f ON f.id = fsr.feedback_id
            JOIN cases c ON c.id = f.case_id
            WHERE c.is_deleted = false
              AND fsr.expectation IS NOT NULL
              AND fsr.perception IS NOT NULL
              {$dateFilter}
              {$roleFilter}
            GROUP BY fsr.dimension
            ORDER BY fsr.dimension
        ";

        $rows = DB::select($sql, $bindings);

        return [
            'dimensions' => array_map(fn ($r) => [
                'dimension' => $r->dimension,
                'expectation' => (float) $r->avg_expectation,
                'perception' => (float) $r->avg_perception,
                'gap' => (float) $r->gap,
                'response_count' => (int) $r->response_count,
            ], $rows),
        ];
    }

    // ========================================================================
    //  U — AGENCY SATISFACTION RANKING
    // ========================================================================

    public function getAgencySatisfactionRanking(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null);
        $to = $this->resolveDate($filters['to'] ?? null);

        $query = Feedback::select(
            'agencies.name',
            DB::raw('AVG(feedback.overall_rating) as avg_rating'),
            DB::raw('COUNT(*) as submission_count'),
        )
            ->join('agencies', 'agencies.id', '=', 'feedback.agency_id')
            ->whereNotNull('feedback.overall_rating')
            ->whereNotNull('feedback.agency_id');

        switch ($user->role) {
            case 'CASE_MANAGER':
                $query->whereIn('feedback.case_id', function ($q) use ($user) {
                    $q->select('id')->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false);
                });
                break;
            case 'AGENCY':
                $query->where('feedback.agency_id', $user->agcy_id);
                break;
        }

        if ($from) {
            $query->whereDate('feedback.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('feedback.created_at', '<=', $to);
        }

        $rows = $query->groupBy('agencies.id', 'agencies.name')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('avg_rating')
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'data' => [], 'colors' => []];
        }

        $colors = ['#6366f1', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];

        return [
            'labels' => $rows->pluck('name')->toArray(),
            'data' => $rows->map(fn ($r) => round((float) $r->avg_rating, 2))->toArray(),
            'colors' => array_slice($colors, 0, $rows->count()),
        ];
    }

    // ========================================================================
    //  V — FEEDBACK VOLUME (monthly submissions)
    // ========================================================================

    public function getFeedbackVolume(User $user, array $filters = []): array
    {
        $from = $this->resolveDate($filters['from'] ?? null, now()->subMonths(6));
        $to = $this->resolveDate($filters['to'] ?? null, now());

        $query = Feedback::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as period"),
            DB::raw('COUNT(*) as total'),
        )
            ->whereBetween('created_at', [$from, $to]);

        switch ($user->role) {
            case 'CASE_MANAGER':
                $query->whereIn('case_id', function ($q) use ($user) {
                    $q->select('id')->from('cases')
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false);
                });
                break;
            case 'AGENCY':
                $query->where('agency_id', $user->agcy_id);
                break;
        }

        $rows = $query->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->orderBy('period')
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $rows->pluck('period')->toArray(),
            'datasets' => [
                [
                    'label' => 'Feedback Submissions',
                    'data' => $rows->pluck('total')->toArray(),
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    // ========================================================================
    //  I — PREDICTIVE ANALYTICS (Tier 1 Statistical)
    // ========================================================================

    /**
     * 30-day rolling forecast using 7-day moving average + linear regression.
     *
     * @return Collection<int, array{date: string, actual: int|float|null, moving_avg: float|null, projected: float|null}>
     */
    public function getCaseVolumeForecast(?User $user, int $daysBack = 90, int $forecastDays = 30): Collection
    {
        $from = now()->subDays($daysBack)->startOfDay();
        $to = now()->endOfDay();

        $query = DB::table('cases')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->where('is_deleted', false)
            ->whereBetween('created_at', [$from, $to]);

        if ($user && $user->role === 'CASE_MANAGER') {
            $query->where('user_id', $user->id);
        } elseif ($user && $user->role === 'AGENCY') {
            // Cases belong to CASE_MANAGERs, not agencies
            $query->whereRaw('1 = 0');
        }

        $daily = $query->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('total', 'date');

        $dateCursor = $from->copy();
        $points = [];
        $index = 0;
        $xSum = 0;
        $ySum = 0;
        $xySum = 0;
        $x2Sum = 0;
        $n = 0;

        while ($dateCursor->lte($to)) {
            $key = $dateCursor->format('Y-m-d');
            $actual = $daily->get($key);
            $points[] = [
                'date' => $key,
                'actual' => $actual,
                'moving_avg' => null,
                'projected' => null,
            ];
            if ($actual !== null) {
                $xSum += $index;
                $ySum += $actual;
                $xySum += $index * $actual;
                $x2Sum += $index * $index;
                $n++;
            }
            $dateCursor->addDay();
            $index++;
        }

        // Compute 7-day moving average
        $window = [];
        foreach ($points as &$pt) {
            if ($pt['actual'] !== null) {
                $window[] = $pt['actual'];
                if (count($window) > 7) {
                    array_shift($window);
                }
                $pt['moving_avg'] = round(array_sum($window) / count($window), 1);
            } else {
                $pt['moving_avg'] = count($window) > 0
                    ? round(array_sum($window) / count($window), 1)
                    : null;
            }
        }
        unset($pt);

        // Linear regression on available data points
        $slope = 0;
        $intercept = 0;
        if ($n > 1) {
            $slope = ($n * $xySum - $xSum * $ySum) / ($n * $x2Sum - $xSum * $xSum);
            $intercept = ($ySum - $slope * $xSum) / $n;
        }

        // Project forward
        for ($i = 0; $i < $forecastDays; $i++) {
            $projIndex = $index + $i;
            $projDate = $to->copy()->addDays($i + 1)->format('Y-m-d');
            $projected = round($intercept + $slope * $projIndex, 1);
            $points[] = [
                'date' => $projDate,
                'actual' => null,
                'moving_avg' => null,
                'projected' => $projected >= 0 ? $projected : 0,
            ];
        }

        return Cache::remember('insights.forecast.case_volume', 3600, fn () => collect(array_slice($points, -($daysBack < 90 ? $daysBack : 90) - $forecastDays))
        );
    }

    /**
     * Categorise open cases by SLA breach risk.
     *
     * @return Collection<int, array{category: string, count: int, percentage: float}>
     */
    public function getBreachProbability(?User $user): Collection
    {
        $query = DB::table('cases')
            ->select(
                DB::raw("
                    CASE
                        WHEN status = 'OPEN' AND (created_at + sla_target_days * INTERVAL '1 day') <= NOW() THEN 'breached'
                        WHEN status = 'OPEN' AND (created_at + (sla_target_days * 0.8) * INTERVAL '1 day') <= NOW() THEN 'warning'
                        WHEN status = 'CLOSED' AND sla_met = false THEN 'breached'
                        ELSE 'within_sla'
                    END as category
                "),
                DB::raw('COUNT(*) as count')
            )
            ->where('is_deleted', false);

        if ($user && $user->role === 'CASE_MANAGER') {
            $query->where('user_id', $user->id);
        } elseif ($user && $user->role === 'AGENCY') {
            $query->whereRaw('1 = 0');
        }

        $rows = $query->groupBy(DB::raw('category'))
            ->orderBy('category')
            ->get();

        $total = max($rows->sum('count'), 1);

        return Cache::remember('insights.forecast.breach_probability', 3600, fn () => $rows->map(fn ($r) => [
            'category' => $r->category,
            'count' => (int) $r->count,
            'percentage' => round(($r->count / $total) * 100, 1),
        ])
        );
    }

    /**
     * Analyse peak case intake by day-of-week and hour.
     *
     * @return Collection<int, array{day_of_week: int, hour: int, case_count: int}>
     */
    public function getPeakPeriods(?User $user, int $monthsBack = 6): Collection
    {
        $from = now()->subMonths($monthsBack)->startOfDay();

        $query = DB::table('cases')
            ->select(
                DB::raw('EXTRACT(DOW FROM created_at) as day_of_week'),
                DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
                DB::raw('COUNT(*) as case_count')
            )
            ->where('is_deleted', false)
            ->where('created_at', '>=', $from);

        if ($user && $user->role === 'CASE_MANAGER') {
            $query->where('user_id', $user->id);
        } elseif ($user && $user->role === 'AGENCY') {
            $query->whereRaw('1 = 0');
        }

        return Cache::remember('insights.forecast.peak_periods', 3600, fn () => $query->groupBy(DB::raw('EXTRACT(DOW FROM created_at)'), DB::raw('EXTRACT(HOUR FROM created_at)'))
            ->orderBy('day_of_week')
            ->orderBy('hour')
            ->get()
            ->map(fn ($r) => [
                'day_of_week' => (int) $r->day_of_week,
                'hour' => (int) $r->hour,
                'case_count' => (int) $r->case_count,
            ])
        );
    }

    /**
     * Project workload per case-manager / agency over the next 4 weeks.
     *
     * @return Collection<int, array{name: string, current_active: int, weekly_rate: float, projected_load: float}>
     */
    public function getCapacityForecast(?User $user): Collection
    {
        $fourWeeksAgo = now()->subWeeks(4);

        $query = DB::table('cases')
            ->select(
                'cases.user_id',
                DB::raw('u.name'),
                DB::raw("COUNT(*) FILTER (WHERE cases.status = 'OPEN' AND cases.is_deleted = false) as current_active"),
                DB::raw("
                    ROUND(
                        COUNT(*) FILTER (WHERE cases.created_at >= '$fourWeeksAgo' AND cases.is_deleted = false)::numeric / 4.0,
                        1
                    ) as weekly_rate
                "),
                DB::raw("
                    ROUND(
                        (COUNT(*) FILTER (WHERE cases.status = 'OPEN' AND cases.is_deleted = false)::numeric
                        + (COUNT(*) FILTER (WHERE cases.created_at >= '$fourWeeksAgo' AND cases.is_deleted = false)::numeric / 4.0) * 4),
                        1
                    ) as projected_load
                ")
            )
            ->join('users as u', 'u.id', '=', 'cases.user_id')
            ->where('cases.is_deleted', false)
            ->groupBy('cases.user_id', 'u.name')
            ->orderByDesc(DB::raw('projected_load'));

        if ($user && $user->role === 'CASE_MANAGER') {
            $query->where('cases.user_id', $user->id);
        } elseif ($user && $user->role === 'AGENCY') {
            $query->whereRaw('1 = 0');
        } else {
            $query->limit(20);
        }

        return Cache::remember('insights.forecast.capacity_forecast', 3600, fn () => $query->get()->map(fn ($r) => [
            'name' => $r->name,
            'current_active' => (int) $r->current_active,
            'weekly_rate' => (float) $r->weekly_rate,
            'projected_load' => (float) $r->projected_load,
        ])
        );
    }
}
