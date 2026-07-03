<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

use function Laravel\Ai\agent;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService,
        private readonly ReportsExportService $reportsExportService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');
        $dateScope = $request->query('date_scope', 'case_created_at');
        $province = $request->query('province');
        $city = $request->query('city');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
            dateScope: $dateScope,
            province: $province,
            city: $city,
        );

        $managedReferrals = $this->reportsService->getManagedReferrals(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
            dateScope: $dateScope,
            province: $province,
            city: $city,
        );

        // Gather filter options
        $provinceOptions = $this->reportsService->getProvinceOptions(
            userId: $user->id,
            role: $user->role,
        );

        $cityOptions = $province
            ? $this->reportsService->getCityOptions(
                province: $province,
                userId: $user->id,
                role: $user->role,
            )
            : [];

        return Inertia::render('Reports/Index', [
            // Eager props (included in initial response)
            'role' => $user->role,
            'kpis' => $data['kpis'],
            'managedReferrals' => $managedReferrals,
            'from' => $fromDate,
            'to' => $toDate,
            'dateScope' => $dateScope,
            'province' => $province,
            'city' => $city,
            'provinceOptions' => $provinceOptions,
            'cityOptions' => $cityOptions,

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
            // NEW deferred props
            'overdueReferrals' => Inertia::defer(fn () => $data['overdueReferrals'] ?? null, 'overdueReferrals'),
            'cityDistribution' => Inertia::defer(fn () => $data['cityDistribution'] ?? null, 'cityDistribution'),
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
        $data = $this->reportsExportService->buildPdfPayload($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $pdf = Pdf::loadView('pdf.report', $data);

        return $pdf->download('bayanihan-report-'.now()->format('Ymd-His').'.pdf');
    }
}
