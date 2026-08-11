<?php

namespace App\Services\Reports;

use App\Services\ReportsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsExportService
{
    private const SCHEMA_VERSION = 'reports_export_v2';

    private const TZ = 'Asia/Manila';

    private const DEFAULT_ROW_CAP = 10000;

    private const PDF_TOP_N = 10;

    /**
     * Excel sheet titles an AGENCY export must not emit — not even as empty
     * sheets — because the agency's own report tabs do not show them.
     *
     * Categories and Case Status were previously on this list and should not
     * have been: getAgencyPayload() returns both, agency-scoped, and both
     * render on screen. They are now included in agency exports.
     */
    private const AGENCY_HIDDEN_SHEETS = [
        'Geography', 'Case Issues', 'Employment',
        'Cities', 'Agency Workload', 'Referrals by Agency', 'Occupations', 'Vulnerability',
    ];

    /**
     * Payload keys behind the newly added sections that an agency cannot see
     * on screen. Blanked for the same reason as AGENCY_HIDDEN_SHEETS.
     *
     * The rule this enforces is specific to AGENCY: an agency export must not
     * widen an agency's view beyond its own dashboard, because that dashboard
     * is the boundary of what one agency may see about the programme. It is
     * deliberately NOT applied to CASE_MANAGER — case managers already receive
     * cross-agency scorecards and overdue queues, so agency workload and
     * referrals-by-agency are the same class of information they are already
     * trusted with, and withholding them from a report they are entitled to
     * read would serve nobody.
     */
    private const AGENCY_HIDDEN_SECTIONS = [
        'cityDistribution', 'agencyWorkload', 'referralAgencyDistribution',
        'employmentOccupationBreakdown', 'vulnerabilityDistribution',
        // NOT overdueReferrals: agencies do see it, on the performance tab
        // (OverdueReferralsCard). Blanking it while leaving the sheet in place
        // printed a hard "0" on the agency's KPI card and Overdue sheet, which
        // is worse than omitting the section — the count is already scoped to
        // the agency's own referrals by referralBase().
    ];

    private const RISK = ['FOR_COMPLIANCE' => 40, 'PROCESSING' => 30, 'PENDING' => 20, 'OPEN' => 20];

    private const DATE_SCOPES = ['case_created_at', 'referral_created_at', 'referral_updated_at'];

    public function __construct(private readonly ReportsService $reports) {}

    public function buildPdfPayload(Request $request): array|RedirectResponse
    {
        $criteria = $this->extractCriteria($request);
        if ($criteria instanceof RedirectResponse) {
            return $criteria;
        }

        return $this->buildPayloadFromCriteria($criteria, false);
    }

    public function buildExcelSheets(Request $request): array|RedirectResponse
    {
        $criteria = $this->extractCriteria($request);
        if ($criteria instanceof RedirectResponse) {
            return $criteria;
        }

        $payload = $this->buildPayloadFromCriteria($criteria, true);

        return $payload['sheets'];
    }

    /**
     * Build PDF payload from pre-extracted criteria (for queue jobs).
     */
    public function buildPdfPayloadFromCriteria(array $criteria): array
    {
        return $this->buildPayloadFromCriteria($criteria, false);
    }

    /**
     * Build Excel sheets from pre-extracted criteria (for queue jobs).
     */
    public function buildExcelSheetsFromCriteria(array $criteria): array
    {
        $payload = $this->buildPayloadFromCriteria($criteria, true);

        return $payload['sheets'];
    }

    /**
     * Extract criteria from a Request into a serializable array.
     * This can be stored and passed to a queue job.
     */
    public function extractCriteria(Request $request): array|RedirectResponse
    {
        $today = CarbonImmutable::today(self::TZ);
        foreach (['from', 'to'] as $key) {
            $raw = $request->query($key);
            if ($raw !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $raw)) {
                return back()->with('error', ucfirst($key).' date must use YYYY-MM-DD.');
            }
        }
        try {
            $from = $request->query('from') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->query('from'), self::TZ) : $today->subYear();
            $to = $request->query('to') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->query('to'), self::TZ) : $today;
        } catch (\Throwable) {
            return back()->with('error', 'Invalid report date filter.');
        }
        if ($from->format('Y-m-d') !== ($request->query('from') ?: $from->format('Y-m-d')) || $to->format('Y-m-d') !== ($request->query('to') ?: $to->format('Y-m-d'))) {
            return back()->with('error', 'Invalid report date filter.');
        }
        if ($from->gt($to)) {
            return back()->with('error', 'From date must be before or equal to To date.');
        }
        if ($to->gt($from->addYears(2))) {
            return back()->with('error', 'Export date range cannot exceed 2 years.');
        }

        $user = $request->user();
        if (! in_array($user->role, ['ADMIN', 'CASE_MANAGER', 'AGENCY'], true)) {
            return back()->with('error', 'Your account is not allowed to export reports.');
        }

        $dateScope = $request->query('date_scope', 'case_created_at');
        if (! in_array($dateScope, self::DATE_SCOPES, true)) {
            $dateScope = 'case_created_at';
        }

        $agencyId = match ($user->role) {
            'AGENCY' => $user->agcy_id,
            'ADMIN', 'CASE_MANAGER' => $request->query('agency_id') ?: null,
            default => null,
        };

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'agency_id' => $agencyId,
            'user_agcy_id' => $user->agcy_id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'dateScope' => $dateScope,
            'province' => $request->query('province') ?: null,
            'city' => $request->query('city') ?: null,
        ];
    }

    /**
     * How much work would this export be, without doing it?
     *
     * Two counting queries, run before anything is built. Exports are
     * generated synchronously inside the request against a 60s timeout and a
     * 256M memory limit, so a range that cannot be served has to be refused
     * with an actionable message rather than attempted and failed — a 500 with
     * no explanation is what this endpoint used to do.
     *
     * Thresholds are per format because the two paths cost very differently:
     * see config/reports.php for the measurements they came from.
     */
    public function preflight(array $criteria, string $format): array
    {
        $c = $this->hydrate($criteria);

        $referrals = $this->referralBase($c)->count();
        $cases = $this->caseBase($c)->count();
        $largest = max($referrals, $cases);

        $limit = $format === 'pdf'
            ? (int) config('reports.pdf_preflight_max_rows', 30000)
            : (int) config('reports.export_preflight_max_rows', 6000);

        return [
            'format' => $format,
            'referrals' => $referrals,
            'cases' => $cases,
            'largest' => $largest,
            'limit' => $limit,
            'exceeds' => $limit > 0 && $largest > $limit,
        ];
    }

    /**
     * Hydrate the serializable criteria array into working values.
     *
     * The window bounds are deliberately built in the storage timezone, not in
     * the operating timezone the dates are typed in. Every on-screen report
     * query compares the stored timestamp against the calendar date directly
     * (ReportsService::applyCaseWindow, ::referralQuery — all whereDate), so
     * shifting the export's own bounds by the Manila offset gave one report two
     * different windows: the summary sections covered the whole calendar day
     * while the detail sheets, appendix, top-risk tables and pre-flight counts
     * stopped at 15:59:59Z, silently dropping everything created in the last
     * eight hours of the day. Same dates in, same rows out — the export's
     * numbers have to reconcile against the screen.
     *
     * Bounds rather than whereDate so the comparison stays a range scan the
     * created_at indexes can serve; endOfDay carries microseconds, so the
     * upper bound is inclusive of the whole final second.
     */
    private function hydrate(array $criteria): array
    {
        $storageTz = config('app.timezone', 'UTC');
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $criteria['from'], $storageTz);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $criteria['to'], $storageTz);

        return [
            'user_id' => $criteria['user_id'],
            'user_name' => $criteria['user_name'],
            'role' => $criteria['role'],
            'agency_id' => $criteria['agency_id'],
            'user_agcy_id' => $criteria['user_agcy_id'],
            'from' => $from,
            'to' => $to,
            'windowStart' => $from->startOfDay(),
            'windowEnd' => $to->endOfDay(),
            'dateScope' => $criteria['dateScope'],
            'province' => $criteria['province'],
            'city' => $criteria['city'],
        ];
    }

    private function buildPayloadFromCriteria(array $criteria, bool $withDetails): array
    {
        $c = $this->hydrate($criteria);

        $report = $this->reports->getAll(
            userId: $c['user_id'],
            role: $c['role'],
            agencyId: $c['agency_id'],
            fromDate: $c['from']->toDateString(),
            toDate: $c['to']->toDateString(),
            dateScope: $c['dateScope'],
            province: $c['province'],
            city: $c['city'],
        );

        $refBase = $this->referralBase($c);
        $caseBase = $this->caseBase($c);
        $refDetailCount = (clone $refBase)->count();
        $caseDetailCount = (clone $caseBase)->count();

        $rowCap = $this->rowCap();
        $refRows = $withDetails ? $this->referralRows($refBase)->limit($rowCap)->get() : collect();
        $caseRows = $withDetails ? $this->caseRows($caseBase)->limit($rowCap)->get() : collect();

        $warnings = [];
        if ($withDetails && $refDetailCount > $rowCap) {
            $warnings[] = 'Referral Details capped at '.$rowCap.' of '.$refDetailCount.' matching rows.';
        }
        if ($withDetails && $caseDetailCount > $rowCap) {
            $warnings[] = 'Case Details capped at '.$rowCap.' of '.$caseDetailCount.' matching rows.';
        }

        $summary = $this->summaryFromReport($report, $refBase, $caseBase, $c);
        $metadata = $this->buildMetadata($c, $refDetailCount, $caseDetailCount, $warnings, $withDetails);

        // Fetch only as deep as the appendix needs, then slice the headline
        // top-N off the front — same ordering, so the appendix reads as a
        // continuation of the summary. Totals come from a count, never from
        // the length of a hydrated collection.
        $appendixLimit = $this->appendixLimit();
        $rankedReferrals = $this->riskRows($this->referralRows($refBase), 'referral', $appendixLimit);
        $rankedCases = $this->riskRows($this->caseRows($caseBase), 'case', $appendixLimit);

        $payload = $summary + [
            'metadata' => $metadata,
            'capWarnings' => $warnings,
            'topReferrals' => $rankedReferrals->take($this->pdfTopN())->values()->all(),
            'topCases' => $rankedCases->take($this->pdfTopN())->values()->all(),
            'appendix' => [
                'limit' => $appendixLimit,
                'referrals' => $rankedReferrals->values()->all(),
                'cases' => $rankedCases->values()->all(),
                'referralsTotal' => $this->riskRowCount($this->referralRows($refBase), 'referral'),
                'casesTotal' => $this->riskRowCount($this->caseRows($caseBase), 'case'),
            ],
            'chartMaxCategories' => (int) config('reports.chart_max_categories', 15),
        ];
        $payload['sheets'] = $this->sheets($payload, $refRows, $caseRows, $c['role']);

        return $payload;
    }

    /**
     * Referral detail base — mirrors ReportsService::referralQuery semantics
     * (date scope + role scope) plus the same geo filter and soft-delete rule
     * the on-screen report uses.
     */
    private function referralBase(array $c)
    {
        $q = DB::table('referrals')
            ->whereNull('referrals.deleted_at')
            ->join('cases', 'cases.id', '=', 'referrals.case_id')
            ->whereNull('cases.deleted_at');

        if ($c['dateScope'] === 'case_created_at') {
            $q->whereBetween('cases.created_at', [$c['windowStart'], $c['windowEnd']]);
        } elseif ($c['dateScope'] === 'referral_created_at') {
            $q->whereBetween('referrals.created_at', [$c['windowStart'], $c['windowEnd']]);
        } else {
            $q->whereBetween('referrals.updated_at', [$c['windowStart'], $c['windowEnd']]);
        }

        if ($c['agency_id']) {
            $q->where('referrals.agcy_id', $c['agency_id']);
        } elseif ($c['role'] === 'AGENCY') {
            // Agency user without an assigned agency sees no data.
            $q->whereRaw('1=0');
        }

        $this->applyGeo($q, $c);

        return $q;
    }

    /**
     * Case detail base — mirrors ReportsService::caseQuery (excludes DRAFT and
     * ARCHIVED, which the previous export wrongly included) plus geo + soft-delete.
     */
    private function caseBase(array $c)
    {
        $q = DB::table('cases')
            ->whereNull('cases.deleted_at')
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->whereBetween('cases.created_at', [$c['windowStart'], $c['windowEnd']]);

        if ($c['agency_id']) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('referrals')->whereColumn('referrals.case_id', 'cases.id')->whereNull('referrals.deleted_at')->where('referrals.agcy_id', $c['agency_id']));
        } elseif ($c['role'] === 'AGENCY') {
            // Agency user without an assigned agency sees no data.
            $q->whereRaw('1=0');
        }

        $this->applyGeo($q, $c, 'cases');

        return $q;
    }

    /**
     * Apply the province/city geo filter, matching ReportsService::applyGeoFilter.
     */
    private function applyGeo($query, array $c, string $base = 'referrals'): void
    {
        if (! $c['province'] && ! $c['city']) {
            return;
        }

        $caseIdColumn = $base === 'cases' ? 'cases.id' : 'referrals.case_id';
        $query->whereIn($caseIdColumn, function ($q) use ($c) {
            $q->select('cases.id')->from('cases')
                ->join('clients', 'clients.id', '=', 'cases.client_id')
                ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
            if ($c['province']) {
                $q->where('client_addresses.province', $c['province']);
            }
            if ($c['city']) {
                $q->where('client_addresses.city_municipality', $c['city']);
            }
        });
    }

    private function referralRows($base)
    {
        return (clone $base)->leftJoin('agencies', 'agencies.id', '=', 'referrals.agcy_id')
            ->selectRaw("referrals.id as referral_id, referrals.case_id, cases.case_number, agencies.name as agency, referrals.required_services, referrals.status, referrals.created_at, CASE WHEN referrals.status = 'COMPLETED' THEN referrals.updated_at ELSE NULL END as completed_at, CASE WHEN referrals.status = 'COMPLETED' THEN DATE_PART('day', referrals.updated_at - referrals.created_at)::int ELSE NULL END as completion_days, DATE_PART('day', NOW() - referrals.created_at)::int as age_days")
            ->orderByDesc('referrals.created_at')->orderBy('referrals.id');
    }

    private function caseRows($base)
    {
        $category = "(SELECT STRING_AGG(DISTINCT cc.name, ', ' ORDER BY cc.name) FROM case_category ca JOIN case_categories cc ON cc.id = ca.case_category_id WHERE ca.case_id = cases.id)";

        return (clone $base)->leftJoin('case_issues', 'case_issues.id', '=', 'cases.case_issue_id')
            ->selectRaw("cases.id as case_id, cases.case_number, cases.client_type, {$category} as category, case_issues.name as issue, cases.status, cases.created_at, cases.updated_at, cases.closed_at, DATE_PART('day', NOW() - cases.created_at)::int as age_days")
            ->orderByDesc('cases.created_at')->orderBy('cases.id');
    }

    /**
     * Map the getAll() report payload into the shapes the PDF Blade and Excel
     * sheets consume. Trends are computed from the (identically filtered) detail
     * bases so both PDF and Excel always have month-by-month series regardless
     * of the role-specific keys getAll() returns.
     */
    private function summaryFromReport(array $report, $refBase, $caseBase, array $c): array
    {
        $role = $c['role'];
        $kpis = $report['kpis'] ?? [];

        // getAll agency scorecard uses `avgDays`; the Blade/Excel expect `avg_days`.
        $scorecard = collect($report['agencyScorecard'] ?? [])->map(function ($row) {
            $row = (array) $row;
            $row['avg_days'] = $row['avgDays'] ?? ($row['avg_days'] ?? null);

            return $row;
        })->all();

        $summary = [
            'kpis' => [
                'totalReferrals' => (int) ($kpis['totalReferrals'] ?? 0),
                'totalCases' => (int) ($kpis['totalCases'] ?? 0),
                'openCases' => (int) ($kpis['openCases'] ?? 0),
                'completedReferrals' => (int) ($kpis['completedReferrals'] ?? 0),
                'pendingReferrals' => (int) ($kpis['pendingReferrals'] ?? 0),
                'processingReferrals' => (int) ($kpis['processingReferrals'] ?? 0),
                'forComplianceReferrals' => (int) ($kpis['forComplianceReferrals'] ?? 0),
                'rejectedReferrals' => (int) ($kpis['rejectedReferrals'] ?? 0),
                'completionRate' => $kpis['completionRate'] ?? 0,
                'avgCompletionDays' => $kpis['avgCompletionDays'] ?? 0,
                'avgResolutionDays' => $kpis['avgResolutionDays'] ?? 0,
            ],
            'overview' => ['totalCases' => (int) ($kpis['totalCases'] ?? 0), 'totalReferrals' => (int) ($kpis['totalReferrals'] ?? 0)],
            'referralStatusDistribution' => $report['referralStatusDistribution'] ?? ['labels' => [], 'data' => []],
            'caseStatusDistribution' => $report['caseStatusDistribution'] ?? ['labels' => [], 'data' => []],
            'agencyScorecard' => $scorecard,
            'categoryDistribution' => $report['categoryDistribution'] ?? [],
            'caseIssueDistribution' => $report['caseIssueDistribution'] ?? [],
            'referralAging' => $report['referralAging'] ?? $this->agingFromBase($refBase),
            'cycleTimeDistribution' => $report['cycleTimeDistribution'] ?? ['labels' => [], 'data' => []],
            'geographicDistribution' => $report['geographicDistribution'] ?? ['labels' => [], 'data' => []],
            'employmentDistribution' => $report['employmentDistribution'] ?? ['labels' => [], 'data' => []],
            'caseTrends' => $this->trendFromBase($caseBase, 'cases.created_at'),
            'referralTrends' => $this->trendFromBase($refBase, 'referrals.created_at'),
        ];

        $summary += $this->additionalSections($c, $refBase);

        if ($role === 'AGENCY') {
            // Only the sections an agency actually sees belong in an agency
            // export. Empty these payload keys so the PDF's `!empty()` section
            // guards skip them and the Excel sheet list drops them.
            //
            // categoryDistribution and caseStatusDistribution used to be
            // blanked here on the stated grounds that "agencies cannot see
            // these sections on-screen". That was wrong:
            // ReportsService::getAgencyPayload() returns both, correctly
            // agency-scoped, and both render on the agency's tabs. Agencies
            // were losing data they are entitled to.
            $summary['caseIssueDistribution'] = [];
            $summary['geographicDistribution'] = ['labels' => [], 'data' => []];
            $summary['employmentDistribution'] = ['labels' => [], 'data' => []];

            foreach (self::AGENCY_HIDDEN_SECTIONS as $key) {
                $summary[$key] = is_array($summary[$key] ?? null) && array_key_exists('labels', $summary[$key])
                    ? ['labels' => [], 'data' => []]
                    : [];
            }
        }

        return $this->suppressSmallCells($summary);
    }

    /**
     * The report sections that render on screen but were never exported.
     *
     * getAll() returns a different key set per role, so anything a role's
     * payload omits is fetched directly here with the identical filter set.
     * Without this the exports carried roughly half of what the Reports page
     * shows, and a reader could not reconcile the document against the screen.
     */
    private function additionalSections(array $c, $refBase): array
    {
        $role = $c['role'] === 'CASE_MANAGER' ? 'CASE_MANAGER' : ($c['role'] === 'AGENCY' ? 'AGENCY' : null);
        $userId = $c['role'] === 'CASE_MANAGER' ? $c['user_id'] : null;
        $from = $c['from']->toDateString();
        $to = $c['to']->toDateString();
        $scope = $c['dateScope'];
        $prov = $c['province'];
        $city = $c['city'];
        $agency = $c['agency_id'];

        // Fail closed for an AGENCY user with no agency assigned.
        //
        // getAll() short-circuits that state to an empty payload, but these
        // panels are called directly and so bypass that guard. Two of them —
        // gender and age group — resolve their client set through
        // filteredClientIds(), which has no role check of its own: with a null
        // agency it returns every client in the system. referralBase() and
        // caseBase() already `1=0` for exactly this case, so the state is
        // reachable, and without this an export would show a user data their
        // own dashboard refuses them.
        if ($role === 'AGENCY' && ! $agency) {
            return $this->emptyAdditionalSections();
        }

        return [
            'referralFunnel' => $this->funnelFromStatuses($this->reports->getReferralStatusDistribution($userId, $role, $from, $to, $scope, $prov, $city, $agency)),
            'casesOverTime' => $this->reports->getCasesOverTime($userId, $role, $from, $to, $scope, $prov, $city, $agency),
            'genderDistribution' => $this->reports->getGenderDistribution($userId, $role, $from, $to, $scope, $prov, $city, $agency),
            'ageGroupDistribution' => $this->reports->getAgeGroupDistribution($userId, $role, $from, $to, $scope, $prov, $city, $agency),
            'vulnerabilityDistribution' => $this->reports->getVulnerabilityDistribution($userId, $role, $agency, $from, $to, $prov, $city),
            'clientTypeDistribution' => $this->reports->getClientTypeDistribution($userId, $role, $agency, $from, $to, $prov, $city),
            'cityDistribution' => $this->reports->getCityDistribution($userId, $role, $from, $to, $scope, $prov, $city, $agency),
            'agencyWorkload' => $this->reports->getAgencyWorkload($from, $to, $agency),
            'referralAgencyDistribution' => $this->reports->getReferralAgencyDistribution($userId, $role, $from, $to, $scope, $prov, $city, $agency),
            'employmentOccupationBreakdown' => $this->reports->getEmploymentOccupationBreakdown($userId, $role, $agency, $from, $to, $prov, $city),
            'overdueReferrals' => $this->overdueFromBase($refBase),
            'mostRequestedService' => $this->reports->getMostRequestedService($userId, $role, $from, $to, $scope, $prov, $city, $agency),
        ];
    }

    /**
     * The additional sections, all empty. Shape-compatible with the real
     * thing so the Blade guards and sheet builders behave identically.
     *
     * @return array<string, mixed>
     */
    private function emptyAdditionalSections(): array
    {
        $emptyDist = ['labels' => [], 'data' => []];

        return [
            'referralFunnel' => ['stages' => [], 'total' => 0],
            'casesOverTime' => ['labels' => [], 'datasets' => [['data' => []]]],
            'genderDistribution' => $emptyDist,
            'ageGroupDistribution' => $emptyDist,
            'vulnerabilityDistribution' => $emptyDist,
            'clientTypeDistribution' => $emptyDist,
            'cityDistribution' => $emptyDist,
            'agencyWorkload' => $emptyDist,
            'referralAgencyDistribution' => $emptyDist,
            'employmentOccupationBreakdown' => $emptyDist,
            'overdueReferrals' => ['count' => 0, 'threshold_days' => 14],
            'mostRequestedService' => ['name' => 'N/A', 'value' => 0],
        ];
    }

    /**
     * Overdue referral count, computed from the export's own filtered base.
     *
     * ReportsService::getOverdueReferrals() is not reusable here for two
     * reasons: it eager-loads caseFile.client, which would pull client PII
     * into an export that deliberately carries none, and it takes no date
     * range, so its figure would not match the window printed on the page.
     * The >14 day threshold matches the on-screen definition.
     */
    private function overdueFromBase($refBase): array
    {
        $count = (clone $refBase)
            ->whereIn('referrals.status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->whereRaw('EXTRACT(EPOCH FROM (NOW() - referrals.created_at)) / 86400 > 14')
            ->count();

        return ['count' => (int) $count, 'threshold_days' => 14];
    }

    /**
     * The referral funnel as an ordered stage list with conversion rates.
     *
     * On screen this is a component; in a document it has to be self-describing,
     * so each stage carries the count and its share of the intake total.
     */
    private function funnelFromStatuses(array $dist): array
    {
        $counts = collect($dist['labels'] ?? [])
            ->mapWithKeys(fn ($label, $i) => [$label => (int) ($dist['data'][$i] ?? 0)]);

        $total = (int) $counts->sum();
        $order = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];

        $stages = collect($order)
            ->filter(fn ($stage) => $counts->has($stage))
            ->map(fn ($stage) => [
                'stage' => $stage,
                'count' => $counts[$stage],
                'share' => $total > 0 ? round(($counts[$stage] / $total) * 100, 1) : 0.0,
            ])->values()->all();

        return ['stages' => $stages, 'total' => $total];
    }

    /**
     * Suppress small cells in the special-category sections.
     *
     * Gender, age band, vulnerability, client type and employment country are
     * special-category personal data under the Data Privacy Act. A bucket of
     * one or two people in a narrowly filtered export is re-identifiable, so
     * any bucket below the configured threshold is withheld — for every role
     * including ADMIN, because a role-conditional privacy rule is the first
     * thing an assessor pulls on.
     *
     * Suppressed buckets are removed from the chart series and reported in
     * `suppression` so the document can say what was withheld rather than
     * silently showing a shorter chart.
     */
    private function suppressSmallCells(array $summary): array
    {
        $threshold = max(0, (int) config('reports.suppression_threshold', 5));
        $sections = (array) config('reports.suppressed_sections', []);

        if ($threshold <= 1) {
            $summary['suppression'] = ['threshold' => $threshold, 'applied' => false, 'sections' => []];

            return $summary;
        }

        $affected = [];

        foreach ($sections as $key) {
            $section = $summary[$key] ?? null;
            if (! is_array($section) || ! isset($section['labels'], $section['data'])) {
                continue;
            }

            $labels = [];
            $data = [];
            $colors = [];
            $withheld = 0;

            foreach ($section['labels'] as $i => $label) {
                $value = (int) ($section['data'][$i] ?? 0);

                // Zero is not disclosive — an empty bucket identifies nobody.
                if ($value > 0 && $value < $threshold) {
                    $withheld++;

                    continue;
                }

                $labels[] = $label;
                $data[] = $value;
                if (isset($section['colors'][$i])) {
                    $colors[] = $section['colors'][$i];
                }
            }

            // Complement suppression.
            //
            // Withholding exactly one bucket does not protect it when the
            // denominator is published elsewhere: clientTypeDistribution has
            // only two buckets and the same case set is printed as "Total
            // Cases" on the Executive Summary, so withholding OFW=3 while
            // showing Next of Kin=97 against a total of 100 discloses the
            // withheld figure by subtraction. Whenever exactly one bucket is
            // withheld, withhold the next-smallest non-zero one too, so no
            // single value can be recovered.
            if ($withheld === 1) {
                $smallest = null;
                foreach ($data as $index => $value) {
                    if ($value > 0 && ($smallest === null || $value < $data[$smallest])) {
                        $smallest = $index;
                    }
                }

                if ($smallest !== null) {
                    array_splice($labels, $smallest, 1);
                    array_splice($data, $smallest, 1);
                    if ($colors !== []) {
                        array_splice($colors, $smallest, 1);
                    }
                    $withheld++;
                }
            }

            if ($withheld > 0) {
                $affected[$key] = $withheld;
            }

            $summary[$key] = ['labels' => $labels, 'data' => $data] + ($colors ? ['colors' => $colors] : []);
        }

        $summary['suppression'] = [
            'threshold' => $threshold,
            'applied' => $affected !== [],
            'sections' => $affected,
        ];

        return $summary;
    }

    /**
     * Referral aging buckets computed from the identically filtered referral
     * base. Mirrors ReportsService::getReferralAging so agency exports can
     * include this section (it renders on the agency performance tab) even
     * though the AGENCY payload omits the key.
     */
    private function agingFromBase($base): array
    {
        $buckets = ['< 1 week' => 0, '1-2 weeks' => 0, '2-4 weeks' => 0, '> 1 month' => 0];

        $days = (clone $base)
            ->whereIn('referrals.status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->selectRaw('EXTRACT(EPOCH FROM (NOW() - referrals.created_at)) / 86400 as days')
            ->get()
            ->pluck('days');

        foreach ($days as $d) {
            if ($d < 7) {
                $buckets['< 1 week']++;
            } elseif ($d < 14) {
                $buckets['1-2 weeks']++;
            } elseif ($d < 30) {
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

    private function trendFromBase($base, string $column): array
    {
        $rows = (clone $base)
            ->selectRaw("to_char($column, 'YYYY-MM') as label, count(*) as count")
            ->groupBy('label')->orderBy('label')->get();

        return ['labels' => $rows->pluck('label')->all(), 'data' => $rows->pluck('count')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * The highest-risk active rows, ranked and limited in SQL.
     *
     * Previously this pulled every active row with `->get()`, scored and
     * sorted them in PHP, and then kept the top handful. On a wide range that
     * materialises tens of thousands of objects to keep 200 of them — the same
     * unbounded-fetch shape that made the Excel export exhaust memory. Ranking
     * in SQL keeps the working set proportional to what is actually rendered.
     *
     * @param  int|null  $limit  Rows to return; null means all (used for counting).
     */
    private function riskRows($query, string $type, ?int $limit = null): Collection
    {
        $statusColumn = $type === 'case' ? 'cases.status' : 'referrals.status';
        $createdColumn = $type === 'case' ? 'cases.created_at' : 'referrals.created_at';
        $idColumn = $type === 'case' ? 'cases.id' : 'referrals.id';
        $active = $type === 'case' ? ['OPEN'] : ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

        // Same score as before: status severity plus age in days. Expressed in
        // SQL so the database can order and limit before anything is hydrated.
        $cases = [];
        $bindings = [];
        foreach (self::RISK as $status => $weight) {
            $cases[] = "WHEN ? THEN {$weight}";
            $bindings[] = $status;
        }
        $riskExpression = 'CASE '.$statusColumn.' '.implode(' ', $cases).' ELSE 0 END'
            ." + DATE_PART('day', NOW() - {$createdColumn})::int";

        $ranked = $query->whereIn($statusColumn, $active)
            ->selectRaw("({$riskExpression}) as risk_score", $bindings)
            ->reorder()
            ->orderByDesc('risk_score')
            ->orderBy($createdColumn)
            ->orderBy($idColumn);

        if ($limit !== null) {
            $ranked->limit($limit);
        }

        return $ranked->get();
    }

    /**
     * How many active rows exist, without hydrating them.
     */
    private function riskRowCount($query, string $type): int
    {
        $statusColumn = $type === 'case' ? 'cases.status' : 'referrals.status';
        $active = $type === 'case' ? ['OPEN'] : ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

        return (int) $query->whereIn($statusColumn, $active)->count();
    }

    private function rowCap(): int
    {
        return max(1, (int) config('reports.export_row_cap', self::DEFAULT_ROW_CAP));
    }

    private function appendixLimit(): int
    {
        return max(1, (int) config('reports.pdf_appendix_rows', 200));
    }

    private function pdfTopN(): int
    {
        return max(1, (int) config('reports.pdf_top_n', self::PDF_TOP_N));
    }

    private function buildMetadata(array $c, int $refCount, int $caseCount, array $warnings, bool $withDetails): array
    {
        $utc = CarbonImmutable::now('UTC');
        $rowCap = $this->rowCap();
        $rowCounts = ['referral_details_matching' => $refCount, 'case_details_matching' => $caseCount];
        if ($withDetails) {
            $rowCounts['referral_details_exported'] = min($refCount, $rowCap);
            $rowCounts['case_details_exported'] = min($caseCount, $rowCap);
        } else {
            $rowCounts['pdf_top_referrals_limit'] = $this->pdfTopN();
            $rowCounts['pdf_top_cases_limit'] = $this->pdfTopN();
            $rowCounts['pdf_appendix_limit'] = $this->appendixLimit();
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at_utc' => $utc->toIso8601String(),
            'generated_at_manila' => $utc->setTimezone(self::TZ)->toDateTimeString(),
            'generated_by' => $c['user_name'],
            'scope' => $c['role'],
            'timezone' => self::TZ,
            'filters' => [
                'from' => $c['from']->toDateString(),
                'to' => $c['to']->toDateString(),
                'date_scope' => $c['dateScope'],
                'province' => $c['province'] ?? 'All',
                'city' => $c['city'] ?? 'All',
                'agency_id' => $c['agency_id'] ?? 'All',
            ],
            'row_counts' => $rowCounts,
            'row_cap' => $rowCap,
            'cap_warnings' => $warnings,
            'ai_insights_included' => false,
            'source' => $withDetails ? 'reports_excel_export' : 'reports_pdf_export',
        ];
    }

    /**
     * Build the workbook: one sheet per report section, in the same order the
     * PDF presents them, so the two documents can be read side by side.
     *
     * Column types are declared truthfully — counts as `int`, rates as
     * `percent`, timestamps as `datetime`. Every column used to be declared
     * `string`, which meant DataExportService wrote the entire workbook as
     * text: no sorting, no SUM, no pivot tables. Identifiers stay `uuid` (i.e.
     * forced text) so Excel cannot mangle them into scientific notation.
     *
     * `chart` marks the sheets that get a native Excel chart. Only the
     * headline sections carry one — the rest stay data-only.
     */
    private function sheets(array $p, Collection $refs, Collection $cases, string $role): array
    {
        $kv = [
            ['key' => 'metric', 'label' => 'Metric', 'type' => 'string'],
            ['key' => 'value', 'label' => 'Value', 'type' => 'string'],
        ];
        $dist = [
            ['key' => 'label', 'label' => 'Label', 'type' => 'string'],
            ['key' => 'count', 'label' => 'Count', 'type' => 'int'],
        ];
        $named = fn (string $label) => [
            ['key' => 'name', 'label' => $label, 'type' => 'string'],
            ['key' => 'count', 'label' => 'Count', 'type' => 'int'],
        ];
        $distRows = fn ($d) => collect($d['labels'] ?? [])
            ->map(fn ($l, $i) => ['label' => $l, 'count' => (int) ($d['data'][$i] ?? 0)]);

        $refCols = [
            ['key' => 'referral_id', 'label' => 'Referral ID', 'type' => 'uuid'],
            ['key' => 'case_id', 'label' => 'Case ID', 'type' => 'uuid'],
            ['key' => 'case_number', 'label' => 'Case Number', 'type' => 'string'],
            ['key' => 'agency', 'label' => 'Agency', 'type' => 'string'],
            ['key' => 'required_services', 'label' => 'Required Services', 'type' => 'string'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
            ['key' => 'created_at', 'label' => 'Created At', 'type' => 'datetime'],
            ['key' => 'completed_at', 'label' => 'Completed At', 'type' => 'datetime'],
            ['key' => 'completion_days', 'label' => 'Completion Days', 'type' => 'int'],
            ['key' => 'age_days', 'label' => 'Age Days', 'type' => 'int'],
        ];
        $caseCols = [
            ['key' => 'case_id', 'label' => 'Case ID', 'type' => 'uuid'],
            ['key' => 'case_number', 'label' => 'Case Number', 'type' => 'string'],
            ['key' => 'client_type', 'label' => 'Client Type', 'type' => 'string'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'string'],
            ['key' => 'issue', 'label' => 'Issue', 'type' => 'string'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
            ['key' => 'created_at', 'label' => 'Created At', 'type' => 'datetime'],
            ['key' => 'updated_at', 'label' => 'Updated At', 'type' => 'datetime'],
            ['key' => 'closed_at', 'label' => 'Closed At', 'type' => 'datetime'],
            ['key' => 'age_days', 'label' => 'Age Days', 'type' => 'int'],
        ];

        $sheets = [
            ['title' => 'Report Info', 'columnMap' => $kv, 'rows' => $this->metadataRows($p)],
            ['title' => 'Data Dictionary', 'columnMap' => [
                ['key' => 'sheet', 'label' => 'Sheet', 'type' => 'string'],
                ['key' => 'column', 'label' => 'Column', 'type' => 'string'],
                ['key' => 'meaning', 'label' => 'Meaning', 'type' => 'string'],
                ['key' => 'units', 'label' => 'Units', 'type' => 'string'],
            ], 'rows' => $this->dataDictionaryRows()],
            ['title' => 'Executive Summary', 'columnMap' => [
                ['key' => 'metric', 'label' => 'Metric', 'type' => 'string'],
                ['key' => 'value', 'label' => 'Value', 'type' => 'float'],
                ['key' => 'units', 'label' => 'Units', 'type' => 'string'],
            ], 'rows' => $this->kpiRows($p['kpis'] ?? [])],

            ['title' => 'Referral Funnel', 'columnMap' => [
                ['key' => 'stage', 'label' => 'Stage', 'type' => 'string'],
                ['key' => 'count', 'label' => 'Referrals', 'type' => 'int'],
                ['key' => 'share', 'label' => 'Share', 'type' => 'percent'],
            ], 'rows' => collect($p['referralFunnel']['stages'] ?? []), 'chart' => 'bar'],
            ['title' => 'Referral Status', 'columnMap' => $dist, 'rows' => $distRows($p['referralStatusDistribution']), 'chart' => 'pie'],
            ['title' => 'Referral Aging', 'columnMap' => $dist, 'rows' => $distRows($p['referralAging']), 'chart' => 'bar'],
            ['title' => 'Cycle Time', 'columnMap' => $dist, 'rows' => $distRows($p['cycleTimeDistribution']), 'chart' => 'bar'],
            ['title' => 'Overdue Referrals', 'columnMap' => $kv, 'rows' => collect([
                ['metric' => 'Overdue referrals', 'value' => (string) ($p['overdueReferrals']['count'] ?? 0)],
                ['metric' => 'Overdue after (days)', 'value' => (string) ($p['overdueReferrals']['threshold_days'] ?? 14)],
            ])],
            ['title' => 'Most Requested Service', 'columnMap' => $kv, 'rows' => collect([
                ['metric' => 'Service', 'value' => (string) ($p['mostRequestedService']['name'] ?? 'N/A')],
                ['metric' => 'Requests', 'value' => (string) ($p['mostRequestedService']['value'] ?? 0)],
            ])],

            ['title' => 'Trends', 'columnMap' => [
                ['key' => 'period', 'label' => 'Period', 'type' => 'string'],
                ['key' => 'cases', 'label' => 'Cases', 'type' => 'int'],
                ['key' => 'referrals', 'label' => 'Referrals', 'type' => 'int'],
            ], 'rows' => $this->trendRows($p['caseTrends'], $p['referralTrends']), 'chart' => 'line'],
            ['title' => 'Cases Over Time', 'columnMap' => [
                ['key' => 'period', 'label' => 'Period', 'type' => 'string'],
                ['key' => 'count', 'label' => 'Cases', 'type' => 'int'],
            ], 'rows' => $this->seriesRows($p['casesOverTime'] ?? []), 'chart' => 'line'],

            ['title' => 'Agency Scorecard', 'columnMap' => [
                ['key' => 'agency', 'label' => 'Agency', 'type' => 'string'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'int'],
                ['key' => 'completed', 'label' => 'Completed', 'type' => 'int'],
                ['key' => 'pending', 'label' => 'Pending', 'type' => 'int'],
                ['key' => 'avg_days', 'label' => 'Avg Days', 'type' => 'float'],
            ], 'rows' => collect($p['agencyScorecard']), 'chart' => 'bar'],
            ['title' => 'Agency Workload', 'columnMap' => $dist, 'rows' => $distRows($p['agencyWorkload'] ?? [])],
            ['title' => 'Referrals by Agency', 'columnMap' => $dist, 'rows' => $distRows($p['referralAgencyDistribution'] ?? [])],

            ['title' => 'Geography', 'columnMap' => $dist, 'rows' => $distRows($p['geographicDistribution']), 'chart' => 'bar'],
            ['title' => 'Cities', 'columnMap' => $dist, 'rows' => $distRows($p['cityDistribution'] ?? [])],
            ['title' => 'Categories', 'columnMap' => $named('Category'), 'rows' => collect($p['categoryDistribution'])],
            ['title' => 'Case Issues', 'columnMap' => $named('Issue'), 'rows' => collect($p['caseIssueDistribution'])],
            ['title' => 'Case Status', 'columnMap' => $dist, 'rows' => $distRows($p['caseStatusDistribution']), 'chart' => 'pie'],

            ['title' => 'Gender', 'columnMap' => $dist, 'rows' => $distRows($p['genderDistribution'] ?? [])],
            ['title' => 'Age Groups', 'columnMap' => $dist, 'rows' => $distRows($p['ageGroupDistribution'] ?? [])],
            ['title' => 'Vulnerability', 'columnMap' => $dist, 'rows' => $distRows($p['vulnerabilityDistribution'] ?? [])],
            ['title' => 'Client Type', 'columnMap' => $dist, 'rows' => $distRows($p['clientTypeDistribution'] ?? [])],
            ['title' => 'Employment', 'columnMap' => $dist, 'rows' => $distRows($p['employmentDistribution'])],
            ['title' => 'Occupations', 'columnMap' => $dist, 'rows' => $distRows($p['employmentOccupationBreakdown'] ?? [])],

            ['title' => 'Referral Details', 'columnMap' => $refCols, 'rows' => $refs],
            ['title' => 'Case Details', 'columnMap' => $caseCols, 'rows' => $cases],
        ];

        if ($role === 'AGENCY') {
            $sheets = array_values(array_filter(
                $sheets,
                fn ($sheet) => ! in_array($sheet['title'], self::AGENCY_HIDDEN_SHEETS, true)
            ));
        }

        return $sheets;
    }

    /**
     * Metadata as flat rows, including the suppression notice.
     */
    private function metadataRows(array $p): Collection
    {
        // Values are passed through unchanged apart from array flattening.
        // Casting to string here turned `false` into an empty cell, which read
        // as "unknown" rather than "no" for flags like ai_insights_included.
        $rows = collect($p['metadata'])
            ->map(fn ($v, $k) => ['metric' => $k, 'value' => is_array($v) ? json_encode($v) : $v])
            ->values();

        $suppression = $p['suppression'] ?? null;
        if (is_array($suppression)) {
            $rows->push([
                'metric' => 'small_cell_suppression',
                'value' => $suppression['applied']
                    ? 'Applied: buckets below '.$suppression['threshold'].' withheld in '.implode(', ', array_keys($suppression['sections']))
                    : 'Threshold '.$suppression['threshold'].'; no bucket required suppression',
            ]);
        }

        return $rows;
    }

    /**
     * KPIs with their units spelled out, so a rate is never mistaken for a count.
     */
    private function kpiRows(array $kpis): Collection
    {
        $units = [
            'completionRate' => '%',
            'avgCompletionDays' => 'days',
            'avgResolutionDays' => 'days',
        ];

        return collect($kpis)->map(fn ($v, $k) => [
            'metric' => ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $k))),
            'value' => is_numeric($v) ? (float) $v : 0,
            'units' => $units[$k] ?? 'count',
        ])->values();
    }

    /**
     * Flatten a Chart.js-shaped {labels, datasets:[{data}]} series into rows.
     */
    private function seriesRows(array $series): Collection
    {
        $data = $series['datasets'][0]['data'] ?? ($series['data'] ?? []);

        return collect($series['labels'] ?? [])
            ->map(fn ($label, $i) => ['period' => $label, 'count' => (int) ($data[$i] ?? 0)]);
    }

    /**
     * Column definitions for the reader.
     *
     * Every derived or ambiguous column is defined here, because "age days"
     * and "completion days" are counted from different anchors and nothing on
     * the sheet itself says so.
     *
     * Timestamp columns and the trend month are labelled UTC because that is
     * what they are: values are written as stored and the date range filters on
     * the stored value, so labelling them Asia/Manila invited a reader to
     * mis-place rows by eight hours. Only the generation stamp on Report Info
     * is rendered in the operating timezone.
     */
    private function dataDictionaryRows(): Collection
    {
        return collect([
            ['Referral Details', 'Age Days', 'Days from referral creation to now, for referrals that are still active', 'days'],
            ['Referral Details', 'Completion Days', 'Days from referral creation to completion; blank unless status is COMPLETED', 'days'],
            ['Referral Details', 'Completed At', 'Timestamp the referral reached COMPLETED; blank for any other status', 'UTC'],
            ['Case Details', 'Age Days', 'Days from case creation to now', 'days'],
            ['Case Details', 'Category', 'All categories assigned to the case, comma separated', 'text'],
            ['Case Details', 'Closed At', 'Timestamp the case was closed; blank while open', 'UTC'],
            ['Executive Summary', 'Completion Rate', 'Completed referrals as a share of all referrals in range', '%'],
            ['Executive Summary', 'Avg Resolution Days', 'Mean days from case creation to closure, closed cases only', 'days'],
            ['Referral Funnel', 'Share', 'Stage count as a share of all referrals in range', '%'],
            ['Referral Aging', 'Label', 'Age band of referrals still awaiting action', 'band'],
            ['Cycle Time', 'Label', 'Elapsed time band for completed referrals', 'band'],
            ['Overdue Referrals', 'Overdue referrals', 'Active referrals older than the threshold, within the selected filters', 'count'],
            ['Trends', 'Period', 'Calendar month, YYYY-MM, in UTC', 'month'],
            ['All sheets', 'Timestamps', 'Created At, Updated At, Completed At and Closed At are UTC, as stored; the selected date range filters the same values', 'UTC'],
            ['Gender / Age Groups / Vulnerability / Client Type / Employment', 'Count', 'Buckets below the suppression threshold are withheld — see Report Info', 'count'],
            ['All sheets', 'Scope', 'Every figure honours the date range, date scope, province, city and agency filters shown on Report Info', 'n/a'],
        ])->map(fn ($r) => ['sheet' => $r[0], 'column' => $r[1], 'meaning' => $r[2], 'units' => $r[3]]);
    }

    private function trendRows(array $caseTrends, array $refTrends): Collection
    {
        $cases = collect($caseTrends['labels'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => (int) ($caseTrends['data'][$i] ?? 0)]);
        $refs = collect($refTrends['labels'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => (int) ($refTrends['data'][$i] ?? 0)]);

        return $cases->keys()->merge($refs->keys())->unique()->sort()->values()
            ->map(fn ($period) => ['period' => $period, 'cases' => (int) ($cases[$period] ?? 0), 'referrals' => (int) ($refs[$period] ?? 0)]);
    }
}
