<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\Referral;
use App\Services\Reports\CaseMetrics;
use App\Services\Reports\ClientMetrics;
use App\Services\Reports\Concerns\ScopesReportQueries;
use App\Services\Reports\ReferralMetrics;
use App\Services\Reports\ReportLookups;
use App\Services\Reports\ReportsCache;

class ReportsService
{
    use ScopesReportQueries;

    // â”€â”€ Cache Keys & TTLs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private const CACHE_TTL_PAYLOAD = 180;       // 3 minutes â€” full report payload

    // BC alias — the canonical key lives on ReportLookups.
    public const KEY_REFERENCE_DATA = ReportLookups::KEY_REFERENCE_DATA;

    public function __construct(
        private readonly ReferralMetrics $referrals = new ReferralMetrics,
        private readonly CaseMetrics $cases = new CaseMetrics,
        private readonly ClientMetrics $clients = new ClientMetrics,
        private readonly ReportLookups $lookups = new ReportLookups,
    ) {}

    public function getAll(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
    ): array {
        // Do this before constructing or reading the cache key.  An unassigned
        // scoped user must never be able to receive a cached report generated
        // for another identity.
        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            $empty = $this->emptyPayload($role);
            $empty['role'] = $role;

            return $empty;
        }

        // Build cache key from all parameters that affect the output
        $cacheKey = 'reports:payload:'.hash('sha256', implode('|', [
            $userId ?? '', $role ?? '', $agencyId ?? '',
            $fromDate ?? '', $toDate ?? '', $dateScope,
            $province ?? '', $city ?? '',
        ]));

