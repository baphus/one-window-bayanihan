<?php

namespace App\Services\Reports;

use App\Models\Agency;
use App\Models\CaseEvent;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Services\Reports\Concerns\ScopesReportQueries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralMetrics
{
    use ScopesReportQueries;

    public function getReferralKpis(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $from = Carbon::parse($fromDate ?: now()->subYear());
        $to = Carbon::parse($toDate ?: now());

        $referrals = $this->referralQuery($userId, $role, $agencyId, $from->toDateString(), $to->toDateString(), $dateScope);
        $this->applyGeoFilter($referrals, $province, $city);

        $cases = $this->caseQuery($userId, $role, $agencyId, $dateScope)
            ->whereDate('cases.created_at', '>=', $from->toDateString())
            ->whereDate('cases.created_at', '<=', $to->toDateString());
        $this->applyGeoFilter($cases, $province, $city, 'cases');

        $statusCounts = (clone $referrals)
            ->select('referrals.status', DB::raw('count(*) as cnt'))
            ->groupBy('referrals.status')
            ->pluck('cnt', 'status');
        $total = $statusCounts->sum();
        $totalCases = (clone $cases)->distinct('cases.id')->count('cases.id');
        $openCases = (clone $cases)->where('cases.status', 'OPEN')->distinct('cases.id')->count('cases.id');
        $completed = (int) ($statusCounts['COMPLETED'] ?? 0);
        $pending = (int) ($statusCounts['PENDING'] ?? 0);
        $processing = (int) ($statusCounts['PROCESSING'] ?? 0);
        $forCompliance = (int) ($statusCounts['FOR_COMPLIANCE'] ?? 0);
        $rejected = (int) ($statusCounts['REJECTED'] ?? 0);

        $avgDays = (clone $referrals)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        // Accurate case resolution time uses the real close timestamp (closed_at),
        // not updated_at which is corrupted by any later edit.
        $avgResolutionDays = (clone $cases)
            ->where('cases.status', 'CLOSED')
            ->whereNotNull('cases.closed_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (cases.closed_at - cases.created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $duration = $from->diffInDays($to);
        $prevFrom = $from->copy()->subDays($duration);
        $prevTo = $from->copy()->subDay();

        $prev = $this->referralQuery($userId, $role, $agencyId, $prevFrom->toDateString(), $prevTo->toDateString(), $dateScope);
        $this->applyGeoFilter($prev, $province, $city);

        $prevStatusCounts = (clone $prev)
            ->select('referrals.status', DB::raw('count(*) as cnt'))
            ->groupBy('referrals.status')
            ->pluck('cnt', 'status');
        $prevTotal = $prevStatusCounts->sum();
        $prevCompleted = (int) ($prevStatusCounts['COMPLETED'] ?? 0);
        $prevPending = (int) ($prevStatusCounts['PENDING'] ?? 0);
        $prevAvgDays = (clone $prev)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $pct = fn ($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

        return [
            'totalReferrals' => (int) $total,
            'totalCases' => (int) $totalCases,
            'openCases' => (int) $openCases,
            'completedReferrals' => (int) $completed,
            'pendingReferrals' => (int) $pending,
            'processingReferrals' => (int) $processing,
            'forComplianceReferrals' => (int) $forCompliance,
            'rejectedReferrals' => (int) $rejected,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'avgCompletionDays' => round((float) ($avgDays ?? 0), 1),
            'avgResolutionDays' => round((float) ($avgResolutionDays ?? 0), 1),
            'kpiChanges' => [
                'totalReferrals' => $pct($total, $prevTotal),
                'completedReferrals' => $pct($completed, $prevCompleted),
                'pendingReferrals' => $pct($pending, $prevPending),
                'completionRate' => $pct(
                    $total > 0 ? ($completed / $total) * 100 : 0,
                    $prevTotal > 0 ? ($prevCompleted / $prevTotal) * 100 : 0,
                ),
                'avgCompletionDays' => $pct((float) ($avgDays ?? 0), (float) ($prevAvgDays ?? 0)),
            ],
        ];
    }

    public function getReferralTrends(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        $referrals = $this->referralQuery($userId, $role, $agencyId, $from, $to, $dateScope);
        $this->applyGeoFilter($referrals, $province, $city);

        $referrals = $referrals->select(
            DB::raw("to_char(referrals.created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $referrals->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Referrals Created',
                    'data' => $referrals->pluck('total')->toArray(),
                    'borderColor' => '#0b5a8c',
                    'backgroundColor' => 'rgba(11, 90, 140, 0.1)',
                ],
            ],
        ];
    }

    public function getReferralStatusDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $statuses = (clone $query)
            ->select('referrals.status', DB::raw('count(*) as total'))
            ->groupBy('referrals.status')
            ->pluck('total', 'status');

        $allStatuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];
        $colorMap = [
            'PENDING' => '#f59e0b',
            'PROCESSING' => '#3b82f6',
            'FOR_COMPLIANCE' => '#f97316',
            'COMPLETED' => '#22c55e',
            'REJECTED' => '#ef4444',
        ];

        return [
            'labels' => $allStatuses,
            'data' => array_map(fn ($s) => (int) ($statuses[$s] ?? 0), $allStatuses),
            'colors' => array_map(fn ($s) => $colorMap[$s], $allStatuses),
        ];
    }

    public function getRejectionReasonDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $reasons = (clone $query)
            ->where('referrals.status', 'REJECTED')
            ->select('referrals.rejection_reason', DB::raw('count(*) as total'))
            ->groupBy('referrals.rejection_reason')
            ->pluck('total', 'rejection_reason');

        $allReasons = Referral::REJECTION_REASONS;
        $colorMap = [
            'INCOMPLETE_REQUIREMENTS' => '#f59e0b',
            'OUTSIDE_MANDATE' => '#8b5cf6',
            'DUPLICATE_REFERRAL' => '#6b7280',
            'CLIENT_WITHDREW' => '#3b82f6',
            'NO_SERVICE_CAPACITY' => '#ef4444',
            'OTHER' => '#94a3b8',
        ];

        return [
            'labels' => $allReasons,
            'data' => array_map(fn ($r) => (int) ($reasons[$r] ?? 0), $allReasons),
            'colors' => array_map(fn ($r) => $colorMap[$r], $allReasons),
        ];
    }

    public function getReferralAgencyDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $agencies = (clone $query)
            ->select('agcy_id', DB::raw('count(*) as total'))
            ->groupBy('agcy_id')
            ->orderByDesc('total')
            ->get();

        $agencyNames = Agency::whereIn('id', $agencies->pluck('agcy_id'))->pluck('name', 'id');

        $colors = ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c', '#4338ca', '#0891b2', '#65a30d'];

        return [
            'labels' => $agencies->map(fn ($r) => $agencyNames[$r->agcy_id] ?? 'Unknown')->toArray(),
            'data' => $agencies->pluck('total')->toArray(),
            'colors' => array_slice($colors, 0, $agencies->count()),
        ];
    }

    public function getMostRequestedService(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $top = (clone $query)
            ->join('referral_services', 'referral_services.referral_id', '=', 'referrals.id')
            ->join('services', 'services.id', '=', 'referral_services.service_id')
            ->select('services.name', DB::raw('count(*) as total'))
            ->groupBy('services.name')
            ->orderByDesc('total')
            ->first();

        return [
            'name' => $top?->name ?? 'N/A',
            'value' => (int) ($top?->total ?? 0),
        ];
    }

    public function getReferralCycleTimeDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $referrals = (clone $query)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days'))
            ->get()
            ->pluck('days');

        $buckets = ['< 1 week' => 0, '1-2 weeks' => 0, '2-4 weeks' => 0, '> 1 month' => 0];
        foreach ($referrals as $days) {
            if ($days < 7) {
                $buckets['< 1 week']++;
            } elseif ($days < 14) {
                $buckets['1-2 weeks']++;
            } elseif ($days < 30) {
                $buckets['2-4 weeks']++;
            } else {
                $buckets['> 1 month']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
            'colors' => ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        ];
    }

    public function getReferralAging(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $referrals = (clone $query)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->select(DB::raw('EXTRACT(EPOCH FROM (NOW() - created_at)) / 86400 as days'))
            ->get()
            ->pluck('days');

        $buckets = ['< 1 week' => 0, '1-2 weeks' => 0, '2-4 weeks' => 0, '> 1 month' => 0];
        foreach ($referrals as $days) {
            if ($days < 7) {
                $buckets['< 1 week']++;
            } elseif ($days < 14) {
                $buckets['1-2 weeks']++;
            } elseif ($days < 30) {
                $buckets['2-4 weeks']++;
            } else {
                $buckets['> 1 month']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
            'colors' => ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        ];
    }

    public function getAgencyScorecard(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $referrals = (clone $query)
            ->select('agcy_id', 'status', 'created_at', DB::raw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days'))
            ->get()
            ->groupBy('agcy_id');

        if ($referrals->isEmpty()) {
            return [];
        }

        $agencyIds = $referrals->keys();
        $agencyNames = Agency::whereIn('id', $agencyIds)->pluck('name', 'id');

        $result = [];
        foreach ($referrals as $agcyId => $rows) {
            $total = $rows->count();
            $completed = $rows->where('status', 'COMPLETED')->count();
            $pending = $rows->where('status', 'PENDING')->count();
            $avgDays = $rows->where('status', 'COMPLETED')->avg('days');
            // Same >14d active-referral rule as getOverdueReferrals().
            // Absolute is explicit: Carbon 3 diffs are signed by default.
            $overdue = $rows->filter(fn ($row) => in_array($row->status, ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'], true)
                && $row->created_at && now()->diffInDays($row->created_at, true) > 14)->count();

            $result[] = [
                'agency' => $agencyNames[$agcyId] ?? 'Unknown',
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
                'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'avgDays' => round((float) ($avgDays ?? 0), 1),
            ];
        }

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    public function getAgencyWorkload(?string $fromDate = null, ?string $toDate = null, ?string $agencyId = null): array
    {
        $workload = Agency::withCount(['referrals' => function ($q) use ($fromDate, $toDate, $agencyId) {
            if ($fromDate) {
                $q->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $q->whereDate('created_at', '<=', $toDate);
            }
            if ($agencyId) {
                $q->where('agcy_id', $agencyId);
            }
        }])
            ->orderByDesc('referrals_count')
            ->get();

        return [
            'labels' => $workload->pluck('name')->toArray(),
            'data' => $workload->pluck('referrals_count')->toArray(),
        ];
    }

    public function getAgencyFirstResponse(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        // Only referrals that have left PENDING could have been accepted.
        $referrals = (clone $query)
            ->where('referrals.status', '!=', 'PENDING')
            ->select('referrals.id', 'referrals.agcy_id', 'referrals.created_at')
            ->get();

        if ($referrals->isEmpty()) {
            return [];
        }

        // First PENDING→PROCESSING transition per referral, read from the
        // append-only event log: referral timestamps cannot show it because
        // updated_at moves on every later edit. Aggregated in PostgreSQL
        // (MIN per referral over the same scoped referral set) instead of
        // hydrating every event and grouping in PHP. Same value as before:
        // the earliest PROCESSING event per referral (sequence only broke
        // ties within one timestamp, which cannot move the day-resolution
        // median below).
        $firstAccepts = CaseEvent::where('type', CaseEvent::TYPE_REFERRAL_STATUS_CHANGED)
            ->whereIn('referral_id', (clone $query)->where('referrals.status', '!=', 'PENDING')->select('referrals.id'))
            ->where('meta->to', 'PROCESSING')
            ->select('referral_id', DB::raw('MIN(occurred_at) as first_accept_at'))
            ->groupBy('referral_id')
            ->pluck('first_accept_at', 'referral_id');

        $daysByAgency = [];
        foreach ($referrals as $referral) {
            $acceptAt = $firstAccepts->get($referral->id);
            if (! $acceptAt || ! $referral->created_at) {
                continue;
            }
            $hours = $referral->created_at->diffInHours(Carbon::parse($acceptAt), false);
            if ($hours < 0) {
                continue;
            }
            $daysByAgency[$referral->agcy_id][] = round($hours / 24, 1);
        }

        if (empty($daysByAgency)) {
            return [];
        }

        $agencyNames = Agency::whereIn('id', array_keys($daysByAgency))->pluck('name', 'id');

        $result = [];
        foreach ($daysByAgency as $agcyId => $days) {
            sort($days);
            $count = count($days);
            $median = $count % 2 === 1
                ? $days[intdiv($count, 2)]
                : round(($days[$count / 2 - 1] + $days[$count / 2]) / 2, 1);
            $result[] = [
                'agency' => $agencyNames[$agcyId] ?? 'Unknown',
                'medianDays' => $median,
                'samples' => $count,
            ];
        }

        usort($result, fn ($a, $b) => $a['medianDays'] <=> $b['medianDays']);

        return $result;
    }

    public function getClientRequestTypeDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $types = ReferralClientRequest::whereIn('referral_id', (clone $query)->select('referrals.id'))
            ->where('referral_client_requests.is_deleted', false)
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $allTypes = [
            ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
            ReferralClientRequest::TYPE_QUESTION,
            ReferralClientRequest::TYPE_INFORMATION_UPDATE,
        ];

        return [
            'labels' => ['Document request', 'Question', 'Information update'],
            'data' => array_map(fn ($type) => (int) ($types[$type] ?? 0), $allTypes),
            'colors' => ['#0b5a8c', '#0891b2', '#059669'],
        ];
    }

    public function getAvgReferralCompletionDays(?string $role = null, ?string $agencyId = null): float
    {
        $avg = Referral::where('status', 'COMPLETED');
        if ($agencyId) {
            $avg->where('agcy_id', $agencyId);
        }
        $avg = $avg->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        return round((float) ($avg ?? 0), 1);
    }

    public function getOverdueReferrals(?string $userId = null, ?string $role = null, ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = Referral::whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->whereRaw('EXTRACT(EPOCH FROM (NOW() - created_at)) / 86400 > 14');

        if ($agencyId) {
            $query->where('agcy_id', $agencyId);
        }

        if ($province || $city) {
            $query->whereIn('case_id', function ($q) use ($province, $city) {
                $q->select('cases.id')->from('cases')
                    ->join('clients', 'clients.id', '=', 'cases.client_id')
                    ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
                if ($province) {
                    $q->where('client_addresses.province', $province);
                }
                if ($city) {
                    $q->where('client_addresses.city_municipality', $city);
                }
            });
        }

        $count = (clone $query)->count();

        $referrals = $query->with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        return [
            'count' => $count,
            'referrals' => $referrals,
        ];
    }
}
