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

        $myDraftCount = $user ? CaseFile::where('status', 'DRAFT')->where('user_id', $user->id)->count() : 0;

        // Single aggregated query for case counts + referral counts (cached 60s)
        $countsKey = 'dashboard:cm_counts';
        [$caseCounts, $refCounts] = CacheHelper::safeRemember($countsKey, 60, function () {
            $caseCounts = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status != 'DRAFT') AS total,
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
            return (array) DB::selectOne('
                SELECT
                    COUNT(DISTINCT c.client_id) AS total,
                    COUNT(DISTINCT CASE WHEN c.client_type = \'OFW\' THEN c.client_id END) AS ofw,
                    COUNT(DISTINCT CASE WHEN c.client_type = \'NEXT_OF_KIN\' THEN c.client_id END) AS nok
                FROM referrals r
                JOIN cases c ON r.case_id = c.id AND c.is_deleted = false
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

        // New-cases preview: 5 newest OPEN (or created <30d) rows. Count
        // totals above stay exact — this trim only affects the preview list.
        $allCases = CaseFile::with(['client'])
            ->select('id', 'case_number', 'tracker_number', 'client_id', 'client_type', 'status', 'created_at', 'updated_at')
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where(function ($q) {
                $q->where('status', 'OPEN')
                    ->orWhere('created_at', '>', now()->subDays(30));
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'caseNo' => $c->case_number,
                'trackerNumber' => $c->tracker_number,
                'clientName' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                'clientType' => $c->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'status' => $c->status,
                'createdAt' => $c->created_at?->toISOString() ?? now()->toISOString(),
                'updatedAt' => $c->updated_at?->toISOString() ?? now()->toISOString(),
                'ofwProfile' => null,
            ])
            ->values()
            ->toArray();

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
        $forComplianceList = CacheHelper::safeRemember('dashboard:cm_for_compliance_list', 60, function () {
            return $this->queries->buildReferralListSQL(status: 'FOR_COMPLIANCE', priorityOnly: true);
        });
        $readyToClose = CacheHelper::safeRemember('dashboard:cm_ready_to_close', 60, function () {
            return $this->queries->buildReadyToCloseListSQL(8);
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

        // Work queue counts via targeted SQL
        $agingOpenCasesCount = CacheHelper::safeRemember('dashboard:cm_aging_open_count', 60, function () {
            return (int) DB::selectOne("
                SELECT COUNT(*) AS cnt FROM cases
                WHERE status = 'OPEN' AND is_deleted = false AND created_at < NOW() - INTERVAL '7 days'
            ")->cnt;
        });

        $casesWithoutReferrals = CacheHelper::safeRemember('dashboard:cm_no_referral_count', 60, function () {
            return (int) DB::selectOne("
                SELECT COUNT(*) AS cnt FROM cases
                WHERE status = 'OPEN' AND is_deleted = false
                AND NOT EXISTS (SELECT 1 FROM referrals WHERE referrals.case_id = cases.id AND referrals.is_deleted = false)
            ")->cnt;
        });

        $intakeReviewCount = CacheHelper::safeRemember('dashboard:cm_intake_review_count:'.($user?->id ?? 'guest'), 60, function () use ($user) {
            return (int) CaseFile::where('status', 'DRAFT')
                ->where('is_deleted', false)
                ->where(function ($q) use ($user) {
                    $q->where('source', CaseFile::SOURCE_SELF_FILED);
                    if ($user) {
                        $q->orWhere('user_id', $user->id);
                    }
                })
                ->count();
        });

        $readyToCloseCount = CacheHelper::safeRemember('dashboard:cm_ready_to_close_count', 60, function () {
            return (int) DB::selectOne("
                SELECT COUNT(*) AS cnt FROM cases c
                WHERE c.status = 'OPEN' AND c.is_deleted = false
                    AND EXISTS (SELECT 1 FROM referrals WHERE case_id = c.id AND is_deleted = false)
                    AND NOT EXISTS (SELECT 1 FROM referrals WHERE case_id = c.id AND status != 'COMPLETED' AND is_deleted = false)
            ")->cnt;
        });

        $workQueue = [
            $this->queueItem('agingOpenCases', 'Aging open cases', $agingOpenCasesCount, 'Open seven days or more.', 'amber', 'folder_clock', '/cases?status=OPEN&age_min_days=7'),
            $this->queueItem('pendingReferrals', 'Pending referrals', $pendingReferrals, 'Waiting for agency action.', 'amber', 'schedule', '/referrals?status=PENDING'),
            $this->queueItem('rejectedReferrals', 'Rejected referrals', $rejectedReferrals, 'Needs reassignment or follow-up.', 'rose', 'assignment_return', '/referrals?status=REJECTED'),
            $this->queueItem('draftCases', 'Draft cases', $myDraftCount, 'Your unfinished case drafts.', 'slate', 'edit_note', '/cases/drafts'),
            $this->queueItem('casesWithoutReferrals', 'Cases without referrals', $casesWithoutReferrals, 'Open cases that may need routing.', 'blue', 'hub', '/cases?status=OPEN&referral_state=none'),
            $this->queueItem('intakeReview', 'Intake to review', $intakeReviewCount, 'Your drafts plus self-filed intakes awaiting review.', 'cyan', 'pending_actions', '/cases/intake-queue'),
            $this->queueItem('forComplianceReferrals', 'For compliance', $forComplianceReferrals, 'Waiting on missing requirements.', 'orange', 'fact_check', '/referrals?status=FOR_COMPLIANCE'),
            $this->queueItem('readyToClose', 'Ready to close', $readyToCloseCount, 'Open cases with all referrals completed.', 'emerald', 'task_alt', '/cases?status=OPEN'),
        ];

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
            'workQueue' => $workQueue,
            'recentActivity' => $recentActivity,
            'averageCaseDaysToClose' => $averageCaseDaysToClose,
            'myDraftCount' => $myDraftCount,
            'allCases' => $allCases,
            'agencyBreakdown' => $agencyBreakdown,
            'intakeReview' => $intakeReview,
            'forComplianceList' => $forComplianceList,
            'readyToClose' => $readyToClose,
            'casesOverTime' => $casesOverTime,
            'referralTrends' => $referralTrends,
        ];
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
        $referralAgingBands = CacheHelper::safeRemember('dashboard:agency_aging_bands:'.$agencyId, 60, function () use ($agencyId) {
            return $this->queries->buildReferralAgingBandsSQL($agencyId);
        });
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
        $feedbackPulse = CacheHelper::safeRemember('dashboard:agency_feedback_pulse:'.$agencyId, 300, function () use ($agencyId) {
            return $this->queries->buildFeedbackPulse($agencyId);
        });

        // Overdue + new referrals counts via targeted SQL (cached 60s)
        $queueCounts = CacheHelper::safeRemember('dashboard:agency_queue_counts:'.$agencyId, 60, function () use ($agencyId) {
            $row = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - created_at))/86400 >= 5) AS overdue,
                    COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '2 days') AS new_count
                FROM referrals
                WHERE agcy_id = ? AND is_deleted = false
            ", [$agencyId]);

            return ['overdue' => (int) $row->overdue, 'new_count' => (int) $row->new_count];
        });
        $overdueReferrals = $queueCounts['overdue'];
        $newReferralsCount = $queueCounts['new_count'];

        $workQueue = [
            $this->queueItem('newReferrals', 'New referrals', $newReferralsCount, 'Received in the last two days.', 'blue', 'move_to_inbox', '/referrals?age_max_days=2'),
            $this->queueItem('pendingReferrals', 'Pending', $pendingReferrals, 'Needs acknowledgement or first action.', 'amber', 'schedule', '/referrals?status=PENDING'),
            $this->queueItem('forComplianceReferrals', 'For compliance', $forComplianceReferrals, 'Waiting on missing requirements.', 'orange', 'fact_check', '/referrals?status=FOR_COMPLIANCE'),
            $this->queueItem('processingReferrals', 'Processing', $processingReferrals, 'Currently being handled.', 'cyan', 'sync', '/referrals?status=PROCESSING'),
            $this->queueItem('overdueReferrals', 'Overdue', $overdueReferrals, 'Active referrals older than five days.', 'rose', 'warning', '/referrals?age_min_days=5'),
            $this->queueItem('returnedReferrals', 'Rejected', $rejectedReferrals, 'Needs review or clarification.', 'rose', 'assignment_return', '/referrals?status=REJECTED'),
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
            'forComplianceReferrals' => $forComplianceReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'overdueReferrals' => $overdueReferralsList,
            'recentActivity' => $recentActivity,
            'workQueue' => $workQueue,
            'referralStatusDistribution' => $referralStatusDistribution,
            'referralAgingBands' => $referralAgingBands,
            'serviceDemand' => $serviceDemand,
            'feedbackPulse' => $feedbackPulse,
        ];
    }

    public function getAdminData(): array
    {
        $formatter = app(AuditLogFormatter::class);

        $activeReferralStatuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];
        $dashboardWindow = now()->subDays(5);

        // Single aggregated query for case + referral counts (cached 60s)
        $countsKey = 'dashboard:admin_counts_v2';
        [$caseCounts, $refCounts] = CacheHelper::safeRemember($countsKey, 60, function () use ($dashboardWindow) {
            $caseCounts = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status != 'DRAFT') AS total,
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
            $overdueThreshold = now()->subDays(DashboardQueries::OVERDUE_DAYS);

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
                'note' => 'Older than five days.',
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
                    'client_type' => $c->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
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
