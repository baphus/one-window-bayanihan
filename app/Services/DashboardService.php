<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const ACTIVE_REFERRAL_STATUSES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

    private const OVERDUE_DAYS = 5;

    /**
     * Generate a safe relative time string that never shows "from now".
     * Handles timezone discrepancies by clamping future timestamps to "Just now".
     */
    private function safeRelativeTime(?Carbon $timestamp): string
    {
        if (! $timestamp) {
            return 'N/A';
        }

        $now = Carbon::now();

        if ($timestamp->isAfter($now)) {
            return 'Just now';
        }

        return $timestamp->diffForHumans();
    }

    private function formatChangeSummary(array $changes): string
    {
        if (empty($changes)) {
            return '';
        }

        $first = $changes[0];
        $summary = $first['fieldLabel'].': ';

        if ($first['old'] !== null && $first['old'] !== 'not set') {
            $summary .= $first['old'].' → ';
        }

        $summary .= $first['new'] ?? '';

        if (count($changes) > 1) {
            $summary .= ' (+'.(count($changes) - 1).' more)';
        }

        return $summary;
    }

    private function queueItem(string $key, string $label, int $count, string $note, string $tone, string $icon, string $href): array
    {
        return compact('key', 'label', 'count', 'note', 'tone', 'icon', 'href');
    }

    private function ageInDays($timestamp): int
    {
        if (! $timestamp) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($timestamp, false) * -1);
    }

    private function daysBetween($start, $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        return max(0, (int) $start->startOfDay()->diffInDays($end->startOfDay(), false));
    }

    private function clientName($client): string
    {
        if (! $client) {
            return 'N/A';
        }

        return trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'N/A';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'FOR_COMPLIANCE' => 'For compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Returned',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'PENDING' => 'amber',
            'PROCESSING' => 'blue',
            'FOR_COMPLIANCE' => 'orange',
            'COMPLETED' => 'emerald',
            'REJECTED' => 'rose',
            default => 'slate',
        };
    }

    private function buildStatusDistributionFromCounts(array $statusCounts, int $total): array
    {
        $total = max($total, 1);

        return collect($statusCounts)
            ->map(fn (int $count, string $status) => [
                'status' => $status,
                'label' => $this->statusLabel($status),
                'count' => $count,
                'percent' => (int) round(($count / $total) * 100),
                'tone' => $this->statusTone($status),
            ])
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values()
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Optimized SQL-based dashboard builders
    // ──────────────────────────────────────────────────────────────────────────────

    private function buildReferralAgingBandsSQL(?string $agencyId = null): array
    {
        $where = "WHERE status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND is_deleted = false";
        $bindings = [];
        if ($agencyId) {
            $where .= ' AND agcy_id = ?';
            $bindings[] = $agencyId;
        }

        $row = DB::selectOne("
            SELECT
                COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM (NOW() - created_at))/86400 BETWEEN 0 AND 2) AS band_0_2,
                COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM (NOW() - created_at))/86400 > 2 AND EXTRACT(EPOCH FROM (NOW() - created_at))/86400 <= 5) AS band_3_5,
                COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM (NOW() - created_at))/86400 > 5 AND EXTRACT(EPOCH FROM (NOW() - created_at))/86400 <= 10) AS band_6_10,
                COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM (NOW() - created_at))/86400 > 10) AS band_11_plus,
                COUNT(*) AS total
            FROM referrals {$where}
        ", $bindings);

        $total = max((int) $row->total, 1);

        $bands = [
            ['key' => '0-2', 'label' => '0-2 days', 'count' => (int) $row->band_0_2, 'tone' => 'emerald'],
            ['key' => '3-5', 'label' => '3-5 days', 'count' => (int) $row->band_3_5, 'tone' => 'amber'],
            ['key' => '6-10', 'label' => '6-10 days', 'count' => (int) $row->band_6_10, 'tone' => 'orange'],
            ['key' => '11+', 'label' => '11+ days', 'count' => (int) $row->band_11_plus, 'tone' => 'rose'],
        ];

        return array_map(fn ($band) => [
            'key' => $band['key'],
            'label' => $band['label'],
            'count' => $band['count'],
            'percent' => (int) round(($band['count'] / $total) * 100),
            'tone' => $band['tone'],
        ], $bands);
    }

    private function buildPriorityReferralsSQL(?string $agencyId = null, int $limit = 8, bool $includeAgency = true): array
    {
        $agencyFilter = '';
        $bindings = [];
        if ($agencyId) {
            $agencyFilter = 'AND r.agcy_id = ?';
            $bindings[] = $agencyId;
        }
        $bindings[] = $limit;

        $rows = DB::select("
            SELECT * FROM (
                SELECT r.id, r.case_id, r.status, r.required_services, r.created_at,
                    a.name AS agency_name, c.case_number, cl.first_name, cl.last_name,
                    EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 AS age_days,
                    CASE
                        WHEN r.status = 'REJECTED' THEN 100 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status = 'FOR_COMPLIANCE' THEN 80 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status = 'PENDING' THEN 60 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status = 'PROCESSING' AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5 THEN 40 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5 THEN 30 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        ELSE 0
                    END AS priority_score
                FROM referrals r
                LEFT JOIN agencies a ON a.id = r.agcy_id
                LEFT JOIN cases c ON c.id = r.case_id AND c.is_deleted = false
                LEFT JOIN clients cl ON cl.id = c.client_id
                WHERE r.is_deleted = false {$agencyFilter}
            ) sub
            WHERE priority_score > 0
            ORDER BY priority_score DESC
            LIMIT ?
        ", $bindings);

        return array_map(fn ($row) => [
            'id' => $row->id,
            'caseId' => $row->case_id,
            'caseNo' => $row->case_number ?? 'N/A',
            'clientName' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: 'N/A',
            'service' => $row->required_services ?: 'Service not specified',
            'agencyName' => $includeAgency ? ($row->agency_name ?? 'N/A') : null,
            'status' => $row->status,
            'ageDays' => (int) round($row->age_days),
            'href' => '/referrals/'.$row->id,
        ], $rows);
    }

    private function buildAgencyResponseScorecardSQL(?string $agencyId = null): array
    {
        $agencyFilter = '';
        $bindings = [];
        if ($agencyId) {
            $agencyFilter = 'AND r.agcy_id = ?';
            $bindings[] = $agencyId;
        }

        $rows = DB::select("
            SELECT r.agcy_id, a.name AS agency_name,
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) AS active_count,
                COUNT(*) FILTER (WHERE r.status = 'COMPLETED') AS completed_count,
                COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5) AS overdue_count,
                AVG(EXTRACT(EPOCH FROM (r.updated_at - r.created_at))/86400) FILTER (WHERE r.status = 'COMPLETED') AS avg_days
            FROM referrals r
            JOIN agencies a ON a.id = r.agcy_id
            WHERE r.is_deleted = false {$agencyFilter}
            GROUP BY r.agcy_id, a.name
            ORDER BY (COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5) * 10 + COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE'))) DESC
            LIMIT 6
        ", $bindings);

        return array_map(fn ($row) => [
            'agencyId' => $row->agcy_id,
            'agencyName' => $row->agency_name ?? 'Unassigned agency',
            'activeCount' => (int) $row->active_count,
            'overdueCount' => (int) $row->overdue_count,
            'completedCount' => (int) $row->completed_count,
            'averageCompletionDays' => $row->avg_days !== null ? round((float) $row->avg_days, 1) : null,
            'completionRate' => (int) $row->total > 0 ? (int) round(((int) $row->completed_count / (int) $row->total) * 100) : 0,
            'href' => '/referrals',
        ], $rows);
    }

    private function buildAgencyBreakdownSQL(): array
    {
        $rows = DB::select("
            SELECT r.agcy_id, a.name AS agency_name,
                COUNT(*) AS total_referrals,
                COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) AS active_count,
                COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5) AS overdue_count
            FROM referrals r
            JOIN agencies a ON a.id = r.agcy_id
            WHERE r.is_deleted = false
            GROUP BY r.agcy_id, a.name
            HAVING COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) > 0
            ORDER BY COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) DESC
            LIMIT 6
        ");

        return array_map(fn ($row) => [
            'agencyId' => $row->agcy_id,
            'agencyName' => $row->agency_name ?? 'Unknown',
            'count' => (int) $row->active_count,
            'activeCount' => (int) $row->active_count,
            'overdueCount' => (int) $row->overdue_count,
            'totalReferrals' => (int) $row->total_referrals,
        ], $rows);
    }

    private function buildPriorityCasesSQL(int $limit = 8): array
    {
        $rows = DB::select("
            SELECT * FROM (
                -- Aging open cases (7+ days)
                SELECT c.id, c.case_number, c.tracker_number, c.status, c.created_at,
                    cl.first_name, cl.last_name,
                    'Aging open case' AS reason,
                    COALESCE(r_latest.status, NULL) AS latest_referral_status,
                    (40 + EXTRACT(EPOCH FROM (NOW() - c.created_at))/86400) AS priority_score
                FROM cases c
                LEFT JOIN clients cl ON cl.id = c.client_id
                LEFT JOIN LATERAL (
                    SELECT status FROM referrals WHERE case_id = c.id AND is_deleted = false ORDER BY updated_at DESC LIMIT 1
                ) r_latest ON true
                WHERE c.status = 'OPEN' AND c.is_deleted = false
                    AND EXTRACT(EPOCH FROM (NOW() - c.created_at))/86400 >= 7
                    AND EXISTS (SELECT 1 FROM referrals WHERE case_id = c.id AND is_deleted = false)
                    AND NOT EXISTS (SELECT 1 FROM referrals WHERE case_id = c.id AND status = 'REJECTED' AND is_deleted = false)

                UNION ALL

                -- Open cases with no referrals
                SELECT c.id, c.case_number, c.tracker_number, c.status, c.created_at,
                    cl.first_name, cl.last_name,
                    'No referral yet' AS reason,
                    NULL AS latest_referral_status,
                    80 AS priority_score
                FROM cases c
                LEFT JOIN clients cl ON cl.id = c.client_id
                WHERE c.status = 'OPEN' AND c.is_deleted = false
                    AND NOT EXISTS (SELECT 1 FROM referrals r WHERE r.case_id = c.id AND r.is_deleted = false)

                UNION ALL

                -- Cases with REJECTED referrals
                SELECT DISTINCT ON (c.id) c.id, c.case_number, c.tracker_number, c.status, c.created_at,
                    cl.first_name, cl.last_name,
                    'Returned referral' AS reason,
                    'REJECTED' AS latest_referral_status,
                    100 AS priority_score
                FROM cases c
                LEFT JOIN clients cl ON cl.id = c.client_id
                JOIN referrals r ON r.case_id = c.id AND r.status = 'REJECTED' AND r.is_deleted = false
                WHERE c.status = 'OPEN' AND c.is_deleted = false
            ) sub
            ORDER BY priority_score DESC
            LIMIT ?
        ", [$limit]);

        return array_map(fn ($row) => [
            'id' => $row->id,
            'caseNo' => $row->case_number ?? 'N/A',
            'trackerNumber' => $row->tracker_number,
            'clientName' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: 'N/A',
            'status' => $row->status,
            'latestReferralStatus' => $row->latest_referral_status,
            'ageDays' => (int) round(Carbon::parse($row->created_at)->diffInDays(now())),
            'reason' => $row->reason,
            'href' => '/cases/'.$row->id,
        ], $rows);
    }

    private function buildAgencyServiceDemand(?string $agencyId): array
    {
        if (! $agencyId) {
            return [];
        }

        $rows = DB::select("
            SELECT s.id AS service_id, s.name AS service_name,
                COUNT(r.id) AS total_count,
                COUNT(r.id) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) AS active_count,
                COUNT(r.id) FILTER (WHERE r.status = 'COMPLETED') AS completed_count
            FROM services s
            LEFT JOIN referrals r ON r.agcy_id = s.agcy_id AND r.required_services = s.name AND r.is_deleted = false
            WHERE s.agcy_id = ? AND s.is_deleted = false
            GROUP BY s.id, s.name
            HAVING COUNT(r.id) > 0
            ORDER BY COUNT(r.id) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) DESC
            LIMIT 6
        ", [$agencyId]);

        return array_map(fn ($row) => [
            'serviceId' => $row->service_id,
            'serviceName' => $row->service_name,
            'totalCount' => (int) $row->total_count,
            'activeCount' => (int) $row->active_count,
            'completedCount' => (int) $row->completed_count,
            'completionRate' => (int) $row->total_count > 0 ? (int) round(((int) $row->completed_count / (int) $row->total_count) * 100) : 0,
            'href' => '/referrals',
        ], $rows);
    }

    private function buildFeedbackPulse(?string $agencyId): array
    {
        if (! $agencyId) {
            return [
                'hasData' => false,
                'totalSent' => 0,
                'totalSubmitted' => 0,
                'responseRate' => 0,
                'avgRating' => null,
                'avgServqual' => null,
                'href' => '/surveys',
            ];
        }

        $row = DB::selectOne('
            SELECT
                COUNT(*) AS total_sent,
                COUNT(*) FILTER (WHERE submitted_at IS NOT NULL) AS total_submitted
            FROM survey_invitations WHERE agency_id = ?
        ', [$agencyId]);

        $totalSent = (int) ($row->total_sent ?? 0);
        $totalSubmitted = (int) ($row->total_submitted ?? 0);

        return [
            'hasData' => $totalSubmitted > 0 || $totalSent > 0,
            'totalSent' => $totalSent,
            'totalSubmitted' => $totalSubmitted,
            'responseRate' => $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0,
            'avgRating' => null,
            'avgServqual' => null,
            'href' => '/surveys',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Public dashboard data methods
    // ──────────────────────────────────────────────────────────────────────────────

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

            return [$caseCounts, $refCounts];
        });
        $totalCases = (int) $caseCounts->total;
        $openCases = (int) $caseCounts->open;
        $closedCases = (int) $caseCounts->closed;
        $totalReferrals = (int) $refCounts->total;
        $pendingReferrals = (int) $refCounts->pending;
        $processingReferrals = (int) $refCounts->processing;
        $completedReferrals = (int) $refCounts->completed;
        $rejectedReferrals = (int) $refCounts->rejected;
        $forComplianceReferrals = (int) $refCounts->for_compliance;

        $activeAgencies = CacheHelper::safeRemember('dashboard:active_agencies_count', 300, function () {
            return Agency::where('is_active', true)->count();
        });

        // Unique client count + OFW/NOK split via DB query (avoids loading all clients into memory)
        $clientCounts = CacheHelper::safeRemember('dashboard:cm_client_counts', 120, function () {
            return DB::selectOne('
                SELECT
                    COUNT(DISTINCT c.client_id) AS total,
                    COUNT(DISTINCT CASE WHEN c.client_type = \'OFW\' THEN c.client_id END) AS ofw,
                    COUNT(DISTINCT CASE WHEN c.client_type = \'NEXT_OF_KIN\' THEN c.client_id END) AS nok
                FROM referrals r
                JOIN cases c ON r.case_id = c.id AND c.is_deleted = false
                WHERE r.is_deleted = false AND c.client_id IS NOT NULL
            ');
        });
        $uniqueClientCount = (int) $clientCounts->total;
        $ofwCount = (int) $clientCounts->ofw;
        $nokCount = (int) $clientCounts->nok;

        // Recent activity (LIMIT 10, indexed — kept as-is)
        $recentActivity = AuditLog::with('user')
            ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(function ($log) use ($formatter) {
                try {
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'changes' => [],
                        'action' => $log->action,
                        'module' => $log->module,
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

        // Load allCases (trimmed: no 'user' eager load, only needed columns)
        $allCases = CaseFile::with(['client'])
            ->select('id', 'case_number', 'tracker_number', 'client_id', 'client_type', 'status', 'created_at', 'updated_at')
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where(function ($q) {
                $q->where('status', 'OPEN')
                    ->orWhere('created_at', '>', now()->subDays(30));
            })
            ->orderBy('created_at', 'desc')
            ->limit(200)
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
            return $this->buildReferralAgingBandsSQL();
        });
        $priorityReferrals = CacheHelper::safeRemember('dashboard:cm_priority_referrals', 60, function () {
            return $this->buildPriorityReferralsSQL(null, 8, true);
        });
        $priorityCases = CacheHelper::safeRemember('dashboard:cm_priority_cases', 60, function () {
            return $this->buildPriorityCasesSQL(8);
        });
        $agencyResponseScorecard = CacheHelper::safeRemember('dashboard:cm_scorecard', 60, function () {
            return $this->buildAgencyResponseScorecardSQL();
        });
        $agencyBreakdown = CacheHelper::safeRemember('dashboard:cm_agency_breakdown', 60, function () {
            return $this->buildAgencyBreakdownSQL();
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

        $workQueue = [
            $this->queueItem('agingOpenCases', 'Aging open cases', $agingOpenCasesCount, 'Open seven days or more.', 'amber', 'folder_clock', '/cases?status=OPEN&age_min_days=7'),
            $this->queueItem('pendingReferrals', 'Pending referrals', $pendingReferrals, 'Waiting for agency action.', 'amber', 'schedule', '/referrals?status=PENDING'),
            $this->queueItem('rejectedReferrals', 'Returned referrals', $rejectedReferrals, 'Needs reassignment or follow-up.', 'rose', 'assignment_return', '/referrals?status=REJECTED'),
            $this->queueItem('draftCases', 'Draft cases', $myDraftCount, 'Your unfinished case drafts.', 'slate', 'edit_note', '/cases/drafts'),
            $this->queueItem('casesWithoutReferrals', 'Cases without referrals', $casesWithoutReferrals, 'Open cases that may need routing.', 'blue', 'hub', '/cases?status=OPEN&referral_state=none'),
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
        ];
    }

    public function getAgencyData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

        $agencyId = $user?->agcy_id;

        // Single aggregated query for agency referral counts (cached 60s)
        $countsKey = 'dashboard:agency_counts:'.$agencyId;
        $refCounts = CacheHelper::safeRemember($countsKey, 60, function () use ($agencyId) {
            return DB::selectOne("
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
        $totalReferrals = (int) $refCounts->total;
        $pendingReferrals = (int) $refCounts->pending;
        $processingReferrals = (int) $refCounts->processing;
        $forComplianceReferrals = (int) $refCounts->for_compliance;
        $completedReferrals = (int) $refCounts->completed;
        $rejectedReferrals = (int) $refCounts->rejected;

        // Optimized SQL-based computations (replace $agencyReferrals bulk load)
        $referralStatusDistribution = $this->buildStatusDistributionFromCounts(
            ['PENDING' => $pendingReferrals, 'PROCESSING' => $processingReferrals, 'FOR_COMPLIANCE' => $forComplianceReferrals, 'COMPLETED' => $completedReferrals, 'REJECTED' => $rejectedReferrals],
            $totalReferrals
        );
        $referralAgingBands = CacheHelper::safeRemember('dashboard:agency_aging_bands:'.$agencyId, 60, function () use ($agencyId) {
            return $this->buildReferralAgingBandsSQL($agencyId);
        });
        $priorityReferrals = CacheHelper::safeRemember('dashboard:agency_priority:'.$agencyId, 60, function () use ($agencyId) {
            return $this->buildPriorityReferralsSQL($agencyId, 8, false);
        });
        $serviceDemand = CacheHelper::safeRemember('dashboard:agency_service_demand:'.$agencyId, 120, function () use ($agencyId) {
            return $this->buildAgencyServiceDemand($agencyId);
        });
        $feedbackPulse = CacheHelper::safeRemember('dashboard:agency_feedback_pulse:'.$agencyId, 300, function () use ($agencyId) {
            return $this->buildFeedbackPulse($agencyId);
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
            $this->queueItem('returnedReferrals', 'Returned', $rejectedReferrals, 'Needs review or clarification.', 'rose', 'assignment_return', '/referrals?status=REJECTED'),
        ];

        // Recent activity via subquery instead of loading all referral IDs
        $recentActivity = AuditLog::whereIn('entity_id', function ($query) use ($agencyId) {
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
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'changes' => [],
                        'action' => $log->action,
                        'module' => $log->module,
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

        return [
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'forComplianceReferrals' => $forComplianceReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'recentActivity' => $recentActivity,
            'workQueue' => $workQueue,
            'referralStatusDistribution' => $referralStatusDistribution,
            'referralAgingBands' => $referralAgingBands,
            'priorityReferrals' => $priorityReferrals,
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

        $operationalQueues = [
            [
                'key' => 'openCases',
                'label' => 'Open cases',
                'count' => $openCases,
                'note' => 'Active case files on deck.',
                'tone' => 'blue',
                'icon' => 'folder_open',
                'href' => '/cases?status=OPEN',
            ],
            [
                'key' => 'pendingReferrals',
                'label' => 'Pending referrals',
                'count' => $pendingReferrals,
                'note' => 'Waiting for agency action.',
                'tone' => 'amber',
                'icon' => 'schedule',
                'href' => '/referrals?status=PENDING',
            ],
            [
                'key' => 'processingReferrals',
                'label' => 'Processing',
                'count' => $processingReferrals,
                'note' => 'Already in motion.',
                'tone' => 'cyan',
                'icon' => 'sync',
                'href' => '/referrals?status=PROCESSING',
            ],
            [
                'key' => 'forComplianceReferrals',
                'label' => 'For compliance',
                'count' => $forComplianceReferrals,
                'note' => 'Needs missing documents.',
                'tone' => 'orange',
                'icon' => 'fact_check',
                'href' => '/referrals?status=FOR_COMPLIANCE',
            ],
            [
                'key' => 'overdueReferrals',
                'label' => 'Overdue referrals',
                'count' => $overdueReferrals,
                'note' => 'Older than five days.',
                'tone' => 'rose',
                'icon' => 'warning',
                'href' => '/overdue-referrals',
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
                        'ADMIN' => 'Administrators',
                        'CASE_MANAGER' => 'Case managers',
                        'AGENCY' => 'Agency users',
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

        $recentCases = CaseFile::with(['client', 'user', 'category'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('is_deleted', false)
            ->orderBy('updated_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn ($c) => [
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
            ])
            ->toArray();

        $recentLogs = AuditLog::with('user')
            ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
            ->orderBy('timestamp', 'desc')
            ->take(8)
            ->get()
            ->map(function ($log) use ($formatter) {
                try {
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'changes' => [],
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => $log->user?->name ?? 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                $changes = $display['changes'] ?? [];

                return [
                    'id' => $log->id,
                    'action' => $display['action'],
                    'module' => $display['module'],
                    'description' => $display['message'],
                    'user' => $log->user ? ['name' => $log->user->name] : null,
                    'timestamp' => $log->timestamp,
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'changes' => $changes,
                    'actor' => $display['actor'],
                    'hasChanges' => $display['hasChanges'],
                ];
            })
            ->toArray();

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
            'recentCases' => $recentCases,
            'recentLogs' => $recentLogs,
            'casesByCategory' => $casesByCategory,
        ];
    }
}
