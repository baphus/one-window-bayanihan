<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public function getAll(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $data = match ($role) {
            'AGENCY' => $this->getAgencyPayload($userId),
            'ADMIN' => $this->getAdminPayload($fromDate, $toDate),
            default => $this->getCaseManagerPayload($userId, $fromDate, $toDate),
        };

        $data['role'] = $role;

        return $data;
    }

    private function getCaseManagerPayload(
        ?string $userId,
        ?string $fromDate,
        ?string $toDate,
    ): array {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->getReferralKpis($userId, 'CASE_MANAGER', $from, $to),
            'referralStatusDistribution' => $this->getReferralStatusDistribution($userId, 'CASE_MANAGER', $from, $to),
            'referralAgencyDistribution' => $this->getReferralAgencyDistribution($userId, 'CASE_MANAGER', $from, $to),
            'casesOverTime' => $this->getCasesOverTime($userId, 'CASE_MANAGER', $from, $to),
            'genderDistribution' => $this->getGenderDistribution(),
            'clientTypeDistribution' => $this->getClientTypeDistribution($userId, 'CASE_MANAGER'),
            'ageGroupDistribution' => $this->getAgeGroupDistribution(),
            'mostRequestedService' => $this->getMostRequestedService($userId, 'CASE_MANAGER', $from, $to),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution($userId, 'CASE_MANAGER', $from, $to),
            'referralAging' => $this->getReferralAging($userId, 'CASE_MANAGER', $from, $to),
            'agencyScorecard' => $this->getAgencyScorecard($userId, 'CASE_MANAGER', $from, $to),
            'geographicDistribution' => $this->getGeographicDistribution($userId, 'CASE_MANAGER', $from, $to),
        ];
    }

    private function getAgencyPayload(?string $userId): array
    {
        $agency = User::find($userId)?->agency;
        $agcyId = $agency?->id;

        return [
            'kpis' => $this->getReferralKpis(null, 'AGENCY'),
            'referralStatusDistribution' => $this->getReferralStatusDistribution(null, 'AGENCY'),
            'referralTrends' => $this->getReferralTrends(),
            'avgReferralCompletion' => $this->getAvgReferralCompletionDays(),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution(null, 'AGENCY'),
            'agencyScorecard' => $agcyId
                ? $this->getAgencyScorecard(null, 'AGENCY')
                : [],
        ];
    }

    private function getAdminPayload(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'overview' => $this->getOverview($from, $to),
            'caseTrends' => $this->getCaseTrends(),
            'referralStatusDistribution' => $this->getReferralStatusDistribution(null, null, $from, $to),
            'agencyWorkload' => $this->getAgencyWorkload($from, $to),
            'clientTypeDistribution' => $this->getClientTypeDistribution(),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution(null, null, $from, $to),
            'referralAging' => $this->getReferralAging(null, null, $from, $to),
            'geographicDistribution' => $this->getGeographicDistribution(null, null, $from, $to),
            'agencyScorecard' => $this->getAgencyScorecard(null, null, $from, $to),
        ];
    }

    public function getOverview(?string $fromDate = null, ?string $toDate = null): array
    {
        $cases = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        if ($fromDate) {
            $cases->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $cases->whereDate('created_at', '<=', $toDate);
        }

        $referrals = Referral::query();
        if ($fromDate) {
            $referrals->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $referrals->whereDate('created_at', '<=', $toDate);
        }

        return [
            'totalCases' => (int) (clone $cases)->count(),
            'openCases' => (int) (clone $cases)->where('status', 'OPEN')->count(),
            'closedCases' => (int) (clone $cases)->where('status', 'CLOSED')->count(),
            'totalReferrals' => (int) (clone $referrals)->count(),
            'pendingReferrals' => (int) (clone $referrals)->where('status', 'PENDING')->count(),
            'activeAgencies' => (int) Agency::count(),
        ];
    }

    public function getCaseTrends(int $months = 12): array
    {
        $cases = CaseFile::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $cases->pluck('month')->toArray(),
            'data' => $cases->pluck('total')->toArray(),
        ];
    }

    public function getReferralTrends(int $months = 12): array
    {
        $referrals = Referral::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $referrals->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Referrals',
                    'data' => $referrals->pluck('total')->toArray(),
                    'borderColor' => '#0b5a8c',
                    'backgroundColor' => 'rgba(11, 90, 140, 0.1)',
                ],
            ],
        ];
    }

    public function getAgencyWorkload(?string $fromDate = null, ?string $toDate = null): array
    {
        $workload = Agency::withCount(['referrals' => function ($q) use ($fromDate, $toDate) {
            if ($fromDate) {
                $q->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $q->whereDate('created_at', '<=', $toDate);
            }
        }])
            ->orderByDesc('referrals_count')
            ->get();

        return [
            'labels' => $workload->pluck('name')->toArray(),
            'data' => $workload->pluck('referrals_count')->toArray(),
        ];
    }

    public function getClientTypeDistribution(?string $userId = null, ?string $role = null): array
    {
        $query = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('user_id', $userId);
        }

        $types = (clone $query)
            ->select('client_type', DB::raw('count(*) as total'))
            ->groupBy('client_type')
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

    private function caseQuery(?string $userId = null, ?string $role = null)
    {
        $query = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    private function referralQuery(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null)
    {
        $query = Referral::query();
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('case_id', CaseFile::where('user_id', $userId)->select('id'));
        }
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query;
    }

    public function getReferralKpis(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $from = Carbon::parse($fromDate ?: now()->subYear());
        $to = Carbon::parse($toDate ?: now());

        $referrals = $this->referralQuery($userId, $role, $from->toDateString(), $to->toDateString());
        $total = (clone $referrals)->count();
        $completed = (clone $referrals)->where('status', 'COMPLETED')->count();
        $pending = (clone $referrals)->where('status', 'PENDING')->count();
        $processing = (clone $referrals)->where('status', 'PROCESSING')->count();
        $rejected = (clone $referrals)->where('status', 'REJECTED')->count();

        $avgDays = (clone $referrals)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $duration = $from->diffInDays($to);
        $prevFrom = $from->copy()->subDays($duration);
        $prevTo = $from->copy()->subDay();

        $prev = $this->referralQuery($userId, $role, $prevFrom->toDateString(), $prevTo->toDateString());
        $prevTotal = (clone $prev)->count();
        $prevCompleted = (clone $prev)->where('status', 'COMPLETED')->count();
        $prevPending = (clone $prev)->where('status', 'PENDING')->count();
        $prevAvgDays = (clone $prev)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $pct = fn ($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

        return [
            'totalReferrals' => (int) $total,
            'completedReferrals' => (int) $completed,
            'pendingReferrals' => (int) $pending,
            'processingReferrals' => (int) $processing,
            'rejectedReferrals' => (int) $rejected,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'avgCompletionDays' => round((float) ($avgDays ?? 0), 1),
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

    public function getCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $cases = $this->caseQuery($userId, $role)
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', $fromDate ?: now()->subMonths(12))
            ->where('created_at', '<=', $toDate ?: now())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $cases->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases Created',
                    'data' => $cases->pluck('total')->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                ],
            ],
        ];
    }

    public function getGenderDistribution(): array
    {
        $genders = Client::select('sex', DB::raw('count(*) as total'))
            ->whereNotNull('sex')
            ->groupBy('sex')
            ->pluck('total', 'sex');

        $labels = [];
        $data = [];
        $colors = ['#6366f1', '#ec4899', '#94a3b8'];

        foreach (['MALE', 'FEMALE', 'OTHER'] as $i => $sex) {
            $labels[] = ucfirst(strtolower($sex));
            $data[] = (int) ($genders[$sex] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    }

    public function getAgeGroupDistribution(): array
    {
        $ages = Client::whereNotNull('date_of_birth')
            ->select(DB::raw("
                CASE
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) < 18 THEN '0-17'
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) BETWEEN 18 AND 25 THEN '18-25'
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) BETWEEN 26 AND 40 THEN '26-40'
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) BETWEEN 41 AND 60 THEN '41-60'
                    ELSE '60+'
                END as age_group
            "))
            ->selectRaw('count(*) as total')
            ->groupBy('age_group')
            ->pluck('total', 'age_group');

        $groups = ['0-17', '18-25', '26-40', '41-60', '60+'];
        $colors = ['#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#3730a3'];

        return [
            'labels' => $groups,
            'data' => array_map(fn ($g) => (int) ($ages[$g] ?? 0), $groups),
            'colors' => $colors,
        ];
    }

    public function getReferralStatusDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $statuses = $this->referralQuery($userId, $role, $fromDate, $toDate)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
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

    public function getReferralAgencyDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $agencies = $this->referralQuery($userId, $role, $fromDate, $toDate)
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

    public function getMostRequestedService(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $top = $this->referralQuery($userId, $role, $fromDate, $toDate)
            ->select('required_services', DB::raw('count(*) as total'))
            ->whereNotNull('required_services')
            ->groupBy('required_services')
            ->orderByDesc('total')
            ->first();

        return [
            'name' => $top?->required_services ?? 'N/A',
            'value' => (int) ($top?->total ?? 0),
        ];
    }

    public function getReferralCycleTimeDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $referrals = $this->referralQuery($userId, $role, $fromDate, $toDate)
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
    ): array {
        $referrals = $this->referralQuery($userId, $role, $fromDate, $toDate)
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
    ): array {
        $referrals = $this->referralQuery($userId, $role, $fromDate, $toDate)
            ->select('agcy_id', 'status', DB::raw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days'))
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

            $result[] = [
                'agency' => $agencyNames[$agcyId] ?? 'Unknown',
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'avgDays' => round((float) ($avgDays ?? 0), 1),
            ];
        }

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    public function getGeographicDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $query = CaseFile::select('ca.province', DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->whereNotNull('ca.province');

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('cases.user_id', $userId);
        }
        if ($fromDate) {
            $query->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('cases.created_at', '<=', $toDate);
        }

        $result = $query->groupBy('ca.province')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $result->pluck('province')->toArray(),
            'data' => $result->pluck('total')->toArray(),
        ];
    }

    public function getAvgReferralCompletionDays(): float
    {
        $avg = Referral::where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        return round((float) ($avg ?? 0), 1);
    }

    public function getManagedReferrals(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null)
    {
        return $this->referralQuery($userId, $role, $fromDate, $toDate)
            ->with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }
}
