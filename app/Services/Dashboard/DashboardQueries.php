<?php

namespace App\Services\Dashboard;

use App\Models\CaseFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardQueries
{
    public const OVERDUE_DAYS = 5;

    // ──────────────────────────────────────────────────────────────────────────────
    // Optimized SQL-based dashboard builders
    // ──────────────────────────────────────────────────────────────────────────────

    public function buildReferralAgingBandsSQL(?string $agencyId = null): array
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

    /**
     * Single referral-list builder. $priorityOnly adds the CASE-scored
     * wrapper for CM/admin priority queues; otherwise oldest-first
     * (agency queues, with optional overdue filter).
     */
    public function buildReferralListSQL(?string $agencyId = null, int $limit = 8, bool $includeAgency = true, ?string $status = null, bool $overdueOnly = false, bool $priorityOnly = false): array
    {
        $filter = '';
        $bindings = [];
        if ($agencyId) {
            $filter .= ' AND r.agcy_id = ?';
            $bindings[] = $agencyId;
        }
        if ($status) {
            $filter .= ' AND r.status = ?';
            $bindings[] = $status;
        }
        if ($overdueOnly) {
            $filter .= " AND r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5";
        }
        $bindings[] = $limit;

        $scoreColumn = $priorityOnly ? ',
                    CASE
                        WHEN r.status = \'REJECTED\' THEN 100 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status = \'FOR_COMPLIANCE\' THEN 80 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status = \'PENDING\' THEN 60 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status = \'PROCESSING\' AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5 THEN 40 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        WHEN r.status IN (\'PENDING\',\'PROCESSING\',\'FOR_COMPLIANCE\') AND EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 >= 5 THEN 30 + EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                        ELSE 0
                    END AS priority_score' : '';

        $inner = "
            SELECT r.id, r.case_id, r.status,
                COALESCE(
                    (SELECT STRING_AGG(s.name, ', ' ORDER BY s.name) FROM referral_services rs JOIN services s ON s.id = rs.service_id WHERE rs.referral_id = r.id),
                    r.required_services
                ) AS service_names,
                r.created_at,
                a.name AS agency_name, c.case_number, c.tracker_number, cl.first_name, cl.last_name,
                EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400 AS age_days{$scoreColumn}
            FROM referrals r
            LEFT JOIN agencies a ON a.id = r.agcy_id
            LEFT JOIN cases c ON c.id = r.case_id AND c.is_deleted = false
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE r.is_deleted = false {$filter}
        ";

        $sql = $priorityOnly
            ? "SELECT * FROM ({$inner}) sub WHERE priority_score > 0 ORDER BY priority_score DESC LIMIT ?"
            : "{$inner} ORDER BY r.created_at ASC LIMIT ?";

        $rows = DB::select($sql, $bindings);

        return array_map(fn ($row) => [
            'id' => $row->id,
            'case_id' => $row->case_id,
            'case_number' => $row->case_number ?? 'N/A',
            'tracking_number' => $row->tracker_number ?? null,
            'client_name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: 'Unnamed',
            'service' => $row->service_names ?: 'Service not specified',
            'agency_name' => $includeAgency ? ($row->agency_name ?? 'N/A') : null,
            'status' => $row->status,
            'age_days' => (int) round($row->age_days),
            'referred_at' => $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null,
            'href' => '/referrals/'.$row->id,
        ], $rows);
    }

    public function buildAgencyResponseScorecardSQL(?string $agencyId = null): array
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
            'totalReferrals' => (int) $row->total,
            'activeCount' => (int) $row->active_count,
            'overdueCount' => (int) $row->overdue_count,
            'completedCount' => (int) $row->completed_count,
            'averageCompletionDays' => $row->avg_days !== null ? round((float) $row->avg_days, 1) : null,
            'completionRate' => (int) $row->total > 0 ? (int) round(((int) $row->completed_count / (int) $row->total) * 100) : 0,
            'overdueRate' => (int) $row->active_count > 0 ? (int) round(((int) $row->overdue_count / (int) $row->active_count) * 100) : 0,
            'href' => '/referrals',
        ], $rows);
    }

    public function buildAgencyBreakdownSQL(): array
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

    public function buildPriorityCasesSQL(int $limit = 8): array
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
                    'Rejected referral' AS reason,
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

    /**
     * Intake-review list: the manager's own DRAFTs plus unassigned self-filed
     * intakes awaiting review. Oldest first so the longest-waiting intake
     * surfaces at the top. Capped to keep the deferred payload light.
     */
    public function buildIntakeReviewList(?string $userId, int $limit = 8): array
    {
        return CaseFile::with(['client'])
            ->select('id', 'case_number', 'tracker_number', 'client_id', 'client_type', 'status', 'source', 'user_id', 'created_at', 'updated_at')
            ->where('status', 'DRAFT')
            ->where('is_deleted', false)
            ->where(function ($q) use ($userId) {
                $q->where('source', CaseFile::SOURCE_SELF_FILED);
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            })
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'caseNo' => $c->case_number,
                'trackerNumber' => $c->tracker_number,
                'clientName' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                'status' => $c->status,
                'source' => $c->source,
                'isOwnDraft' => $userId ? $c->user_id === $userId : false,
                'createdAt' => $c->created_at?->toISOString() ?? now()->toISOString(),
                'href' => '/cases/'.$c->id,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Ready-to-close list: OPEN cases that have at least one referral and
     * zero non-COMPLETED referrals. Row shape mirrors buildPriorityCasesSQL.
     */
    public function buildReadyToCloseListSQL(int $limit = 8): array
    {
        $rows = DB::select("
            SELECT c.id, c.case_number, c.tracker_number, c.status, c.created_at,
                cl.first_name, cl.last_name,
                'Ready to close' AS reason,
                'COMPLETED' AS latest_referral_status
            FROM cases c
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.status = 'OPEN' AND c.is_deleted = false
                AND EXISTS (SELECT 1 FROM referrals WHERE case_id = c.id AND is_deleted = false)
                AND NOT EXISTS (SELECT 1 FROM referrals WHERE case_id = c.id AND status != 'COMPLETED' AND is_deleted = false)
            ORDER BY c.updated_at DESC
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

    /**
     * Aggregate referral stats for a set of case IDs in a single query.
     * Returns [case_id => [...aggregates...]].
     *
     * Status severity (most → least): REJECTED > FOR_COMPLIANCE > PENDING > PROCESSING > COMPLETED
     */
    public function buildRecentCasesReferralAggregates(array $caseIds): array
    {
        if (empty($caseIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
        $overdueThreshold = now()->subDays(self::OVERDUE_DAYS);

        // Single query: per-case aggregates for total, active, overdue counts,
        // worst status, and max age of active referrals.
        $rows = DB::select("
            SELECT
                r.case_id,
                COUNT(*)::int AS referral_count,
                COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE'))::int AS active_referral_count,
                COUNT(*) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE') AND r.created_at < ?)::int AS overdue_referral_count,
                MAX(CASE WHEN r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')
                    THEN EXTRACT(EPOCH FROM (NOW() - r.created_at))/86400
                    ELSE NULL END) AS max_referral_age_days,
                MAX(CASE
                    WHEN r.status = 'REJECTED' THEN 5
                    WHEN r.status = 'FOR_COMPLIANCE' THEN 4
                    WHEN r.status = 'PENDING' THEN 3
                    WHEN r.status = 'PROCESSING' THEN 2
                    WHEN r.status = 'COMPLETED' THEN 1
                    ELSE 0
                END) AS worst_severity
            FROM referrals r
            WHERE r.case_id IN ({$placeholders}) AND r.is_deleted = false
            GROUP BY r.case_id
        ", array_merge([$overdueThreshold], $caseIds));

        $severityToStatus = [
            5 => 'REJECTED',
            4 => 'FOR_COMPLIANCE',
            3 => 'PENDING',
            2 => 'PROCESSING',
            1 => 'COMPLETED',
        ];

        $result = [];
        foreach ($rows as $row) {
            $maxAge = $row->max_referral_age_days !== null ? (float) $row->max_referral_age_days : null;
            $overdueCount = (int) $row->overdue_referral_count;
            $result[$row->case_id] = [
                'referral_count' => (int) $row->referral_count,
                'active_referral_count' => (int) $row->active_referral_count,
                'overdue_referral_count' => $overdueCount,
                'worst_referral_status' => $severityToStatus[(int) $row->worst_severity] ?? null,
                'max_referral_age_days' => $maxAge !== null ? (int) round($maxAge) : null,
                'is_overdue' => $overdueCount > 0 || ($maxAge !== null && $maxAge >= self::OVERDUE_DAYS),
            ];
        }

        return $result;
    }

    public function buildAgencyServiceDemand(?string $agencyId): array
    {
        if (! $agencyId) {
            return [];
        }

        $rows = DB::select("
            SELECT s.id AS service_id, s.name AS service_name,
                COUNT(rs.referral_id) AS total_count,
                COUNT(rs.referral_id) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) AS active_count,
                COUNT(rs.referral_id) FILTER (WHERE r.status = 'COMPLETED') AS completed_count
            FROM services s
            LEFT JOIN referral_services rs ON rs.service_id = s.id
            LEFT JOIN referrals r ON r.id = rs.referral_id AND r.is_deleted = false
            WHERE s.agcy_id = ? AND s.is_deleted = false
            GROUP BY s.id, s.name
            HAVING COUNT(rs.referral_id) > 0
            ORDER BY COUNT(rs.referral_id) FILTER (WHERE r.status IN ('PENDING','PROCESSING','FOR_COMPLIANCE')) DESC
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

    public function buildFeedbackPulse(?string $agencyId): array
    {
        if (! $agencyId) {
            return [
                'hasData' => false,
                'totalSent' => 0,
                'totalSubmitted' => 0,
                'responseRate' => 0,
                'avgRating' => null,
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
            'href' => '/surveys',
        ];
    }
}
