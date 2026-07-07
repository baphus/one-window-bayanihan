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

    private const ROW_CAP = 10000;

    private const PDF_TOP_N = 10;

    private const RISK = ['FOR_COMPLIANCE' => 40, 'PROCESSING' => 30, 'PENDING' => 20, 'OPEN' => 20];

    private const DATE_SCOPES = ['case_created_at', 'referral_created_at', 'referral_updated_at'];

    public function __construct(private readonly ReportsService $reports) {}

    public function buildPdfPayload(Request $request): array|RedirectResponse
    {
        return $this->buildPayload($request, false);
    }

    public function buildExcelSheets(Request $request): array|RedirectResponse
    {
        $payload = $this->buildPayload($request, true);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        return $payload['sheets'];
    }

    private function buildPayload(Request $request, bool $withDetails): array|RedirectResponse
    {
        $criteria = $this->criteria($request);
        if ($criteria instanceof RedirectResponse) {
            return $criteria;
        }

        // Single source of truth: the exact same computed dataset as the
        // on-screen report, honoring all five filters (date range, date scope,
        // province, city, role). Summary/KPI/distribution sections are mapped
        // from this so an export can never diverge from the screen.
        $report = $this->reports->getAll(
            userId: $criteria['user']->id,
            role: $criteria['role'],
            fromDate: $criteria['from']->toDateString(),
            toDate: $criteria['to']->toDateString(),
            dateScope: $criteria['dateScope'],
            province: $criteria['province'],
            city: $criteria['city'],
        );

        // Detail rows / risk tables use bases that mirror the same filter set.
        $refBase = $this->referralBase($criteria);
        $caseBase = $this->caseBase($criteria);
        $refCount = (int) ($report['kpis']['totalReferrals'] ?? 0);
        $caseCount = (int) ($report['kpis']['totalCases'] ?? 0);
        $refDetailCount = (clone $refBase)->count();
        $caseDetailCount = (clone $caseBase)->count();

        $refRows = $withDetails ? $this->referralRows($refBase)->limit(self::ROW_CAP)->get() : collect();
        $caseRows = $withDetails ? $this->caseRows($caseBase)->limit(self::ROW_CAP)->get() : collect();

        $warnings = [];
        if ($withDetails && $refDetailCount > self::ROW_CAP) {
            $warnings[] = 'Referral Details capped at '.self::ROW_CAP.' of '.$refDetailCount.' matching rows.';
        }
        if ($withDetails && $caseDetailCount > self::ROW_CAP) {
            $warnings[] = 'Case Details capped at '.self::ROW_CAP.' of '.$caseDetailCount.' matching rows.';
        }

        $summary = $this->summaryFromReport($report, $refBase, $caseBase);
        $metadata = $this->metadata($request, $criteria, $refDetailCount, $caseDetailCount, $warnings, $withDetails);

        $payload = $summary + [
            'metadata' => $metadata,
            'capWarnings' => $warnings,
            'topReferrals' => $this->riskRows($this->referralRows($refBase), 'referral')->take(self::PDF_TOP_N)->values()->all(),
            'topCases' => $this->riskRows($this->caseRows($caseBase), 'case')->take(self::PDF_TOP_N)->values()->all(),
        ];
        $payload['sheets'] = $this->sheets($payload, $refRows, $caseRows);

        return $payload;
    }

    private function criteria(Request $request): array|RedirectResponse
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

        return [
            'user' => $user,
            'role' => $user->role,
            'agency_id' => $user->agcy_id,
            'from' => $from,
            'to' => $to,
            'fromInstant' => $from->startOfDay()->utc(),
            'toInstant' => $to->endOfDay()->utc(),
            'dateScope' => $dateScope,
            'province' => $request->query('province') ?: null,
            'city' => $request->query('city') ?: null,
        ];
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
            $q->whereBetween('cases.created_at', [$c['fromInstant'], $c['toInstant']]);
        } elseif ($c['dateScope'] === 'referral_created_at') {
            $q->whereBetween('referrals.created_at', [$c['fromInstant'], $c['toInstant']]);
        } else {
            $q->whereBetween('referrals.updated_at', [$c['fromInstant'], $c['toInstant']]);
        }

        if ($c['role'] === 'CASE_MANAGER') {
            $q->where('cases.user_id', $c['user']->id);
        }
        if ($c['role'] === 'AGENCY') {
            $c['agency_id'] ? $q->where('referrals.agcy_id', $c['agency_id']) : $q->whereRaw('1=0');
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
            ->whereBetween('cases.created_at', [$c['fromInstant'], $c['toInstant']]);

        if ($c['role'] === 'CASE_MANAGER') {
            $q->where('cases.user_id', $c['user']->id);
        }
        if ($c['role'] === 'AGENCY') {
            $c['agency_id']
                ? $q->whereExists(fn ($s) => $s->selectRaw('1')->from('referrals')->whereColumn('referrals.case_id', 'cases.id')->whereNull('referrals.deleted_at')->where('referrals.agcy_id', $c['agency_id']))
                : $q->whereRaw('1=0');
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
        return (clone $base)->leftJoin('case_categories', 'case_categories.id', '=', 'cases.category_id')->leftJoin('case_issues', 'case_issues.id', '=', 'cases.case_issue_id')
            ->selectRaw("cases.id as case_id, cases.case_number, cases.client_type, case_categories.name as category, case_issues.name as issue, cases.status, cases.created_at, cases.updated_at, cases.closed_at, DATE_PART('day', NOW() - cases.created_at)::int as age_days")
            ->orderByDesc('cases.created_at')->orderBy('cases.id');
    }

    /**
     * Map the getAll() report payload into the shapes the PDF Blade and Excel
     * sheets consume. Trends are computed from the (identically filtered) detail
     * bases so both PDF and Excel always have month-by-month series regardless
     * of the role-specific keys getAll() returns.
     */
    private function summaryFromReport(array $report, $refBase, $caseBase): array
    {
        $kpis = $report['kpis'] ?? [];

        // getAll agency scorecard uses `avgDays`; the Blade/Excel expect `avg_days`.
        $scorecard = collect($report['agencyScorecard'] ?? [])->map(function ($row) {
            $row = (array) $row;
            $row['avg_days'] = $row['avgDays'] ?? ($row['avg_days'] ?? null);

            return $row;
        })->all();

        return [
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
            'referralAging' => $report['referralAging'] ?? ['labels' => [], 'data' => []],
            'cycleTimeDistribution' => $report['cycleTimeDistribution'] ?? ['labels' => [], 'data' => []],
            'geographicDistribution' => $report['geographicDistribution'] ?? ['labels' => [], 'data' => []],
            'employmentDistribution' => $report['employmentDistribution'] ?? ['labels' => [], 'data' => []],
            'caseTrends' => $this->trendFromBase($caseBase, 'cases.created_at'),
            'referralTrends' => $this->trendFromBase($refBase, 'referrals.created_at'),
        ];
    }

    private function trendFromBase($base, string $column): array
    {
        $rows = (clone $base)
            ->selectRaw("to_char($column, 'YYYY-MM') as label, count(*) as count")
            ->groupBy('label')->orderBy('label')->get();

        return ['labels' => $rows->pluck('label')->all(), 'data' => $rows->pluck('count')->map(fn ($v) => (int) $v)->all()];
    }

    private function riskRows($query, string $type): Collection
    {
        $active = $type === 'case' ? ['OPEN'] : ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];
        $idKey = $type === 'case' ? 'case_id' : 'referral_id';

        return $query->whereIn($type === 'case' ? 'cases.status' : 'referrals.status', $active)->get()->map(function ($r) {
            $r->risk_score = (self::RISK[$r->status] ?? 0) + (int) $r->age_days;

            return $r;
        })->sortBy([['risk_score', 'desc'], ['created_at', 'asc'], [$idKey, 'asc']])->values();
    }

    private function metadata(Request $request, array $c, int $refCount, int $caseCount, array $warnings, bool $withDetails): array
    {
        $utc = CarbonImmutable::now('UTC');
        $rowCounts = ['referral_details_matching' => $refCount, 'case_details_matching' => $caseCount];
        if ($withDetails) {
            $rowCounts['referral_details_exported'] = min($refCount, self::ROW_CAP);
            $rowCounts['case_details_exported'] = min($caseCount, self::ROW_CAP);
        } else {
            $rowCounts['pdf_top_referrals_limit'] = self::PDF_TOP_N;
            $rowCounts['pdf_top_cases_limit'] = self::PDF_TOP_N;
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at_utc' => $utc->toIso8601String(),
            'generated_at_manila' => $utc->setTimezone(self::TZ)->toDateTimeString(),
            'generated_by' => $request->user()->name,
            'scope' => $c['role'],
            'timezone' => self::TZ,
            // Full filter set so an exported file ties back to the exact filtered view.
            'filters' => [
                'from' => $c['from']->toDateString(),
                'to' => $c['to']->toDateString(),
                'date_scope' => $c['dateScope'],
                'province' => $c['province'] ?? 'All',
                'city' => $c['city'] ?? 'All',
            ],
            'row_counts' => $rowCounts,
            'row_cap' => self::ROW_CAP,
            'cap_warnings' => $warnings,
            'ai_insights_included' => false,
            'source' => $withDetails ? 'reports_excel_export' : 'reports_pdf_export',
        ];
    }

    private function sheets(array $p, Collection $refs, Collection $cases): array
    {
        $kv = [['key' => 'metric', 'label' => 'Metric', 'type' => 'string'], ['key' => 'value', 'label' => 'Value', 'type' => 'string']];
        $dist = [['key' => 'label', 'label' => 'Label', 'type' => 'string'], ['key' => 'count', 'label' => 'Count', 'type' => 'string']];
        $distRows = fn ($d) => collect($d['labels'] ?? [])->map(fn ($l, $i) => ['label' => $l, 'count' => $d['data'][$i] ?? 0]);
        $refCols = collect(['referral_id', 'case_id', 'case_number', 'agency', 'required_services', 'status', 'created_at', 'completed_at', 'completion_days', 'age_days'])->map(fn ($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k)), 'type' => 'string'])->all();
        $caseCols = collect(['case_id', 'case_number', 'client_type', 'category', 'issue', 'status', 'created_at', 'updated_at', 'closed_at', 'age_days'])->map(fn ($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k)), 'type' => 'string'])->all();

        return [
            ['title' => 'Report Info', 'columnMap' => $kv, 'rows' => collect($p['metadata'])->map(fn ($v, $k) => ['metric' => $k, 'value' => is_array($v) ? json_encode($v) : $v])->values()],
            ['title' => 'Executive Summary', 'columnMap' => $kv, 'rows' => collect($p['kpis'])->map(fn ($v, $k) => ['metric' => $k, 'value' => $v])->values()],
            ['title' => 'Referral Status', 'columnMap' => $dist, 'rows' => $distRows($p['referralStatusDistribution'])],
            ['title' => 'Referral Aging', 'columnMap' => $dist, 'rows' => $distRows($p['referralAging'])], ['title' => 'Cycle Time', 'columnMap' => $dist, 'rows' => $distRows($p['cycleTimeDistribution'])],
            ['title' => 'Agency Scorecard', 'columnMap' => [['key' => 'agency', 'label' => 'Agency', 'type' => 'string'], ['key' => 'total', 'label' => 'Total', 'type' => 'string'], ['key' => 'completed', 'label' => 'Completed', 'type' => 'string'], ['key' => 'pending', 'label' => 'Pending', 'type' => 'string'], ['key' => 'avg_days', 'label' => 'Avg Days', 'type' => 'string']], 'rows' => collect($p['agencyScorecard'])],
            ['title' => 'Geography', 'columnMap' => $dist, 'rows' => $distRows($p['geographicDistribution'])], ['title' => 'Categories', 'columnMap' => [['key' => 'name', 'label' => 'Category', 'type' => 'string'], ['key' => 'count', 'label' => 'Count', 'type' => 'string']], 'rows' => collect($p['categoryDistribution'])],
            ['title' => 'Case Issues', 'columnMap' => [['key' => 'name', 'label' => 'Issue', 'type' => 'string'], ['key' => 'count', 'label' => 'Count', 'type' => 'string']], 'rows' => collect($p['caseIssueDistribution'])],
            ['title' => 'Case Status', 'columnMap' => $dist, 'rows' => $distRows($p['caseStatusDistribution'])], ['title' => 'Employment', 'columnMap' => $dist, 'rows' => $distRows($p['employmentDistribution'])], ['title' => 'Trends', 'columnMap' => [['key' => 'period', 'label' => 'Period', 'type' => 'string'], ['key' => 'cases', 'label' => 'Cases', 'type' => 'string'], ['key' => 'referrals', 'label' => 'Referrals', 'type' => 'string']], 'rows' => $this->trendRows($p['caseTrends'], $p['referralTrends'])],
            ['title' => 'Referral Details', 'columnMap' => $refCols, 'rows' => $refs], ['title' => 'Case Details', 'columnMap' => $caseCols, 'rows' => $cases],
        ];
    }

    private function trendRows(array $caseTrends, array $refTrends): Collection
    {
        $cases = collect($caseTrends['labels'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => (int) ($caseTrends['data'][$i] ?? 0)]);
        $refs = collect($refTrends['labels'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => (int) ($refTrends['data'][$i] ?? 0)]);

        return $cases->keys()->merge($refs->keys())->unique()->sort()->values()
            ->map(fn ($period) => ['period' => $period, 'cases' => (int) ($cases[$period] ?? 0), 'referrals' => (int) ($refs[$period] ?? 0)]);
    }
}