        return CacheHelper::safeRemember($cacheKey, self::CACHE_TTL_PAYLOAD, function () use (
            $userId, $role, $agencyId, $fromDate, $toDate, $dateScope, $province, $city
        ) {
            $data = match ($role) {
                'AGENCY' => $this->getAgencyPayload($userId, $fromDate, $toDate, $dateScope, $province, $city, $agencyId),
                'ADMIN' => $this->getAdminPayload($fromDate, $toDate, $dateScope, $province, $city, $agencyId),
                default => $this->getCaseManagerPayload($userId, $fromDate, $toDate, $dateScope, $province, $city, $agencyId),
            };

            $data['role'] = $role;

            return $data;
        });
    }

    private function getCaseManagerPayload(
        ?string $userId,
        ?string $fromDate,
        ?string $toDate,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->referrals->getReferralKpis($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralStatusDistribution' => $this->referrals->getReferralStatusDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'rejectionReasonDistribution' => $this->referrals->getRejectionReasonDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAgencyDistribution' => $this->referrals->getReferralAgencyDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralTrends' => $this->referrals->getReferralTrends($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'casesOverTime' => $this->cases->getCasesOverTime($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'genderDistribution' => $this->clients->getGenderDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'clientTypeDistribution' => $this->cases->getClientTypeDistribution($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'ageGroupDistribution' => $this->clients->getAgeGroupDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'mostRequestedService' => $this->referrals->getMostRequestedService($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'cycleTimeDistribution' => $this->referrals->getReferralCycleTimeDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAging' => $this->referrals->getReferralAging($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyScorecard' => $this->referrals->getAgencyScorecard($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicDistribution' => $this->clients->getGeographicDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicMapData' => $this->clients->getGeographicMapData($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'categoryDistribution' => $this->cases->categoryDistribution($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'employmentDistribution' => $this->clients->getLastEmploymentDistribution($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'employmentOccupationBreakdown' => $this->clients->getEmploymentOccupationBreakdown($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'caseStatusDistribution' => $this->cases->getCaseStatusDistribution($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'caseIssueDistribution' => $this->cases->getCaseIssueDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'overdueReferrals' => $this->referrals->getOverdueReferrals($userId, 'CASE_MANAGER', $province, $city, $agencyId),
            'cityDistribution' => $this->clients->getCityDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'vulnerabilityDistribution' => $this->cases->getVulnerabilityDistribution($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'caseSourceDistribution' => $this->cases->getCaseSourceDistribution($userId, 'CASE_MANAGER', $agencyId, $from, $to, $province, $city),
            'closedCasesOverTime' => $this->cases->getClosedCasesOverTime($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'reopenedStats' => $this->cases->getReopenedStats($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'caseEventActorDistribution' => $this->cases->getCaseEventActorDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyFirstResponse' => $this->referrals->getAgencyFirstResponse($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'clientRequestTypeDistribution' => $this->referrals->getClientRequestTypeDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
        ];
    }

    private function getAgencyPayload(
        ?string $userId,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->referrals->getReferralKpis(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralStatusDistribution' => $this->referrals->getReferralStatusDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'rejectionReasonDistribution' => $this->referrals->getRejectionReasonDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralTrends' => $this->referrals->getReferralTrends(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'avgReferralCompletion' => $this->referrals->getAvgReferralCompletionDays(role: 'AGENCY', agencyId: $agencyId),
            'cycleTimeDistribution' => $this->referrals->getReferralCycleTimeDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyScorecard' => $this->referrals->getAgencyScorecard(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'categoryDistribution' => $this->cases->categoryDistribution(null, 'AGENCY', $agencyId, $from, $to, $province, $city),
            'caseStatusDistribution' => $this->cases->getCaseStatusDistribution(null, 'AGENCY', $agencyId, $from, $to, $province, $city),
            'genderDistribution' => $this->clients->getGenderDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'ageGroupDistribution' => $this->clients->getAgeGroupDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'clientTypeDistribution' => $this->cases->getClientTypeDistribution(null, 'AGENCY', $agencyId, $from, $to, $province, $city),
            'geographicMapData' => $this->clients->getGeographicMapData(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'caseSourceDistribution' => $this->cases->getCaseSourceDistribution(null, 'AGENCY', $agencyId, $from, $to, $province, $city),
            'closedCasesOverTime' => $this->cases->getClosedCasesOverTime(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'reopenedStats' => $this->cases->getReopenedStats(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'caseEventActorDistribution' => $this->cases->getCaseEventActorDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyFirstResponse' => $this->referrals->getAgencyFirstResponse(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'clientRequestTypeDistribution' => $this->referrals->getClientRequestTypeDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
        ];
    }

    private function getAdminPayload(?string $fromDate, ?string $toDate, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->referrals->getReferralKpis(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'overview' => $this->cases->getOverview($from, $to, $agencyId),
            'caseTrends' => $this->cases->getCaseTrends(agencyId: $agencyId),
            'referralStatusDistribution' => $this->referrals->getReferralStatusDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'rejectionReasonDistribution' => $this->referrals->getRejectionReasonDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'referralTrends' => $this->referrals->getReferralTrends(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyWorkload' => $this->referrals->getAgencyWorkload($from, $to, $agencyId),
            'clientTypeDistribution' => $this->cases->getClientTypeDistribution(null, null, $agencyId, $from, $to, $province, $city),
            'cycleTimeDistribution' => $this->referrals->getReferralCycleTimeDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAging' => $this->referrals->getReferralAging(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicDistribution' => $this->clients->getGeographicDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicMapData' => $this->clients->getGeographicMapData(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyScorecard' => $this->referrals->getAgencyScorecard(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'categoryDistribution' => $this->cases->categoryDistribution(null, null, $agencyId, $from, $to, $province, $city),
            'employmentDistribution' => $this->clients->getLastEmploymentDistribution(null, null, $agencyId, $from, $to, $province, $city),
            'employmentOccupationBreakdown' => $this->clients->getEmploymentOccupationBreakdown(null, null, $agencyId, $from, $to, $province, $city),
            'caseStatusDistribution' => $this->cases->getCaseStatusDistribution(null, null, $agencyId, $from, $to, $province, $city),
            'caseIssueDistribution' => $this->cases->getCaseIssueDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'vulnerabilityDistribution' => $this->cases->getVulnerabilityDistribution(null, null, $agencyId, $from, $to, $province, $city),
            'genderDistribution' => $this->clients->getGenderDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'ageGroupDistribution' => $this->clients->getAgeGroupDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAgencyDistribution' => $this->referrals->getReferralAgencyDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'caseSourceDistribution' => $this->cases->getCaseSourceDistribution(null, null, $agencyId, $from, $to, $province, $city),
            'closedCasesOverTime' => $this->cases->getClosedCasesOverTime(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'reopenedStats' => $this->cases->getReopenedStats(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'caseEventActorDistribution' => $this->cases->getCaseEventActorDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyFirstResponse' => $this->referrals->getAgencyFirstResponse(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'clientRequestTypeDistribution' => $this->referrals->getClientRequestTypeDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
        ];
    }

    private function emptyPayload(?string $role): array
    {
        $zeroKpis = [
            'totalReferrals' => 0, 'totalCases' => 0, 'openCases' => 0,
            'completedReferrals' => 0, 'pendingReferrals' => 0,
            'processingReferrals' => 0, 'forComplianceReferrals' => 0,
            'rejectedReferrals' => 0, 'completionRate' => 0,
            'avgCompletionDays' => 0, 'avgResolutionDays' => 0,
            'kpiChanges' => [
                'totalReferrals' => 0, 'completedReferrals' => 0,
                'pendingReferrals' => 0, 'completionRate' => 0,
                'avgCompletionDays' => 0,
            ],
        ];

        if ($role === 'AGENCY') {
            return [
                'kpis' => $zeroKpis,
                'referralStatusDistribution' => ['labels' => ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'], 'data' => [0, 0, 0, 0, 0], 'colors' => []],
                'rejectionReasonDistribution' => ['labels' => Referral::REJECTION_REASONS, 'data' => [0, 0, 0, 0, 0, 0], 'colors' => []],
                'referralTrends' => ['labels' => [], 'datasets' => [['label' => 'Referrals Created', 'data' => [], 'borderColor' => '#0b5a8c', 'backgroundColor' => 'rgba(11, 90, 140, 0.1)']]],
                'avgReferralCompletion' => 0,
                'cycleTimeDistribution' => ['labels' => [], 'data' => [], 'colors' => []],
                'agencyScorecard' => [], 'categoryDistribution' => [],
                'caseStatusDistribution' => ['labels' => ['OPEN', 'CLOSED', 'DRAFT'], 'data' => [0, 0, 0], 'colors' => []],
                'genderDistribution' => ['labels' => ['Male', 'Female', 'Unknown'], 'data' => [0, 0, 0], 'colors' => []],
                'ageGroupDistribution' => ['labels' => ['0-17', '18-25', '26-40', '41-60', '60+'], 'data' => [0, 0, 0, 0, 0], 'colors' => []],
                'clientTypeDistribution' => ['labels' => [], 'data' => [], 'colors' => []],
                'geographicMapData' => ['provinces' => []],
                'caseSourceDistribution' => ['labels' => ['Internal', 'Self-filed'], 'data' => [0, 0], 'colors' => []],
                'closedCasesOverTime' => [],
                'reopenedStats' => ['reopenedCount' => 0, 'repeatClients' => 0, 'totalClients' => 0, 'repeatClientRate' => 0],
                'caseEventActorDistribution' => ['labels' => ['Agency', 'Case manager', 'System'], 'data' => [0, 0, 0], 'colors' => []],
                'agencyFirstResponse' => [],
                'clientRequestTypeDistribution' => ['labels' => ['Document request', 'Question', 'Information update'], 'data' => [0, 0, 0], 'colors' => []],
            ];
        }

        return [
            'kpis' => $zeroKpis, 'referralStatusDistribution' => [],
            'rejectionReasonDistribution' => ['labels' => Referral::REJECTION_REASONS, 'data' => [0, 0, 0, 0, 0, 0], 'colors' => []],
            'referralAgencyDistribution' => [], 'referralTrends' => [],
            'casesOverTime' => [], 'genderDistribution' => [],
            'clientTypeDistribution' => [], 'ageGroupDistribution' => [],
            'mostRequestedService' => ['name' => 'N/A', 'value' => 0],
            'cycleTimeDistribution' => [], 'referralAging' => [],
            'agencyScorecard' => [], 'geographicDistribution' => [],
            'geographicMapData' => ['provinces' => []], 'categoryDistribution' => [],
            'employmentDistribution' => [],             'employmentOccupationBreakdown' => ['labels' => [], 'data' => [], 'total_distinct' => 0],
            'caseStatusDistribution' => [], 'caseIssueDistribution' => [],
            'overdueReferrals' => ['count' => 0, 'referrals' => []],
            'cityDistribution' => [], 'vulnerabilityDistribution' => [],
            'caseSourceDistribution' => ['labels' => ['Internal', 'Self-filed'], 'data' => [0, 0], 'colors' => []],
            'closedCasesOverTime' => [],
            'reopenedStats' => ['reopenedCount' => 0, 'repeatClients' => 0, 'totalClients' => 0, 'repeatClientRate' => 0],
            'caseEventActorDistribution' => ['labels' => ['Agency', 'Case manager', 'System'], 'data' => [0, 0, 0], 'colors' => []],
            'agencyFirstResponse' => [],
            'clientRequestTypeDistribution' => ['labels' => ['Document request', 'Question', 'Information update'], 'data' => [0, 0, 0], 'colors' => []],
        ];
    }

    public function getReferralKpis(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getReferralKpis($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getReferralTrends(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getReferralTrends($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getReferralStatusDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getReferralStatusDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getRejectionReasonDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getRejectionReasonDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getReferralAgencyDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getReferralAgencyDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getMostRequestedService(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getMostRequestedService($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getReferralCycleTimeDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        return $this->referrals->getReferralCycleTimeDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getReferralAging(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        return $this->referrals->getReferralAging($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getAgencyScorecard(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        return $this->referrals->getAgencyScorecard($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getAgencyWorkload(?string $fromDate = null, ?string $toDate = null, ?string $agencyId = null): array
    {
        return $this->referrals->getAgencyWorkload($fromDate, $toDate, $agencyId);
    }

    public function getAgencyFirstResponse(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getAgencyFirstResponse($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getClientRequestTypeDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getClientRequestTypeDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getAvgReferralCompletionDays(?string $role = null, ?string $agencyId = null): float
    {
        return $this->referrals->getAvgReferralCompletionDays($role, $agencyId);
    }

    public function getOverdueReferrals(?string $userId = null, ?string $role = null, ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->referrals->getOverdueReferrals($userId, $role, $province, $city, $agencyId);
    }

    public function getOverview(?string $fromDate = null, ?string $toDate = null, ?string $agencyId = null): array
    {
        return $this->cases->getOverview($fromDate, $toDate, $agencyId);
    }

    public function getCaseTrends(int $months = 12, ?string $agencyId = null): array
    {
        return $this->cases->getCaseTrends($months, $agencyId);
    }

    public function getCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->cases->getCasesOverTime($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getClosedCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->cases->getClosedCasesOverTime($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getReopenedStats(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->cases->getReopenedStats($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getCaseEventActorDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->cases->getCaseEventActorDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getCaseSourceDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null, ?string $fromDate = null, ?string $toDate = null, ?string $province = null, ?string $city = null): array
    {
        return $this->cases->getCaseSourceDistribution($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function getCaseStatusDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        return $this->cases->getCaseStatusDistribution($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function categoryDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        return $this->cases->categoryDistribution($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function getCaseIssueDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        return $this->cases->getCaseIssueDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getVulnerabilityDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        return $this->cases->getVulnerabilityDistribution($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function getClientTypeDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        return $this->cases->getClientTypeDistribution($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function getGenderDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->clients->getGenderDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getAgeGroupDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->clients->getAgeGroupDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getLastEmploymentDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        return $this->clients->getLastEmploymentDistribution($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function getEmploymentOccupationBreakdown(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        return $this->clients->getEmploymentOccupationBreakdown($userId, $role, $agencyId, $fromDate, $toDate, $province, $city);
    }

    public function getGeographicDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        return $this->clients->getGeographicDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getGeographicMapData(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        return $this->clients->getGeographicMapData($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    public function getCityDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        return $this->clients->getCityDistribution($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);
    }

    /**
     * Reference rows that drive the chart toggle controls (statuses, categories,
     * case issues). Sourced from the live reference tables â€” active only, ordered
     * by sort_order â€” so toggle lists and colors never drift from hard-coded literals.
     */
    public function getReferenceData(): array
    {
        return $this->lookups->getReferenceData();
    }

    /**
     * Role-scoped agency options for the agency filter dropdown.
     *
     * Admin and CASE_MANAGER: all active agencies.
     * Agency: empty array â€” the selector is hidden for Agency users.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getAgencyOptions(?string $userId = null, ?string $role = null): array
    {
        return $this->lookups->getAgencyOptions($userId, $role);
    }

    public function getProvinceOptions(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        return $this->lookups->getProvinceOptions($userId, $role, $agencyId);
    }

    public function getCityOptions(?string $province = null, ?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        return $this->lookups->getCityOptions($province, $userId, $role, $agencyId);
    }

    /**
     * Flush all reports caches. Called when cases/referrals/agencies change.
     */
    public static function invalidateAll(): void
    {
        ReportsCache::invalidateAll();
    }
}
