<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\Dashboard\Concerns\FormatsDashboardRows;
use App\Services\Dashboard\DashboardQueries;
use App\Support\ReportCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    use FormatsDashboardRows;

    public function __construct(
        private readonly DashboardQueries $queries = new DashboardQueries,
    ) {}

    public function getCaseManagerData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

        // Single aggregated query for case counts + referral counts (cached 60s)
        $countsKey = 'dashboard:cm_counts';
        [$caseCounts, $refCounts] = CacheHelper::safeRemember($countsKey, 60, function () {
            $caseCounts = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status != 'ARCHIVED') AS total,
                    COUNT(*) FILTER (WHERE status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed
                FROM cases WHERE is_deleted = false
            ");
            $refCounts = DB::selectOne("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'PENDING') AS pending,
                    COUNT(*) FILTER (WHERE status = 'PROCESSING') AS processing,
                    COUNT(*) FILTER (WHERE status = 'FOR_COMPLIANCE') AS for_compliance,
                    COUNT(*) FILTER (WHERE status = 'COMPLETED') AS completed,
                    COUNT(*) FILTER (WHERE status = 'REJECTED') AS rejected
                FROM referrals WHERE is_deleted = false
            ");

            // Cast to arrays: objects from DB::selectOne silently break across
            // serialized cache drivers (redis/file/database) — the cached
            // payload must be plain arrays or scalars to avoid incomplete-object
            // corruption that causes an infinite 409-reload loop.
            return [(array) $caseCounts, (array) $refCounts];
        });
        $totalCases = (int) ($caseCounts['total'] ?? 0);
        $openCases = (int) ($caseCounts['open'] ?? 0);
        $closedCases = (int) ($caseCounts['closed'] ?? 0);
        $totalReferrals = (int) ($refCounts['total'] ?? 0);
        $pendingReferrals = (int) ($refCounts['pending'] ?? 0);
        $processingReferrals = (int) ($refCounts['processing'] ?? 0);
        $completedReferrals = (int) ($refCounts['completed'] ?? 0);
        $rejectedReferrals = (int) ($refCounts['rejected'] ?? 0);
        $forComplianceReferrals = (int) ($refCounts['for_compliance'] ?? 0);

        $activeAgencies = CacheHelper::safeRemember('dashboard:active_agencies_count', 300, function () {
            return Agency::where('is_active', true)->count();
        });

        // Unique client count + OFW/NOK split via DB query (avoids loading all clients into memory)
        $clientCounts = CacheHelper::safeRemember('dashboard:cm_client_counts', 120, function () {
            // Cache only arrays/scalars, never DB result objects.
            // Client type lives on clients.type (cases.client_type dropped in Phase 1a).
            return (array) DB::selectOne('
                SELECT
                    COUNT(DISTINCT c.client_id) AS total,
                    COUNT(DISTINCT CASE WHEN cl.type = \'OFW\' THEN c.client_id END) AS ofw,
                    COUNT(DISTINCT CASE WHEN cl.type = \'NEXT_OF_KIN\' THEN c.client_id END) AS nok
                FROM referrals r
                JOIN cases c ON r.case_id = c.id AND c.is_deleted = false
                LEFT JOIN clients cl ON cl.id = c.client_id AND cl.is_deleted = false
                WHERE r.is_deleted = false AND c.client_id IS NOT NULL
            ');
        });
        $uniqueClientCount = (int) ($clientCounts['total'] ?? 0);
        $ofwCount = (int) ($clientCounts['ofw'] ?? 0);
        $nokCount = (int) ($clientCounts['nok'] ?? 0);

        // Recent activity — DATA category only (no security/admin/system events).
        // Cached 300s; invalidated by CacheInvalidationObserver on case/referral writes.
        $recentActivity = CacheHelper::safeRemember('dashboard:cm_recent_activity', 300, function () use ($formatter) {
            return AuditLog::with('user')
                ->where('category', AuditCategory::DATA)
                ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
                ->orderBy('timestamp', 'desc')
                ->take(10)
                ->get()
                ->map(function ($log) use ($formatter) {
                    try {
                        $display = $formatter->formatForAuditResponse($log);
                    } catch (\Throwable $e) {
                        report($e, ['context' => 'DashboardService: audit display formatting failed', 'log_id' => $log->getKey()]);
                        $display = [
                            'id' => (string) $log->getKey(),
                            'message' => 'Activity recorded',
                            'detail' => '',
                            'changes' => [],
                            'action' => $log->action,
                            'module' => 'other',
                            'actor' => 'System',
                            'timestamp' => $log->timestamp?->toISOString(),
                            'hasChanges' => false,
                        ];
                    }

                    $changes = $display['changes'] ?? [];

                    return [
                        'id' => $log->id,
                        'title' => $display['message'],
                        'desc' => $this->formatChangeSummary($changes),
                        'time' => $this->safeRelativeTime($log->timestamp),
                        'logoSrc' => '/logo.png',
                        'message' => $display['message'],
                        'detail' => $display['detail'],
                        'changes' => $changes,
                        'actionType' => $display['action'],
                        'module' => $display['module'],
                        'actor' => $display['actor'],
                        'timestamp' => $display['timestamp'],
                    ];
                })
                ->toArray();
        });

        // Average days to close (SQL AVG instead of loading all into PHP)
        $averageCaseDaysToClose = CacheHelper::safeRemember('dashboard:cm_closed_days', 300, function () {
            $result = DB::selectOne("
                SELECT AVG(EXTRACT(EPOCH FROM (COALESCE(closed_at, updated_at) - created_at)) / 86400) AS avg_days
                FROM cases WHERE status = 'CLOSED' AND is_deleted = false
            ");

            return round((float) (($result->avg_days ?? 0)), 1);
        });

        $casesByCategory = CacheHelper::safeRemember('dashboard:cm_cases_by_category', 300, function () {
            // Authoritative assignments: one case may contribute once to each
            // assigned category, but never more than once per category. Deleted,
            // draft, and archived cases are intentionally excluded.
            return DB::table('case_category AS assignments')
                ->join('cases', 'cases.id', '=', 'assignments.case_id')
                ->join('case_categories', 'case_categories.id', '=', 'assignments.case_category_id')
                ->select('case_categories.name', 'case_categories.color', DB::raw('count(DISTINCT cases.id) as count'))
                ->where('cases.is_deleted', false)
                ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
                ->groupBy('case_categories.name', 'case_categories.color')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'color' => $row->color,
                    'count' => (int) $row->count,
                ])
                ->toArray();
        });

        // Optimized SQL-based computations (replace $allReferrals bulk load)
        $referralStatusDistribution = $this->buildStatusDistributionFromCounts(
            ['PENDING' => $pendingReferrals, 'PROCESSING' => $processingReferrals, 'FOR_COMPLIANCE' => $forComplianceReferrals, 'COMPLETED' => $completedReferrals, 'REJECTED' => $rejectedReferrals],
            $totalReferrals
        );
        $referralAgingBands = CacheHelper::safeRemember('dashboard:cm_aging_bands', 60, function () {
            return $this->queries->buildReferralAgingBandsSQL();
        });
        $priorityReferrals = CacheHelper::safeRemember('dashboard:cm_priority_referrals', 60, function () {
            return $this->queries->buildReferralListSQL(priorityOnly: true);
        });
        $priorityCases = CacheHelper::safeRemember('dashboard:cm_priority_cases', 60, function () {
            return $this->queries->buildPriorityCasesSQL(8);
        });
        $agencyResponseScorecard = CacheHelper::safeRemember('dashboard:cm_scorecard', 60, function () {
            return $this->queries->buildAgencyResponseScorecardSQL();
        });
        $agencyBreakdown = CacheHelper::safeRemember('dashboard:cm_agency_breakdown', 60, function () {
            return $this->queries->buildAgencyBreakdownSQL();
        });
        $intakeReview = CacheHelper::safeRemember('dashboard:cm_intake_review:'.($user?->id ?? 'guest'), 60, function () use ($user) {
            return $this->queries->buildIntakeReviewList($user?->id, 8);
        });
        $referralNews = CacheHelper::safeRemember('dashboard:cm_referral_news', 60, function () {
            return $this->queries->buildReferralNews(8);
        });

        // Trend datasets for the Numbers block chart toggle. Reuses the
        // existing ReportsService builders behind the same 300s cache-key
        // pattern as the admin case-trends payload.
        $reportsService = app(ReportsService::class);
        $casesOverTime = CacheHelper::safeRemember('dashboard:cm_cases_over_time', 300, function () use ($reportsService) {
            return $reportsService->getCasesOverTime(null, UserRole::CASE_MANAGER->value);
        });
        $referralTrends = CacheHelper::safeRemember('dashboard:cm_referral_trends', 300, function () use ($reportsService) {
            return $reportsService->getReferralTrends(null, UserRole::CASE_MANAGER->value);
        });

        // Pending-review and draft counters. Both are per-user counts behind a
        // visible widget, so they are keyed by user and capped to the same
        // 60s window as the review list itself.
        $pendingReviewCount = CacheHelper::safeRemember('dashboard:cm_pending_review_count:'.($user?->id ?? 'guest'), 60, function () {
            return (int) CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
                ->where('is_deleted', false)
                ->count();
        });

        return [
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'totalReferrals' => $totalReferrals,
            'activeAgencies' => $activeAgencies,
            'uniqueClientCount' => $uniqueClientCount,
            'ofwCount' => $ofwCount,
            'nokCount' => $nokCount,
            'casesByCategory' => $casesByCategory,
            'referralStatusDistribution' => $referralStatusDistribution,
            'referralAgingBands' => $referralAgingBands,
            'priorityReferrals' => $priorityReferrals,
            'priorityCases' => $priorityCases,
            'agencyResponseScorecard' => $agencyResponseScorecard,
            'recentActivity' => $recentActivity,
            'averageCaseDaysToClose' => $averageCaseDaysToClose,
            'agencyBreakdown' => $agencyBreakdown,
            'intakeReview' => $intakeReview,
            'pendingReviewCount' => $pendingReviewCount,
            'referralNews' => $referralNews,
            'casesOverTime' => $casesOverTime,
            'referralTrends' => $referralTrends,
        ];
    }

    /**
     * The case manager overview, scoped to a reporting period.
     *
     * Four headline numbers plus a day-bucketed case-volume series. Everything
     * here is period-relative: "Awaiting Agency Response", "Cases needing
     * action" and "Cases closed this period" all describe the window, which is
     * why they cannot come from the lifetime counters in getCaseManagerData().
     *
     * Buckets are days rather than months because the windows are 7/30/90 days
     * — a monthly bucket would collapse the whole chart into one or two points.
     *
     * @param  int  $days  Window length: 7, 30 or 90.
     * @return array{
     *     period: array{days: int, label: string, from: string, to: string},
     *     kpis: array{active: int, awaiting_agency: int, needs_action: int, closed: int},
     *     volume: array{labels: list<string>, received: list<int>, closed: list<int>}
     * }
     */
    public function getCaseManagerPeriodData(int $days = 30): array
    {
        // Only the three offered windows are meaningful; anything else falls
        // back to the 30-day default so a hand-edited query string cannot ask
        // for an unbounded scan.
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $to = CarbonImmutable::now(ReportCalendar::timezone())->startOfDay();
        $from = $to->subDays($days - 1);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return CacheHelper::safeRemember("dashboard:cm_period:{$days}", 60, function () use ($days, $from, $to, $fromDate, $toDate) {
            [$startBound] = ReportCalendar::localDayBounds($fromDate);
            [, $endBound] = ReportCalendar::localDayBounds($toDate);

            // Headline numbers. One pass each, scoped to the window.
            $counts = DB::selectOne('
                SELECT
                    COUNT(*) FILTER (WHERE status = ?) AS active,
                    COUNT(*) FILTER (WHERE status = ? AND COALESCE(closed_at, updated_at) >= ? AND COALESCE(closed_at, updated_at) < ?) AS closed
                FROM cases
                WHERE is_deleted = false AND status <> ?
            ', [
                'OPEN',
                'CLOSED', $startBound, $endBound,
                'ARCHIVED',
            ]);

            // A referral awaiting a reply from a partner office: still open at
            // the agency's end (PENDING or PROCESSING) during the window.
            $awaitingAgency = (int) DB::selectOne('
                SELECT COUNT(*) AS total
                FROM referrals
                WHERE is_deleted = false
                  AND status IN (?, ?)
                  AND created_at >= ? AND created_at < ?
            ', ['PENDING', 'PROCESSING', $startBound, $endBound])->total;

            // Cases needing action: a partner office has asked the citizen for
            // something (FOR_COMPLIANCE) or the referral was rejected and needs
            // re-routing.
            $needsAction = (int) DB::selectOne('
                SELECT COUNT(*) AS total
                FROM referrals
                WHERE is_deleted = false
                  AND status IN (?, ?)
                  AND created_at >= ? AND created_at < ?
            ', ['FOR_COMPLIANCE', 'REJECTED', $startBound, $endBound])->total;

            // Volume series: filings vs closures, one bucket per day.
            $received = DB::table('cases')
                ->selectRaw(ReportCalendar::localDay('created_at').' AS bucket, COUNT(*) AS total')
                ->where('is_deleted', false)
                ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
                ->where('created_at', '>=', $startBound)
                ->where('created_at', '<', $endBound)
                ->groupByRaw(ReportCalendar::localDay('created_at'))
                ->pluck('total', 'bucket');

            $closed = DB::table('cases')
                ->selectRaw(ReportCalendar::localDay('COALESCE(closed_at, updated_at)').' AS bucket, COUNT(*) AS total')
                ->where('is_deleted', false)
                ->where('status', 'CLOSED')
                ->whereRaw('COALESCE(closed_at, updated_at) >= ?', [$startBound])
                ->whereRaw('COALESCE(closed_at, updated_at) < ?', [$endBound])
                ->groupByRaw(ReportCalendar::localDay('COALESCE(closed_at, updated_at)'))
                ->pluck('total', 'bucket');

            // Fill every day in the window so the line has no gaps.
            $labels = [];
            $receivedSeries = [];
            $closedSeries = [];
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $key = $day->toDateString();
                $labels[] = $key;
                $receivedSeries[] = (int) ($received[$key] ?? 0);
                $closedSeries[] = (int) ($closed[$key] ?? 0);
            }

            return [
                'period' => [
                    'days' => $days,
                    'label' => "Last {$days} days",
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'kpis' => [
                    'active' => (int) ($counts->active ?? 0),
                    'awaiting_agency' => $awaitingAgency,
                    'needs_action' => $needsAction,
                    'closed' => (int) ($counts->closed ?? 0),
                ],
                'volume' => [
                    'labels' => $labels,
                    'received' => $receivedSeries,
                    'closed' => $closedSeries,
                ],
            ];
        });
    }

    /**
     * The agency focal overview, scoped to a period and to one agency.
     *
     * Everything here is filtered by the logged-in user's agcy_id — a focal
     * person sees their own agency's referrals, never the whole system. That
     * is the difference from the case manager view, which spans all agencies.
     *
     * "Assigned" counts referrals that reached this agency; "resolved" counts
     * the ones the agency completed. Resolving a referral closes the agency's
     * part, not the whole case, so the two are deliberately separate.
     *
     * @param  int  $days  Window length: 7, 30 or 90.
     */
    public function getAgencyPeriodData(?string $agencyId, int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $to = CarbonImmutable::now(ReportCalendar::timezone())->startOfDay();
        $from = $to->subDays($days - 1);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // No agency on the account means nothing to scope to — return an empty
        // shape rather than every agency's rows.
        if (! $agencyId) {
            return [
                'period' => ['days' => $days, 'label' => "Last {$days} days", 'from' => $fromDate, 'to' => $toDate],
                'kpis' => ['assigned' => 0, 'awaiting' => 0, 'action' => 0, 'resolved' => 0],
                'volume' => ['labels' => [], 'received' => [], 'resolved' => []],
                'status_mix' => [],
                'category_mix' => [],
            ];
        }

        return CacheHelper::safeRemember("dashboard:agency_period:{$agencyId}:{$days}", 60, function () use ($agencyId, $days, $from, $to, $fromDate, $toDate) {
            [$startBound] = ReportCalendar::localDayBounds($fromDate);
            [, $endBound] = ReportCalendar::localDayBounds($toDate);

            // Headline numbers for this agency in the window.
            $counts = DB::selectOne('
                SELECT
                    COUNT(*) AS assigned,
                    COUNT(*) FILTER (WHERE status IN (?, ?)) AS awaiting,
                    COUNT(*) FILTER (WHERE status IN (?, ?)) AS action,
                    COUNT(*) FILTER (WHERE status = ?) AS resolved
                FROM referrals
                WHERE is_deleted = false
                  AND agcy_id = ?
                  AND created_at >= ? AND created_at < ?
            ', [
                'PENDING', 'PROCESSING',
                'FOR_COMPLIANCE', 'REJECTED',
                'COMPLETED',
                $agencyId, $startBound, $endBound,
            ]);

            // Received vs resolved per day, so the line chart can show whether
            // the agency is keeping up. Resolved is bucketed on the last write
            // because referrals carry no dedicated completed_at.
            $received = DB::table('referrals')
                ->selectRaw(ReportCalendar::localDay('created_at').' AS bucket, COUNT(*) AS total')
                ->where('is_deleted', false)
                ->where('agcy_id', $agencyId)
                ->where('created_at', '>=', $startBound)
                ->where('created_at', '<', $endBound)
                ->groupByRaw(ReportCalendar::localDay('created_at'))
                ->pluck('total', 'bucket');

            $resolved = DB::table('referrals')
                ->selectRaw(ReportCalendar::localDay('updated_at').' AS bucket, COUNT(*) AS total')
                ->where('is_deleted', false)
                ->where('agcy_id', $agencyId)
                ->where('status', 'COMPLETED')
                ->where('updated_at', '>=', $startBound)
                ->where('updated_at', '<', $endBound)
                ->groupByRaw(ReportCalendar::localDay('updated_at'))
                ->pluck('total', 'bucket');

            $labels = [];
            $receivedSeries = [];
            $resolvedSeries = [];
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $key = $day->toDateString();
                $labels[] = $key;
                $receivedSeries[] = (int) ($received[$key] ?? 0);
                $resolvedSeries[] = (int) ($resolved[$key] ?? 0);
            }

            // Status mix across all of this agency's referrals (not just the
            // window) — it answers "where is our workload concentrated now".
            $statusMix = DB::select('
                SELECT status, COUNT(*) AS total
                FROM referrals
                WHERE is_deleted = false AND agcy_id = ?
                GROUP BY status
                ORDER BY total DESC
            ', [$agencyId]);

            // Category mix: the case category is the only reliable taxonomy on
            // the referral row (referral_services is sparsely populated).
            $categoryMix = DB::select('
                SELECT COALESCE(cc.name, ?) AS label, COUNT(*) AS total
                FROM referrals r
                JOIN cases c ON c.id = r.case_id AND c.is_deleted = false
                LEFT JOIN case_categories cc ON cc.id = c.category_id
                WHERE r.is_deleted = false AND r.agcy_id = ?
                GROUP BY cc.name
                ORDER BY total DESC
                LIMIT 8
            ', ['Uncategorised', $agencyId]);

            return [
                'period' => [
                    'days' => $days,
                    'label' => "Last {$days} days",
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'kpis' => [
                    'assigned' => (int) ($counts->assigned ?? 0),
                    'awaiting' => (int) ($counts->awaiting ?? 0),
                    'action' => (int) ($counts->action ?? 0),
                    'resolved' => (int) ($counts->resolved ?? 0),
                ],
                'volume' => [
                    'labels' => $labels,
                    'received' => $receivedSeries,
                    'resolved' => $resolvedSeries,
                ],
                'status_mix' => array_map(fn ($row) => [
                    'status' => $row->status,
                    'total' => (int) $row->total,
                ], $statusMix),
                'category_mix' => array_map(fn ($row) => [
                    'label' => $row->label,
                    'total' => (int) $row->total,
                ], $categoryMix),
            ];
        });
    }

    public function getAgencyData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

        $agencyId = $user?->agcy_id;

        // Single aggregated query for agency referral counts (cached 60s)
        $countsKey = 'dashboard:agency_counts:'.$agencyId;
        $refCounts = CacheHelper::safeRemember($countsKey, 60, function () use ($agencyId) {
            // Cache only arrays/scalars, never DB result objects.
            return (array) DB::selectOne("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'PENDING') AS pending,
                    COUNT(*) FILTER (WHERE status = 'PROCESSING') AS processing,
                    COUNT(*) FILTER (WHERE status = 'FOR_COMPLIANCE') AS for_compliance,
                    COUNT(*) FILTER (WHERE status = 'COMPLETED') AS completed,
                    COUNT(*) FILTER (WHERE status = 'REJECTED') AS rejected
                FROM referrals WHERE is_deleted = false AND agcy_id = ?
            ", [$agencyId]);
        });
        $totalReferrals = (int) ($refCounts['total'] ?? 0);
        $pendingReferrals = (int) ($refCounts['pending'] ?? 0);
        $processingReferrals = (int) ($refCounts['processing'] ?? 0);
        $forComplianceReferrals = (int) ($refCounts['for_compliance'] ?? 0);
        $completedReferrals = (int) ($refCounts['completed'] ?? 0);
        $rejectedReferrals = (int) ($refCounts['rejected'] ?? 0);

        // Optimized SQL-based computations (replace $agencyReferrals bulk load)
        $referralStatusDistribution = $this->buildStatusDistributionFromCounts(
            ['PENDING' => $pendingReferrals, 'PROCESSING' => $processingReferrals, 'FOR_COMPLIANCE' => $forComplianceReferrals, 'COMPLETED' => $completedReferrals, 'REJECTED' => $rejectedReferrals],
            $totalReferrals
        );
        $pendingReferralsList = CacheHelper::safeRemember('dashboard:agency_pending_referrals:'.$agencyId, 60, function () use ($agencyId) {
            return $this->queries->buildReferralListSQL($agencyId, 5, false, 'PENDING');
        });
        $processingReferralsList = CacheHelper::safeRemember('dashboard:agency_processing_referrals:'.$agencyId, 60, function () use ($agencyId) {
            return $this->queries->buildReferralListSQL($agencyId, 5, false, 'PROCESSING');
        });
        $overdueReferralsList = CacheHelper::safeRemember('dashboard:agency_overdue_referrals:'.$agencyId, 60, function () use ($agencyId) {
            return $this->queries->buildReferralListSQL($agencyId, 5, false, overdueOnly: true);
        });
        $serviceDemand = CacheHelper::safeRemember('dashboard:agency_service_demand:'.$agencyId, 120, function () use ($agencyId) {
            return $this->queries->buildAgencyServiceDemand($agencyId);
        });

        // Overdue count via targeted SQL (cached 60s). The cutoff is read from
        // the referral_overdue_days setting so this queue cell always matches
        // the Overdue Referrals page and the overdueReferrals list above.
        $overdueDays = DashboardQueries::overdueDays();
        $queueCounts = CacheHelper::safeRemember('dashboard:agency_queue_counts:'.$agencyId, 60, function () use ($agencyId, $overdueDays) {
            $row = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - created_at))/86400 >= ?) AS overdue
                FROM referrals
                WHERE agcy_id = ? AND is_deleted = false
            ", [$overdueDays, $agencyId]);

            return ['overdue' => (int) $row->overdue];
        });
        $overdueReferrals = $queueCounts['overdue'];

        $workQueue = [
            $this->queueItem('pendingReferrals', 'Pending', $pendingReferrals, 'Needs acknowledgement or first action.', 'amber', 'schedule', '/referrals?status=PENDING'),
            $this->queueItem('processingReferrals', 'Processing', $processingReferrals, 'Currently being handled.', 'cyan', 'sync', '/referrals?status=PROCESSING'),
            $this->queueItem('overdueReferrals', 'Overdue', $overdueReferrals, "Active referrals older than {$overdueDays} days.", 'rose', 'warning', "/referrals?age_min_days={$overdueDays}"),
        ];

        // Recent activity via subquery instead of loading all referral IDs.
        // Cached 300s per agency; invalidated by CacheInvalidationObserver on referral writes.
        $recentActivity = CacheHelper::safeRemember('dashboard:agency_recent_activity:'.$agencyId, 300, function () use ($agencyId, $formatter) {
            return AuditLog::whereIn('entity_id', function ($query) use ($agencyId) {
                $query->select('id')
                    ->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->where('is_deleted', false);
            })
                ->whereIn('module', ['referral', 'referrals'])
                ->orderBy('timestamp', 'desc')
                ->take(10)
                ->get()
                ->map(function ($log) use ($formatter) {
                    try {
                        $display = $formatter->formatForAuditResponse($log);
                    } catch (\Throwable $e) {
                        report($e, ['context' => 'DashboardService: audit display formatting failed', 'log_id' => $log->getKey()]);
                        $display = [
                            'id' => (string) $log->getKey(),
                            'message' => 'Activity recorded',
                            'detail' => '',
                            'changes' => [],
                            'action' => $log->action,
                            'module' => 'other',
                            'actor' => 'System',
                            'timestamp' => $log->timestamp?->toISOString(),
                            'hasChanges' => false,
                        ];
                    }

                    $changes = $display['changes'] ?? [];

                    return [
                        'id' => $log->id,
                        'title' => $display['message'],
                        'desc' => $this->formatChangeSummary($changes),
                        'time' => $this->safeRelativeTime($log->timestamp),
                        'logoSrc' => '/logo.png',
                        'message' => $display['message'],
                        'detail' => $display['detail'],
                        'changes' => $changes,
                        'actionType' => $display['action'],
                        'module' => $display['module'],
                        'actor' => $display['actor'],
                        'timestamp' => $display['timestamp'],
                    ];
                })
                ->toArray();
        });

        return [
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferralsList,
            'processingReferrals' => $processingReferralsList,
            'overdueReferrals' => $overdueReferralsList,
            'recentActivity' => $recentActivity,
            'workQueue' => $workQueue,
            'referralStatusDistribution' => $referralStatusDistribution,
            'serviceDemand' => $serviceDemand,
        ];
    }

    /**
     * The system admin overview, scoped to a period.
     *
     * System health, user/agency management, and security monitoring — not
     * casework. Four numbers (users, agencies, pending invites, security
     * events), a system-activity line, a role distribution donut, and a
     * security-events-by-type bar.
     *
     * @param  int  $days  Window length: 7, 30 or 90.
     */
    public function getAdminPeriodData(int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $to = CarbonImmutable::now(ReportCalendar::timezone())->startOfDay();
        $from = $to->subDays($days - 1);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return CacheHelper::safeRemember("dashboard:admin_period:{$days}", 60, function () use ($days, $from, $to, $fromDate, $toDate) {
            [$startBound] = ReportCalendar::localDayBounds($fromDate);
            [, $endBound] = ReportCalendar::localDayBounds($toDate);

            // Headline counts. Users and agencies are lifetime (they answer
            // "how big is the system"); invites and security events are
            // period-scoped (they answer "what needs attention now").
            $userCounts = (array) DB::selectOne('
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE is_active = true) AS active,
                    COUNT(*) FILTER (WHERE email_verified_at IS NOT NULL) AS verified
                FROM users WHERE is_deleted = false
            ');

            $agencyCounts = (array) DB::selectOne('
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE is_active = true) AS active
                FROM agencies WHERE is_deleted = false
            ');

            // Pending invitations: sent but not consumed and not expired.
            $pendingInvites = (int) DB::selectOne('
                SELECT COUNT(*) AS total
                FROM user_invites
                WHERE consumed_at IS NULL
                  AND cancelled_at IS NULL
                  AND expires_at > NOW()
            ')->total;

            // Security events in the window.
            $securityEvents = (int) DB::selectOne('
                SELECT COUNT(*) AS total
                FROM audit_logs
                WHERE category = ?
                  AND timestamp >= ? AND timestamp < ?
            ', ['security', $startBound, $endBound])->total;

            // System activity over time — every audit event, bucketed by day.
            $activity = DB::table('audit_logs')
                ->selectRaw(ReportCalendar::localDay('timestamp').' AS bucket, COUNT(*) AS total')
                ->where('timestamp', '>=', $startBound)
                ->where('timestamp', '<', $endBound)
                ->groupByRaw(ReportCalendar::localDay('timestamp'))
                ->pluck('total', 'bucket');

            $labels = [];
            $activitySeries = [];
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $key = $day->toDateString();
                $labels[] = $key;
                $activitySeries[] = (int) ($activity[$key] ?? 0);
            }

            // Users by role (lifetime — the distribution doesn't change by window).
            $usersByRole = DB::table('users')
                ->select('role', DB::raw('COUNT(*) AS total'))
                ->where('is_deleted', false)
                ->groupBy('role')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['role' => $row->role, 'total' => (int) $row->total])
                ->toArray();

            // Security events by action in the window.
            $securityByType = DB::select('
                SELECT action, COUNT(*) AS total
                FROM audit_logs
                WHERE category = ?
                  AND timestamp >= ? AND timestamp < ?
                GROUP BY action
                ORDER BY total DESC
                LIMIT 10
            ', ['security', $startBound, $endBound]);

            return [
                'period' => [
                    'days' => $days,
                    'label' => "Last {$days} days",
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'kpis' => [
                    'total_users' => (int) ($userCounts['total'] ?? 0),
                    'total_agencies' => (int) ($agencyCounts['total'] ?? 0),
                    'pending_invites' => $pendingInvites,
                    'security_events' => $securityEvents,
                ],
                'activity' => [
                    'labels' => $labels,
                    'events' => $activitySeries,
                ],
                'users_by_role' => $usersByRole,
                'security_by_type' => array_map(fn ($row) => [
                    'action' => $row->action,
                    'total' => (int) $row->total,
                ], $securityByType),
            ];
        });
    }

    public function getAdminData(): array
    {
        $formatter = app(AuditLogFormatter::class);

        $overdueDays = DashboardQueries::overdueDays();
        $dashboardWindow = now()->subDays($overdueDays);

        // Single aggregated query for case + referral counts (cached 60s)
        $countsKey = 'dashboard:admin_counts_v2';
        [$caseCounts, $refCounts] = CacheHelper::safeRemember($countsKey, 60, function () use ($dashboardWindow) {
            $caseCounts = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status != 'ARCHIVED') AS total,
                    COUNT(*) FILTER (WHERE status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed
                FROM cases WHERE is_deleted = false
            ");
            $refCounts = DB::selectOne("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'PENDING') AS pending,
                    COUNT(*) FILTER (WHERE status = 'PROCESSING') AS processing,
                    COUNT(*) FILTER (WHERE status = 'FOR_COMPLIANCE') AS for_compliance,
                    COUNT(*) FILTER (WHERE status = 'COMPLETED') AS completed,
                    COUNT(*) FILTER (WHERE status = 'REJECTED') AS rejected,
                    COUNT(*) FILTER (WHERE status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND created_at < ?) AS overdue
                FROM referrals WHERE is_deleted = false
            ", [$dashboardWindow]);

            return [(array) $caseCounts, (array) $refCounts];
        });
        $totalCases = (int) $caseCounts['total'];
        $openCases = (int) $caseCounts['open'];
        $closedCases = (int) $caseCounts['closed'];
        $totalReferrals = (int) $refCounts['total'];
        $pendingReferrals = (int) $refCounts['pending'];
        $processingReferrals = (int) $refCounts['processing'];
        $forComplianceReferrals = (int) $refCounts['for_compliance'];
        $completedReferrals = (int) $refCounts['completed'];
        $rejectedReferrals = (int) $refCounts['rejected'];
        $overdueReferrals = (int) $refCounts['overdue'];

        $referralStatusDistribution = $this->buildStatusDistributionFromCounts(
            ['PENDING' => $pendingReferrals, 'PROCESSING' => $processingReferrals, 'FOR_COMPLIANCE' => $forComplianceReferrals, 'COMPLETED' => $completedReferrals, 'REJECTED' => $rejectedReferrals],
            $totalReferrals
        );

        $totalUsers = CacheHelper::safeRemember('dashboard:admin_user_counts', 120, function () {
            return [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
            ];
        });
        $activeUsers = $totalUsers['active'];
        $verifiedUsers = $totalUsers['verified'];
        $inactiveUsers = max($totalUsers['total'] - $activeUsers, 0);
        $totalUsers = $totalUsers['total'];

        $agencyCounts = CacheHelper::safeRemember('dashboard:admin_agency_counts', 300, function () {
            $total = Agency::count();
            $active = Agency::where('is_active', true)->count();

            return ['total' => $total, 'active' => $active];
        });
        $totalAgencies = $agencyCounts['total'];
        $activeAgencies = $agencyCounts['active'];
        $inactiveAgencies = max($totalAgencies - $activeAgencies, 0);

        // Worst-case age per queue for triage severity (cached 60s).
        $worstAges = CacheHelper::safeRemember('dashboard:admin_worst_ages', 60, function () {
            $overdueThreshold = now()->subDays(DashboardQueries::overdueDays());

            return (array) DB::selectOne("
                SELECT
                    (SELECT MAX(EXTRACT(EPOCH FROM (NOW() - created_at))/86400)::int FROM cases WHERE status = 'OPEN' AND is_deleted = false) AS open_cases_worst,
                    (SELECT MAX(EXTRACT(EPOCH FROM (NOW() - created_at))/86400)::int FROM referrals WHERE status = 'PENDING' AND is_deleted = false) AS pending_worst,
                    (SELECT MAX(EXTRACT(EPOCH FROM (NOW() - created_at))/86400)::int FROM referrals WHERE status = 'PROCESSING' AND is_deleted = false) AS processing_worst,
                    (SELECT MAX(EXTRACT(EPOCH FROM (NOW() - created_at))/86400)::int FROM referrals WHERE status = 'FOR_COMPLIANCE' AND is_deleted = false) AS compliance_worst,
                    (SELECT MAX(EXTRACT(EPOCH FROM (NOW() - created_at))/86400)::int FROM referrals WHERE status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND created_at < ? AND is_deleted = false) AS overdue_worst
            ", [$overdueThreshold]);
        });

        $operationalQueues = [
            [
                'key' => 'openCases',
                'label' => 'Open cases',
                'count' => $openCases,
                'note' => 'Active case files on deck.',
                'tone' => 'blue',
                'icon' => 'folder_open',
                'href' => '/cases?status=OPEN',
                'worstAgeDays' => $worstAges['open_cases_worst'] ?? null,
            ],
            [
                'key' => 'pendingReferrals',
                'label' => 'Pending referrals',
                'count' => $pendingReferrals,
                'note' => 'Waiting for agency action.',
                'tone' => 'amber',
                'icon' => 'schedule',
                'href' => '/referrals?status=PENDING',
                'worstAgeDays' => $worstAges['pending_worst'] ?? null,
            ],
            [
                'key' => 'processingReferrals',
                'label' => 'Processing',
                'count' => $processingReferrals,
                'note' => 'Already in motion.',
                'tone' => 'cyan',
                'icon' => 'sync',
                'href' => '/referrals?status=PROCESSING',
                'worstAgeDays' => $worstAges['processing_worst'] ?? null,
            ],
            [
                'key' => 'forComplianceReferrals',
                'label' => 'For compliance',
                'count' => $forComplianceReferrals,
                'note' => 'Needs missing documents.',
                'tone' => 'orange',
                'icon' => 'fact_check',
                'href' => '/referrals?status=FOR_COMPLIANCE',
                'worstAgeDays' => $worstAges['compliance_worst'] ?? null,
            ],
            [
                'key' => 'overdueReferrals',
                'label' => 'Overdue referrals',
                'count' => $overdueReferrals,
                'note' => "Older than {$overdueDays} days.",
                'tone' => 'rose',
                'icon' => 'warning',
                'href' => '/overdue-referrals',
                'worstAgeDays' => $worstAges['overdue_worst'] ?? null,
            ],
        ];

        $usersByRole = CacheHelper::safeRemember('dashboard:admin_users_by_role', 120, function () {
            return User::select('role', DB::raw('count(*) as total'))
                ->groupBy('role')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'role' => $row->role,
                    'label' => match ($row->role) {
                        UserRole::ADMIN->value => 'Administrators',
                        UserRole::CASE_MANAGER->value => 'Case managers',
                        UserRole::AGENCY->value => 'Agency users',
                        default => $row->role,
                    },
                    'count' => (int) $row->total,
                ])
                ->toArray();
        });

        $topAgencies = CacheHelper::safeRemember('dashboard:admin_top_agencies', 120, function () {
            $activeReferralStatuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

            return Agency::select('id', 'name', 'is_active')
                ->where('is_deleted', false)
                ->where('is_active', true)
                ->withCount(['referrals' => fn ($query) => $query->where('is_deleted', false)])
                ->withCount(['referrals as active_referrals_count' => fn ($query) => $query->where('is_deleted', false)->whereIn('status', $activeReferralStatuses)])
                ->orderByDesc('active_referrals_count')
                ->orderByDesc('referrals_count')
                ->take(5)
                ->get()
                ->map(fn ($agency) => [
                    'id' => $agency->id,
                    'name' => $agency->name,
                    'isActive' => (bool) $agency->is_active,
                    'totalReferrals' => (int) $agency->referrals_count,
                    'activeReferrals' => (int) $agency->active_referrals_count,
                ])
                ->toArray();
        });

        // Agency response scorecard — worst-performing agencies first (cached 120s).
        $agencyScorecard = CacheHelper::safeRemember('dashboard:admin_agency_scorecard', 120, function () {
            return array_map(fn (array $row) => [
                'id' => $row['agencyId'],
                'name' => $row['agencyName'],
                'totalReferrals' => $row['totalReferrals'],
                'activeReferrals' => $row['activeCount'],
                'overdueReferrals' => $row['overdueCount'],
                'overdueRate' => $row['overdueRate'],
                'avgDaysToComplete' => $row['averageCompletionDays'],
            ], array_slice($this->queries->buildAgencyResponseScorecardSQL(), 0, 5));
        });

        $recentCases = CaseFile::with(['client', 'user', 'category'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('is_deleted', false)
            ->orderBy('updated_at', 'desc')
            ->take(6)
            ->get();

        // Aggregate referral stats for recent cases in a single query (no N+1).
        $recentCaseIds = $recentCases->pluck('id')->all();
        $referralAggregates = $recentCaseIds
            ? $this->queries->buildRecentCasesReferralAggregates($recentCaseIds)
            : [];

        $recentCases = $recentCases
            ->map(function ($c) use ($referralAggregates) {
                $agg = $referralAggregates[$c->id] ?? null;

                return [
                    'id' => $c->id,
                    'case_number' => $c->case_number,
                    'tracker_number' => $c->tracker_number,
                    'client_name' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                    'client_type' => ($c->client?->type ?? 'OFW') === 'NEXT_OF_KIN' ? 'Next of Kin' : 'Overseas Filipino Worker',
                    'status' => $c->status,
                    'created_at' => $c->created_at?->toISOString() ?? now()->toISOString(),
                    'updated_at' => $c->updated_at?->toISOString() ?? now()->toISOString(),
                    'case_owner' => $c->user?->name,
                    'category' => $c->category?->name,
                    'last_activity' => $this->safeRelativeTime($c->updated_at),
                    'referral_count' => $agg['referral_count'] ?? 0,
                    'active_referral_count' => $agg['active_referral_count'] ?? 0,
                    'overdue_referral_count' => $agg['overdue_referral_count'] ?? 0,
                    'worst_referral_status' => $agg['worst_referral_status'] ?? null,
                    'max_referral_age_days' => $agg['max_referral_age_days'] ?? null,
                    'is_overdue' => $agg['is_overdue'] ?? false,
                ];
            })
            ->toArray();

        // Recent audit activity. Cached 300s; invalidated by
        // CacheInvalidationObserver on case/referral writes.
        $recentLogs = CacheHelper::safeRemember('dashboard:admin_recent_logs', 300, function () use ($formatter) {
            return AuditLog::with('user')
                ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
                ->orderBy('timestamp', 'desc')
                ->take(8)
                ->get()
                ->map(function ($log) use ($formatter) {
                    try {
                        $display = $formatter->formatForAuditResponse($log);
                    } catch (\Throwable $e) {
                        report($e, ['context' => 'DashboardService: audit display formatting failed', 'log_id' => $log->getKey()]);
                        $display = [
                            'id' => (string) $log->getKey(),
                            'message' => 'Activity recorded',
                            'detail' => '',
                            'changes' => [],
                            'action' => $log->action,
                            'module' => 'other',
                            'actor' => $log->user?->name ?? 'System',
                            'timestamp' => $log->timestamp?->toISOString(),
                            'hasChanges' => false,
                        ];
                    }

                    $changes = $display['changes'] ?? [];

                    return [
                        'id' => $display['id'],
                        'action' => $display['action'],
                        'module' => $display['module'],
                        'timestamp' => $display['timestamp'],
                        'message' => $display['message'],
                        'detail' => $display['detail'],
                        'changes' => $changes,
                        'actor' => $display['actor'],
                        'hasChanges' => $display['hasChanges'],
                    ];
                })
                ->toArray();
        });

        // Priority referrals — top 5 highest-priority across all agencies.
        $adminPriorityReferrals = CacheHelper::safeRemember('dashboard:admin_priority_referrals', 60, function () {
            return $this->queries->buildReferralListSQL(limit: 5, priorityOnly: true);
        });

        // Referral aging bands — global view for frontend aging visualization.
        $adminReferralAgingBands = CacheHelper::safeRemember('dashboard:admin_aging_bands', 60, function () {
            return $this->queries->buildReferralAgingBandsSQL();
        });

        $casesByCategory = CacheHelper::safeRemember('dashboard:admin_cases_by_category', 300, function () {
            // Keep category counts additive across assignments while excluding
            // deleted, draft, and archived cases from the dashboard mix.
            return DB::table('case_category AS assignments')
                ->join('cases', 'cases.id', '=', 'assignments.case_id')
                ->join('case_categories', 'case_categories.id', '=', 'assignments.case_category_id')
                ->select('case_categories.name', 'case_categories.color', DB::raw('count(DISTINCT cases.id) as count'))
                ->where('cases.is_deleted', false)
                ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
                ->groupBy('case_categories.name', 'case_categories.color')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'color' => $row->color,
                    'count' => (int) $row->count,
                ])
                ->toArray();
        });

        $stats = [
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'forComplianceReferrals' => $forComplianceReferrals,
            'overdueReferrals' => $overdueReferrals,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'verifiedUsers' => $verifiedUsers,
            'inactiveUsers' => $inactiveUsers,
            'totalAgencies' => $totalAgencies,
            'activeAgencies' => $activeAgencies,
            'inactiveAgencies' => $inactiveAgencies,
        ];

        return [
            'stats' => $stats,
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'forComplianceReferrals' => $forComplianceReferrals,
            'overdueReferrals' => $overdueReferrals,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'verifiedUsers' => $verifiedUsers,
            'inactiveUsers' => $inactiveUsers,
            'totalAgencies' => $totalAgencies,
            'activeAgencies' => $activeAgencies,
            'inactiveAgencies' => $inactiveAgencies,
            'operationalQueues' => $operationalQueues,
            'referralStatusDistribution' => $referralStatusDistribution,
            'usersByRole' => $usersByRole,
            'topAgencies' => $topAgencies,
            'agencyScorecard' => $agencyScorecard,
            'recentCases' => $recentCases,
            'recentLogs' => $recentLogs,
            'casesByCategory' => $casesByCategory,
            'priorityReferrals' => $adminPriorityReferrals,
            'referralAgingBands' => $adminReferralAgingBands,
        ];
    }
}
