<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Requests\ReportsFilterRequest;
use App\Models\AuditLog;
use App\Services\Export\DataExportService;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'employmentOccupationBreakdown' => Inertia::defer(fn () => $data['employmentOccupationBreakdown'] ?? null),
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

        $guard = $this->guardAndAudit($request, $criteria, 'pdf');
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $payload = $this->reportsExportService->buildPdfPayloadFromCriteria($criteria);
        $filename = 'bayanihan-report-'.now()->format('Ymd-His').'.pdf';

        // Render before recording the outcome. `download()` is where dompdf
        // actually rasterises, so logging COMPLETED ahead of it would assert a
        // successful export for a document that never rendered.
        $pdf = Pdf::loadView('pdf.report', $payload);
        $output = $pdf->output();

        $this->recordExport($request, $criteria, 'pdf', 'COMPLETED', [
            'filename' => $filename,
            'bytes' => strlen($output),
            'row_counts' => $payload['metadata']['row_counts'] ?? [],
            'suppression_applied' => $payload['suppression']['applied'] ?? false,
        ]);

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $criteria = $this->reportsExportService->extractCriteria($request);
        if ($criteria instanceof RedirectResponse) {
            return $criteria;
        }

        $guard = $this->guardAndAudit($request, $criteria, 'xlsx');
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $exportService = new DataExportService;
        $sheets = $this->reportsExportService->buildExcelSheetsFromCriteria($criteria);
        $filename = 'bayanihan-report-'.now()->format('Ymd-His').'.xlsx';

        $response = $exportService->generateMultiSheet($sheets, $filename);

        // generateMultiSheet catches its own failures and answers 500, so the
        // outcome is only known after it returns. The workbook itself is
        // written inside the streamed callback, which is past the point where
        // any status can change — hence GENERATED rather than DELIVERED.
        $this->recordExport(
            $request,
            $criteria,
            'xlsx',
            $response->getStatusCode() === 200 ? 'COMPLETED' : 'FAILED',
            ['filename' => $filename, 'sheets' => count($sheets)],
        );

        return $response;
    }

    /**
     * Record the attempt, then refuse the export if it is too large to serve.
     *
     * The attempt is logged before the decision so a blocked or failed export
     * leaves the same evidence trail as a successful one — an audit record that
     * only ever shows successes cannot answer "who tried to pull this data".
     *
     * @return RedirectResponse|null Redirect when the export must not proceed.
     */
    private function guardAndAudit(Request $request, array $criteria, string $format): ?RedirectResponse
    {
        $preflight = $this->reportsExportService->preflight($criteria, $format);

        $this->recordExport($request, $criteria, $format, 'ATTEMPTED', ['preflight' => $preflight]);

        if (! $preflight['exceeds']) {
            return null;
        }

        $this->recordExport($request, $criteria, $format, 'BLOCKED', ['preflight' => $preflight]);

        return back()->with('error', sprintf(
            'This range matches %s records, above the %s limit of %s for a %s export. '
                .'Narrow the date range, or filter by agency, province or city, and try again.',
            number_format($preflight['largest']),
            $format === 'pdf' ? 'PDF' : 'Excel',
            number_format($preflight['limit']),
            strtoupper($format),
        ));
    }

    /**
     * Write one audit row for an export attempt, block, or completion.
     *
     * Records the actor, the full filter set, and the matched volumes. No
     * client-identifying data is involved — the exports carry aggregates and
     * case references only — so the log itself stays free of personal data.
     */
    /**
     * One correlation id per request, so the ATTEMPTED row and its outcome row
     * can be joined. Minting a fresh UUID per write — the pattern copied from
     * the other export controllers — left the two halves of the same export
     * unlinkable, which defeats the point of logging the attempt.
     */
    private function correlationId(Request $request): string
    {
        $existing = $request->attributes->get('correlation_id')
            ?? $request->header('X-Request-ID');

        if ($existing) {
            return (string) $existing;
        }

        if (! $request->attributes->has('export_correlation_id')) {
            $request->attributes->set('export_correlation_id', (string) Str::uuid());
        }

        return (string) $request->attributes->get('export_correlation_id');
    }

    private function recordExport(Request $request, array $criteria, string $format, string $outcome, array $context = []): void
    {
        $user = $request->user();

        AuditLog::create([
            'action' => AuditAction::EXPORT->value,
            'module' => AuditModule::DATA_EXPORT->value,
            'entity_id' => $user?->id,
            'description' => sprintf(
                '%s %s a reports %s export (%s to %s)',
                $user?->name ?? 'Unknown user',
                strtolower($outcome),
                strtoupper($format),
                $criteria['from'],
                $criteria['to'],
            ),
            'new_value' => [
                'outcome' => $outcome,
                'format' => $format,
                'role' => $criteria['role'],
                'filters' => [
                    'from' => $criteria['from'],
                    'to' => $criteria['to'],
                    'date_scope' => $criteria['dateScope'],
                    'province' => $criteria['province'] ?? 'All',
                    'city' => $criteria['city'] ?? 'All',
                    'agency_id' => $criteria['agency_id'] ?? 'All',
                ],
            ] + $context,
            'user_id' => $user?->id,
            'timestamp' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $this->correlationId($request),
        ]);
    }
}
