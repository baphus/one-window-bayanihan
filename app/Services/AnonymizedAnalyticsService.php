<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class AnonymizedAnalyticsService
{
    /**
     * Get cases grouped by status (anonymized aggregate).
     */
    public function casesByStatus(): array
    {
        return CaseFile::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->toArray();
    }

    /**
     * Get cases grouped by client type as proxy for service (anonymized aggregate).
     * The cases table has no direct service_id FK; client_type (OFW / NEXT_OF_KIN)
     * serves as the primary case categorization dimension.
     */
    public function casesByService(): array
    {
        return CaseFile::select('client_type as service', DB::raw('count(*) as total'))
            ->groupBy('client_type')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    /**
     * Get case creation trend by month for the given year.
     */
    public function casesOverTime(?int $year = null): array
    {
        $year = $year ?: now()->year;

        $records = CaseFile::selectRaw(
            'EXTRACT(YEAR FROM created_at) as yr, EXTRACT(MONTH FROM created_at) as mo, count(*) as total'
        )
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->orderBy('yr')
            ->orderBy('mo')
            ->get()
            ->keyBy(fn ($r) => ((int) $r->yr).'-'.str_pad((string) ((int) $r->mo), 2, '0', STR_PAD_LEFT));

        $period = CarbonPeriod::create("{$year}-01-01", '1 month', "{$year}-12-01");
        $result = [];

        foreach ($period as $date) {
            $key = $date->format('Y-m');
            $result[] = [
                'month' => $key,
                'total' => (int) ($records[$key]->total ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Get average case resolution time (in days), anonymized.
     */
    public function averageResolutionTime(): array
    {
        $avg = CaseFile::whereNotNull('closed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (closed_at - created_at)) / 86400) as avg_days')
            ->value('avg_days');

        return [
            'average_days' => $avg ? round((float) $avg, 1) : null,
            'resolved_cases' => CaseFile::whereNotNull('closed_at')->count(),
        ];
    }

    /**
     * Get referral statistics (anonymized aggregate).
     */
    public function referralStats(): array
    {
        return [
            'total' => Referral::count(),
            'by_status' => Referral::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->toArray(),
            'avg_per_case' => round(
                CaseFile::whereHas('referrals')->withCount('referrals')->get()->avg('referrals_count') ?? 0,
                1
            ),
        ];
    }

    /**
     * Get cases grouped by category (anonymized aggregate, non-draft only).
     */
    public function casesByCategory(): array
    {
        return CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
            ->join('case_categories', 'cases.category_id', '=', 'case_categories.id')
            ->where('cases.status', '!=', 'DRAFT')
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    /**
     * Get total unique clients (anonymized count only).
     */
    public function totalClients(): int
    {
        return Client::count();
    }

    /**
     * Get all key metrics in a single call.
     */
    public function summary(): array
    {
        return [
            'cases_by_status' => $this->casesByStatus(),
            'cases_by_service' => $this->casesByService(),
            'cases_by_category' => $this->casesByCategory(),
            'cases_over_time' => $this->casesOverTime(),
            'average_resolution_time' => $this->averageResolutionTime(),
            'referral_stats' => $this->referralStats(),
            'total_clients' => $this->totalClients(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
