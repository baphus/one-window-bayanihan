<?php

namespace App\Http\Controllers;

use App\Services\Export\DataExportService;
use App\Services\ReportsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

use function Laravel\Ai\agent;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        $managedReferrals = $this->reportsService->getManagedReferrals(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        return Inertia::render('Reports/Index', [
            // Eager props (included in initial response)
            'role' => $user->role,
            'kpis' => $data['kpis'],
            'managedReferrals' => $managedReferrals,
            'from' => $fromDate,
            'to' => $toDate,

            // Deferred props (loaded on-demand by section components via partial reload)
            'referralStatusDistribution' => Inertia::defer(fn () => $data['referralStatusDistribution'] ?? null, 'referralStatusDistribution'),
            'referralAgencyDistribution' => Inertia::defer(fn () => $data['referralAgencyDistribution'] ?? null, 'referralAgencyDistribution'),
            'casesOverTime' => Inertia::defer(fn () => $data['casesOverTime'] ?? null, 'casesOverTime'),
            'genderDistribution' => Inertia::defer(fn () => $data['genderDistribution'] ?? null, 'genderDistribution'),
            'clientTypeDistribution' => Inertia::defer(fn () => $data['clientTypeDistribution'] ?? null, 'clientTypeDistribution'),
            'ageGroupDistribution' => Inertia::defer(fn () => $data['ageGroupDistribution'] ?? null, 'ageGroupDistribution'),
            'cycleTimeDistribution' => Inertia::defer(fn () => $data['cycleTimeDistribution'] ?? null, 'cycleTimeDistribution'),
            'referralAging' => Inertia::defer(fn () => $data['referralAging'] ?? null, 'referralAging'),
            'agencyScorecard' => Inertia::defer(fn () => $data['agencyScorecard'] ?? null, 'agencyScorecard'),
            'geographicDistribution' => Inertia::defer(fn () => $data['geographicDistribution'] ?? null, 'geographicDistribution'),
            'categoryDistribution' => Inertia::defer(fn () => $data['categoryDistribution'] ?? null, 'categoryDistribution'),
            'employmentDistribution' => Inertia::defer(fn () => $data['employmentDistribution'] ?? null, 'employmentDistribution'),
            'employmentPositionBreakdown' => Inertia::defer(fn () => $data['employmentPositionBreakdown'] ?? null, 'employmentPositionBreakdown'),
            'caseStatusDistribution' => Inertia::defer(fn () => $data['caseStatusDistribution'] ?? null, 'caseStatusDistribution'),
            'referralTypeDistribution' => Inertia::defer(fn () => $data['referralTypeDistribution'] ?? null, 'referralTypeDistribution'),
            'caseIssueDistribution' => Inertia::defer(fn () => $data['caseIssueDistribution'] ?? null, 'caseIssueDistribution'),
            'overview' => Inertia::defer(fn () => $data['overview'] ?? null, 'overview'),
            'caseTrends' => Inertia::defer(fn () => $data['caseTrends'] ?? null, 'caseTrends'),
            'agencyWorkload' => Inertia::defer(fn () => $data['agencyWorkload'] ?? null, 'agencyWorkload'),
            'referralTrends' => Inertia::defer(fn () => $data['referralTrends'] ?? null, 'referralTrends'),
            'avgReferralCompletion' => Inertia::defer(fn () => $data['avgReferralCompletion'] ?? null, 'avgReferralCompletion'),
            'mostRequestedService' => Inertia::defer(fn () => $data['mostRequestedService'] ?? null, 'mostRequestedService'),
        ]);
    }

    public function aiInsight(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        // Build compact KPI summary (~300 tokens instead of ~5000)
        $kpis = $data['kpis'] ?? [];
        $overview = $data['overview'] ?? [];

        // total_cases: prefer overview.totalCases, fallback to caseStatusDistribution sum
        $totalCases = (int) ($overview['totalCases'] ?? 0);
        if ($totalCases === 0 && isset($data['caseStatusDistribution']['data'])) {
            $totalCases = (int) array_sum($data['caseStatusDistribution']['data']);
        }

        // completion_rate: kpis stores it as 0-100, normalize to 0-1 float
        $completionRate = (float) ($kpis['completionRate'] ?? 0);
        if ($completionRate > 1) {
            $completionRate = round($completionRate / 100, 4);
        }

        // top_categories: sort by count desc, take 3
        $allCategories = $data['categoryDistribution'] ?? [];
        usort($allCategories, fn ($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
        $topCategories = array_map(
            fn ($c) => ['name' => $c['name'], 'count' => (int) ($c['count'] ?? 0)],
            array_slice($allCategories, 0, 3),
        );

        // top_agencies: sort by total desc, take 3
        $allAgencies = $data['agencyScorecard'] ?? [];
        usort($allAgencies, fn ($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        $topAgencies = array_map(
            fn ($a) => ['name' => $a['agency'] ?? '', 'referral_count' => (int) ($a['total'] ?? 0)],
            array_slice($allAgencies, 0, 3),
        );

        $summary = [
            'user_role' => $user->role,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'total_cases' => $totalCases,
            'avg_processing_days' => (float) ($kpis['avgCompletionDays'] ?? 0),
            'completion_rate' => $completionRate,
            'pending_referrals' => (int) ($kpis['pendingReferrals'] ?? 0),
            'overdue_referrals' => 0,
            'top_categories' => $topCategories,
            'top_agencies' => $topAgencies,
        ];

        if (! config('ai-chatbot.enabled', false)) {
            return response()->json(['insight' => null, 'error' => 'AI service is not configured. Configure it in System Settings.']);
        }

        try {
            $response = agent(
                instructions: 'You are a senior business intelligence analyst for the Department of Migrant Workers (DMW) Region VII. Analyze the report data and provide actionable insights. Be specific with numbers, identify trends, and suggest improvements. Keep responses to 3 paragraphs maximum.',
                temperature: 0.3,
                maxTokens: 1000,
            )->prompt(
                prompt: 'Here is the report data: '.json_encode($summary)."\n\nProvide a concise business insight summary (max 3 paragraphs) highlighting key metrics, trends, and actionable recommendations.",
                provider: config('ai-chatbot.provider', 'openai'),
                model: config('ai-chatbot.model', 'gpt-4o-mini'),
            );

            return response()->json(['insight' => $response->text]);
        } catch (\Exception $e) {
            Log::error('Reports AI analysis failed', ['message' => $e->getMessage(), 'exception' => $e]);

            return response()->json(['insight' => null, 'error' => 'AI analysis is temporarily unavailable. Please try again.']);
        }
    }

    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        $data['generatedAt'] = now()->format('Y-m-d H:i:s');
        $data['generatedBy'] = $user->name;

        $pdf = Pdf::loadView('pdf.report', $data);

        return $pdf->download('bayanihan-report-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        // Sheet 1: Overview — KPI metrics as key/value pairs
        $overviewRows = collect();
        foreach (($data['kpis'] ?? []) as $key => $value) {
            if ($key === 'kpiChanges') {
                continue;
            }
            $overviewRows->push(['metric' => ucwords(preg_replace('/([A-Z])/', ' $1', $key)), 'value' => (string) $value]);
        }
        foreach (($data['overview'] ?? []) as $key => $value) {
            $overviewRows->push(['metric' => ucwords(preg_replace('/([A-Z])/', ' $1', $key)), 'value' => (string) $value]);
        }
        $overviewColumnMap = [
            ['key' => 'metric', 'label' => 'Metric', 'type' => 'string'],
            ['key' => 'value',  'label' => 'Value',  'type' => 'string'],
        ];

        // Sheet 2: Referral Status
        $referralStatusRows = collect();
        $statusDist = $data['referralStatusDistribution'] ?? [];
        foreach (($statusDist['labels'] ?? []) as $i => $label) {
            $referralStatusRows->push(['status' => (string) $label, 'count' => (string) ($statusDist['data'][$i] ?? 0)]);
        }
        $referralStatusColumnMap = [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'count',  'label' => 'Count',  'type' => 'string'],
        ];

        // Sheet 3: Agency Scorecard
        $agencyScorecardRows = collect();
        foreach (($data['agencyScorecard'] ?? []) as $row) {
            $agencyScorecardRows->push([
                'agency' => (string) ($row['agency'] ?? ''),
                'total' => (string) ($row['total'] ?? 0),
                'completed' => (string) ($row['completed'] ?? 0),
                'pending' => (string) ($row['pending'] ?? 0),
                'completion_rate' => ($row['completionRate'] ?? 0).'%',
                'avg_days' => (string) ($row['avgDays'] ?? 0),
            ]);
        }
        $agencyScorecardColumnMap = [
            ['key' => 'agency',          'label' => 'Agency',          'type' => 'string'],
            ['key' => 'total',           'label' => 'Total Referrals', 'type' => 'string'],
            ['key' => 'completed',       'label' => 'Completed',       'type' => 'string'],
            ['key' => 'pending',         'label' => 'Pending',         'type' => 'string'],
            ['key' => 'completion_rate', 'label' => 'Completion Rate', 'type' => 'string'],
            ['key' => 'avg_days',        'label' => 'Avg. Days',       'type' => 'string'],
        ];

        // Sheet 4: Geographic
        $geographicRows = collect();
        $geoDist = $data['geographicDistribution'] ?? [];
        foreach (($geoDist['labels'] ?? []) as $i => $label) {
            $geographicRows->push(['province' => (string) $label, 'count' => (string) ($geoDist['data'][$i] ?? 0)]);
        }
        $geographicColumnMap = [
            ['key' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['key' => 'count',    'label' => 'Count',    'type' => 'string'],
        ];

        // Sheet 5: Categories
        $categoriesRows = collect();
        foreach (($data['categoryDistribution'] ?? []) as $cat) {
            $categoriesRows->push([
                'name' => (string) ($cat['name'] ?? ''),
                'count' => (string) ($cat['count'] ?? 0),
                'percentage' => ($cat['percentage'] ?? 0).'%',
            ]);
        }
        $categoriesColumnMap = [
            ['key' => 'name',       'label' => 'Category',   'type' => 'string'],
            ['key' => 'count',      'label' => 'Count',      'type' => 'string'],
            ['key' => 'percentage', 'label' => 'Percentage', 'type' => 'string'],
        ];

        // Sheet 6: Employment
        $employmentRows = collect();
        $empDist = $data['employmentDistribution'] ?? [];
        foreach (($empDist['labels'] ?? []) as $i => $label) {
            $employmentRows->push(['country' => (string) $label, 'count' => (string) ($empDist['data'][$i] ?? 0)]);
        }
        $employmentColumnMap = [
            ['key' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['key' => 'count',   'label' => 'Count',   'type' => 'string'],
        ];

        // Sheet 7: Cycle Time
        $cycleTimeRows = collect();
        $cycleDist = $data['cycleTimeDistribution'] ?? [];
        foreach (($cycleDist['labels'] ?? []) as $i => $label) {
            $cycleTimeRows->push(['range' => (string) $label, 'count' => (string) ($cycleDist['data'][$i] ?? 0)]);
        }
        $cycleTimeColumnMap = [
            ['key' => 'range', 'label' => 'Duration Range', 'type' => 'string'],
            ['key' => 'count', 'label' => 'Count',          'type' => 'string'],
        ];

        $sheets = [
            ['title' => 'Overview',         'columnMap' => $overviewColumnMap,        'rows' => $overviewRows],
            ['title' => 'Referral Status',  'columnMap' => $referralStatusColumnMap,  'rows' => $referralStatusRows],
            ['title' => 'Agency Scorecard', 'columnMap' => $agencyScorecardColumnMap, 'rows' => $agencyScorecardRows],
            ['title' => 'Geographic',       'columnMap' => $geographicColumnMap,      'rows' => $geographicRows],
            ['title' => 'Categories',       'columnMap' => $categoriesColumnMap,      'rows' => $categoriesRows],
            ['title' => 'Employment',       'columnMap' => $employmentColumnMap,      'rows' => $employmentRows],
            ['title' => 'Cycle Time',       'columnMap' => $cycleTimeColumnMap,       'rows' => $cycleTimeRows],
        ];

        $filename = 'bayanihan-report-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateMultiSheet($sheets, $filename);
    }
}
