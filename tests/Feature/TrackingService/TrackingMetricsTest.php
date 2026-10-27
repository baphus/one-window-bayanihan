<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

/**
 * Client-facing tracking metrics: the completion percentage, the rejected
 * referral count, and the query budget of the timeline read model.
 *
 * Weighting contract (TrackingService::buildTrackingData):
 *   COMPLETED = 100, PROCESSING = 66, FOR_COMPLIANCE = 33, PENDING = 10,
 *   everything else (notably REJECTED) = 0.
 * Rejection is an outcome, not progress, so a rejected referral contributes
 * nothing to the numerator but still counts toward the denominator; it is
 * surfaced separately through `rejectedCount`.
 *
 * The payload is built for an anonymous endpoint, so the final test pins that
 * the timeline is derived from exactly one `case_events` read and never from
 * the staff-only `audit_logs` table.
 */
class TrackingMetricsTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that buildTrackingData() requires.
     */
    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ]);

        return $case;
    }

    /**
     * Build the tracking payload for a case with its relations eager-loaded.
     */
    private function trackingData(CaseFile $case): array
    {
        return app(TrackingService::class)->buildTrackingData($this->loadRelations($case));
    }

    public function test_completion_percentage_key_exists(): void
    {
        // ARRANGE
        $case = $this->createCompleteCase()['case'];

        // ACT
        $data = $this->trackingData($case);

        // ASSERT — both metric keys are always present, even on a bare payload
        $this->assertTrue(array_key_exists('completionPercentage', $data));
        $this->assertTrue(array_key_exists('rejectedCount', $data));
    }

    public function test_completion_percentage_zero_when_no_referrals(): void
    {
        // ARRANGE — a case with no referrals has no progress to report
        $case = $this->createCompleteCase(referralCount: 0)['case'];

        // ACT
        $data = $this->trackingData($case);

        // ASSERT — 0/0 must not divide by zero; the guard returns 0
        $this->assertEquals(0, $data['completionPercentage']);
        $this->assertEquals(0, $data['rejectedCount']);
    }

    public function test_completion_percentage_all_pending_low(): void
    {
        // ARRANGE — 3 PENDING referrals (the trait creates them PENDING)
        $case = $this->createCompleteCase(referralCount: 3)['case'];

        // ACT
        $data = $this->trackingData($case);

        // ASSERT — PENDING = 10 each, 3 referrals = 30/300 = 10%
        $this->assertEquals(10, $data['completionPercentage']);
    }

    public function test_completion_percentage_mixed_statuses(): void
    {
        // ARRANGE — one referral per weight tier
        $case = $this->createCompleteCase(referralCount: 0)['case'];
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);         // 10
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'FOR_COMPLIANCE']);  // 33
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);      // 66
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'COMPLETED']);       // 100

        // ACT
        $data = $this->trackingData($case);

        // ASSERT — (10 + 33 + 66 + 100) / 400 = 209/400 = 52%
        $this->assertEquals(52, $data['completionPercentage']);
    }

    public function test_completion_percentage_excludes_rejected_referrals(): void
    {
        // ARRANGE — 2 completed referrals and 1 rejected one
        $case = $this->createCompleteCase(referralCount: 0)['case'];
        Referral::factory()->count(2)->create(['case_id' => $case->id, 'status' => 'COMPLETED']);
        Referral::factory()->rejected()->create(['case_id' => $case->id]);

        // ACT
        $data = $this->trackingData($case);

        // ASSERT — rejection is an outcome, not progress: (100 + 100 + 0) / 300 = 67%
        $this->assertEquals(67, $data['completionPercentage']);
        $this->assertEquals(1, $data['rejectedCount']);
    }

    public function test_all_rejected_case_reports_zero_progress(): void
    {
        // ARRANGE — every referral was turned down
        $case = $this->createCompleteCase(referralCount: 0)['case'];
        Referral::factory()->count(3)->rejected()->create(['case_id' => $case->id]);

        // ACT
        $data = $this->trackingData($case);

        // ASSERT — 0/300 = 0%, with all 3 rejections surfaced separately
        $this->assertEquals(0, $data['completionPercentage']);
        $this->assertEquals(3, $data['rejectedCount']);
    }

    public function test_timeline_uses_one_event_query_and_never_touches_audit_logs(): void
    {
        // ARRANGE — several non-pending referrals; the old implementation
        // issued one audit-log query per non-pending referral.
        $case = $this->createCompleteCase(referralCount: 3)['case'];
        $case->referrals()->update(['status' => 'PROCESSING']);
        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT — the eager-loading above happens before the log is enabled, so
        // only the queries issued by buildTrackingData() are counted.
        DB::enableQueryLog();
        $service->buildTrackingData($case);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // ASSERT
        $this->assertEquals(
            1,
            $queries->filter(fn ($sql) => str_contains($sql, 'case_events'))->count(),
            'Timeline construction must issue exactly one case_events query'
        );
        $this->assertEquals(
            0,
            $queries->filter(fn ($sql) => str_contains($sql, 'audit_logs'))->count(),
            'The public tracking payload must never be built from audit logs'
        );
    }
}
