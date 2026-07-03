<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsExportService
{
    private const SCHEMA_VERSION = 'reports_export_v1';

    private const TZ = 'Asia/Manila';

    private const ROW_CAP = 10000;

    private const PDF_TOP_N = 10;

    private const RISK = ['FOR_COMPLIANCE' => 40, 'PROCESSING' => 30, 'PENDING' => 20, 'OPEN' => 20];

    public function buildPdfPayload(Request $request): array|RedirectResponse
    {
        $payload = $this->buildPayload($request, false);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        return $payload;
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

        $refBase = $this->referralBase($criteria);
        $caseBase = $this->caseBase($criteria);
        $refCount = (clone $refBase)->count();
        $caseCount = (clone $caseBase)->count();
        $refRows = $withDetails ? $this->referralRows($refBase)->limit(self::ROW_CAP)->get() : collect();
        $caseRows = $withDetails ? $this->caseRows($caseBase)->limit(self::ROW_CAP)->get() : collect();

        $warnings = [];
        if ($withDetails && $refCount > self::ROW_CAP) {
            $warnings[] = 'Referral Details capped at '.self::ROW_CAP.' of '.$refCount.' matching rows.';
        }
        if ($withDetails && $caseCount > self::ROW_CAP) {
            $warnings[] = 'Case Details capped at '.self::ROW_CAP.' of '.$caseCount.' matching rows.';
        }

        $summary = $this->summaries($criteria, $refBase, $caseBase, $refCount, $caseCount);
        $metadata = $this->metadata($request, $criteria, $refCount, $caseCount, $warnings, $withDetails);

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

        return ['user' => $user, 'role' => $user->role, 'agency_id' => $user->agcy_id, 'from' => $from, 'to' => $to,
            'fromInstant' => $from->startOfDay()->utc(), 'toInstant' => $to->endOfDay()->utc()];
    }

    private function referralBase(array $c)
    {
        $q = DB::table('referrals')->where('referrals.is_deleted', false)
            ->whereBetween('referrals.created_at', [$c['fromInstant'], $c['toInstant']])
            ->join('cases', 'cases.id', '=', 'referrals.case_id')->where('cases.is_deleted', false);
        if ($c['role'] === 'CASE_MANAGER') {
            $q->where('cases.user_id', $c['user']->id);
        }
        if ($c['role'] === 'AGENCY') {
            $c['agency_id'] ? $q->where('referrals.agcy_id', $c['agency_id']) : $q->whereRaw('1=0');
        }

        return $q;
    }

    private function caseBase(array $c)
    {
        $q = DB::table('cases')->where('cases.is_deleted', false)->whereBetween('cases.created_at', [$c['fromInstant'], $c['toInstant']]);
        if ($c['role'] === 'CASE_MANAGER') {
            $q->where('cases.user_id', $c['user']->id);
        }
        if ($c['role'] === 'AGENCY') {
            $c['agency_id'] ? $q->whereExists(fn ($s) => $s->selectRaw('1')->from('referrals')->whereColumn('referrals.case_id', 'cases.id')->where('referrals.is_deleted', false)->where('referrals.agcy_id', $c['agency_id'])) : $q->whereRaw('1=0');
        }

        return $q;
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

    private function summaries(array $c, $refBase, $caseBase, int $refCount, int $caseCount): array
    {
        $status = (clone $refBase)->select('referrals.status', DB::raw('count(*) as count'))->groupBy('referrals.status')->orderBy('referrals.status')->get();
        $caseStatus = (clone $caseBase)->select('cases.status', DB::raw('count(*) as count'))->groupBy('cases.status')->orderBy('cases.status')->get();
        $agencies = (clone $refBase)->leftJoin('agencies', 'agencies.id', '=', 'referrals.agcy_id')->selectRaw("COALESCE(agencies.name, 'Unassigned') as agency, count(*) as total, sum(case when referrals.status='COMPLETED' then 1 else 0 end) as completed, sum(case when referrals.status='PENDING' then 1 else 0 end) as pending, round(avg(case when referrals.status='COMPLETED' then DATE_PART('day', referrals.updated_at - referrals.created_at) else null end)::numeric, 2) as avg_days")->groupBy('agency')->orderByDesc('total')->get();
        $categories = (clone $caseBase)->leftJoin('case_categories', 'case_categories.id', '=', 'cases.category_id')->selectRaw("COALESCE(case_categories.name, 'Unspecified') as name, count(*) as count")->groupBy('name')->orderByDesc('count')->get();
        $issues = (clone $caseBase)->leftJoin('case_issues', 'case_issues.id', '=', 'cases.case_issue_id')->selectRaw("COALESCE(case_issues.name, 'Unspecified') as name, count(*) as count")->groupBy('name')->orderByDesc('count')->get();
        $refAging = (clone $refBase)->whereIn('referrals.status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])->selectRaw("CASE WHEN DATE_PART('day', NOW() - referrals.created_at) < 7 THEN '< 1 week' WHEN DATE_PART('day', NOW() - referrals.created_at) < 14 THEN '1-2 weeks' WHEN DATE_PART('day', NOW() - referrals.created_at) < 30 THEN '2-4 weeks' ELSE '> 1 month' END as label, count(*) as count")->groupBy('label')->get();
        $cycle = (clone $refBase)->where('referrals.status', 'COMPLETED')->selectRaw("CASE WHEN DATE_PART('day', referrals.updated_at - referrals.created_at) < 7 THEN '< 1 week' WHEN DATE_PART('day', referrals.updated_at - referrals.created_at) < 14 THEN '1-2 weeks' WHEN DATE_PART('day', referrals.updated_at - referrals.created_at) < 30 THEN '2-4 weeks' ELSE '> 1 month' END as label, count(*) as count")->groupBy('label')->get();
        $geo = (clone $caseBase)->leftJoin('client_addresses', 'client_addresses.client_id', '=', 'cases.client_id')->where('client_addresses.is_deleted', false)->whereNotNull('client_addresses.province')->where('client_addresses.province', '!=', '')->selectRaw('client_addresses.province as label, count(distinct cases.id) as count')->groupBy('client_addresses.province')->orderByDesc('count')->limit(20)->get();
        $employment = (clone $caseBase)->leftJoin('client_employments', 'client_employments.client_id', '=', 'cases.client_id')->where('client_employments.is_deleted', false)->whereNotNull('client_employments.last_country')->where('client_employments.last_country', '!=', '')->selectRaw('client_employments.last_country as label, count(distinct cases.id) as count')->groupBy('client_employments.last_country')->orderByDesc('count')->limit(20)->get();
        $caseTrends = (clone $caseBase)->selectRaw("to_char(cases.created_at, 'YYYY-MM') as label, count(*) as count")->groupBy('label')->orderBy('label')->get();
        $refTrends = (clone $refBase)->selectRaw("to_char(referrals.created_at, 'YYYY-MM') as label, count(*) as count")->groupBy('label')->orderBy('label')->get();
        $completed = (int) $status->firstWhere('status', 'COMPLETED')?->count;

        return ['kpis' => ['totalReferrals' => $refCount, 'totalCases' => $caseCount, 'completedReferrals' => $completed, 'pendingReferrals' => (int) $status->firstWhere('status', 'PENDING')?->count, 'processingReferrals' => (int) $status->firstWhere('status', 'PROCESSING')?->count, 'rejectedReferrals' => (int) $status->firstWhere('status', 'REJECTED')?->count, 'completionRate' => $refCount ? round($completed / $refCount * 100, 2) : 0], 'overview' => ['totalCases' => $caseCount, 'totalReferrals' => $refCount], 'referralStatusDistribution' => $this->dist($status, 'status'), 'caseStatusDistribution' => $this->dist($caseStatus, 'status'), 'agencyScorecard' => $agencies->map(fn ($r) => (array) $r)->all(), 'categoryDistribution' => $categories->map(fn ($r) => (array) $r)->all(), 'caseIssueDistribution' => $issues->map(fn ($r) => (array) $r)->all(), 'referralAging' => $this->orderedBucketDist($refAging), 'cycleTimeDistribution' => $this->orderedBucketDist($cycle), 'geographicDistribution' => $this->dist($geo), 'employmentDistribution' => $this->dist($employment), 'caseTrends' => $this->dist($caseTrends), 'referralTrends' => $this->dist($refTrends)];
    }

    private function dist(Collection $rows, string $label = 'label'): array
    {
        return ['labels' => $rows->pluck($label)->all(), 'data' => $rows->pluck('count')->map(fn ($v) => (int) $v)->all()];
    }

    private function orderedBucketDist(Collection $rows): array
    {
        $counts = $rows->pluck('count', 'label');
        $labels = ['< 1 week', '1-2 weeks', '2-4 weeks', '> 1 month'];

        return ['labels' => $labels, 'data' => array_map(fn ($label) => (int) ($counts[$label] ?? 0), $labels)];
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

        return ['schema_version' => self::SCHEMA_VERSION, 'generated_at_utc' => $utc->toIso8601String(), 'generated_at_manila' => $utc->setTimezone(self::TZ)->toDateTimeString(), 'generated_by' => $request->user()->name, 'scope' => $c['role'], 'timezone' => self::TZ, 'filters' => ['from' => $c['from']->toDateString(), 'to' => $c['to']->toDateString()], 'row_counts' => $rowCounts, 'row_cap' => self::ROW_CAP, 'cap_warnings' => $warnings, 'ai_insights_included' => false, 'source' => $withDetails ? 'reports_excel_export' : 'reports_pdf_export'];
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
