<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportsFilterRequest;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService,
        private readonly ReportsExportService $reportsExportService,
    ) {}

    public function index(ReportsFilterRequest $request)
    {
        $user = $request->user();
        $filters = $request->validated();
        $fromDate = $filters['from'] ?? null;
        $toDate = $filters['to'] ?? null;
        $dateScope = $filters['date_scope'];
        $province = $filters['province'] ?? null;
        $city = $filters['city'] ?? null;

        // Resolve effective agency scope: AGENCY users are always locked to
        // their own agency; ADMIN and CASE_MANAGER may select one or view all.
        $effectiveAgencyId = match ($user?->role) {
            'AGENCY' => $user?->agency?->id,
            'ADMIN', 'CASE_MANAGER' => $filters['agency_id'] ?? null,
            default => null,
        };

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            agencyId: $effectiveAgencyId,
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
            agencyId: $effectiveAgencyId,
        );

        $cityOptions = $province
            ? $this->reportsService->getCityOptions(
                province: $province,
                userId: $user->id,
                role: $user->role,
                agencyId: $effectiveAgencyId,
            )
            : [];

        $agencyOptions = $this->reportsService->getAgencyOptions(
            userId: $user->id,
            role: $user->role,
        );

        return Inertia::render('Reports/Index', [
            // Eager props (included in initial response)
            'role' => $user->role,
            'agencyId' => $effectiveAgencyId,
            'agencyOptions' => $agencyOptions,
            'kpis' => $data['kpis'],
            'from' => $fromDate,
            'to' => $toDate,
            'dateScope' => $dateScope,
            'province' => $province,
            'city' => $city,
            'provinceOptions' => $provinceOptions,
            'cityOptions' => $cityOptions,
            // Reference rows (statuses/categories/issues) that drive the chart toggles.
            'referenceData' => $this->reportsService->getReferenceData(),

            // ── EAGER PROPS (filter-sensitive — re-fetched on every filter change) ──
            'referralStatusDistribution' => $data['referralStatusDistribution'] ?? null,
            'referralAgencyDistribution' => $data['referralAgencyDistribution'] ?? null,
            'casesOverTime' => $data['casesOverTime'] ?? null,
            'genderDistribution' => $data['genderDistribution'] ?? null,
            'ageGroupDistribution' => $data['ageGroupDistribution'] ?? null,
            'cycleTimeDistribution' => $data['cycleTimeDistribution'] ?? null,
            'referralAging' => $data['referralAging'] ?? null,
            'agencyScorecard' => $data['agencyScorecard'] ?? null,
            'geographicDistribution' => $data['geographicDistribution'] ?? null,
            'geographicMapData' => $data['geographicMapData'] ?? null,
            'caseIssueDistribution' => $data['caseIssueDistribution'] ?? null,
            'mostRequestedService' => $data['mostRequestedService'] ?? null,
            'overdueReferrals' => $data['overdueReferrals'] ?? null,
            'cityDistribution' => $data['cityDistribution'] ?? null,

            // ── DEFERRED PROPS (static data that doesn't change with filters) ──
            // Loaded in one background group after initial render.
            'clientTypeDistribution' => Inertia::defer(fn () => $data['clientTypeDistribution'] ?? null),
            'categoryDistribution' => Inertia::defer(fn () => $data['categoryDistribution'] ?? null),
            'employmentDistribution' => Inertia::defer(fn () => $data['employmentDistribution'] ?? null),
            'employmentPositionBreakdown' => Inertia::defer(fn () => $data['employmentPositionBreakdown'] ?? null),
            'caseStatusDistribution' => Inertia::defer(fn () => $data['caseStatusDistribution'] ?? null),
            'vulnerabilityDistribution' => Inertia::defer(fn () => $data['vulnerabilityDistribution'] ?? null),
            'overview' => Inertia::defer(fn () => $data['overview'] ?? null),
            'caseTrends' => Inertia::defer(fn () => $data['caseTrends'] ?? null),
            'agencyWorkload' => Inertia::defer(fn () => $data['agencyWorkload'] ?? null),
            'referralTrends' => Inertia::defer(fn () => $data['referralTrends'] ?? null),
            'avgReferralCompletion' => Inertia::defer(fn () => $data['avgReferralCompletion'] ?? null),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $criteria = $this->reportsExportService->extractCriteria($request);
        if ($criteria instanceof RedirectResponse) {
            return $criteria;
        }

        $filename = 'bayanihan-report-'.now()->format('Ymd-His').'.pdf';

        $document = \App\Models\GeneratedDocument::create([
            'user_id' => $request->user()->id,
            'type' => 'system_report_pdf',
            'filename' => $filename,
            'status' => 'pending',
        ]);

        \App\Jobs\GenerateSystemReport::dispatch(
            $criteria,
            $request->user()->id,
            $document->id,
        );

        return response()->json([
            'id' => $document->id,
            'status' => 'pending',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $criteria = $this->reportsExportService->extractCriteria($request);
        if ($criteria instanceof RedirectResponse) {
            return $criteria;
        }

        $filename = 'bayanihan-report-'.now()->format('Ymd-His').'.xlsx';

        $document = \App\Models\GeneratedDocument::create([
            'user_id' => $request->user()->id,
            'type' => 'reports_export',
            'filename' => $filename,
            'status' => 'pending',
        ]);

        \App\Jobs\ExportDataToExcel::dispatch(
            'reports_export',
            $criteria,
            $request->user()->id,
            $document->id,
        );

        return response()->json([
            'id' => $document->id,
            'status' => 'pending',
        ]);
    }
}
