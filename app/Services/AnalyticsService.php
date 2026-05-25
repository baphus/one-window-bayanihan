<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getOverview(): array
    {
        $totalCases = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();
        $openCases = CaseFile::where('status', 'OPEN')->count();
        $closedCases = CaseFile::where('status', 'CLOSED')->count();
        $totalReferrals = Referral::count();
        $pendingReferrals = Referral::where('status', 'PENDING')->count();
        $agencies = Agency::count();

        return [
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'activeAgencies' => $agencies,
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

    public function getReferralStatusDistribution(): array
    {
        $statuses = Referral::select(
            'status',
            DB::raw('count(*) as total')
        )
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $allStatuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];
        $labels = [];
        $data = [];
        $colors = [];

        $colorMap = [
            'PENDING' => '#f59e0b',
            'PROCESSING' => '#3b82f6',
            'FOR_COMPLIANCE' => '#f97316',
            'COMPLETED' => '#22c55e',
            'REJECTED' => '#ef4444',
        ];

        foreach ($allStatuses as $status) {
            $labels[] = $status;
            $data[] = (int) ($statuses[$status]->total ?? 0);
            $colors[] = $colorMap[$status];
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
        ];
    }

    public function getAgencyWorkload(): array
    {
        $workload = Agency::withCount('referrals')
            ->orderByDesc('referrals_count')
            ->get();

        return [
            'labels' => $workload->pluck('name')->toArray(),
            'data' => $workload->pluck('referrals_count')->toArray(),
        ];
    }

    public function getCaseTypeDistribution(): array
    {
        $types = CaseFile::select(
            'client_type',
            DB::raw('count(*) as total')
        )
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->groupBy('client_type')
            ->get()
            ->keyBy('client_type');

        return [
            'labels' => ['OFW', 'Next of Kin'],
            'data' => [
                (int) ($types['OFW']->total ?? 0),
                (int) ($types['NEXT_OF_KIN']->total ?? 0),
            ],
        ];
    }
}
