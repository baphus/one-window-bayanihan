<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NOTE: AuditLog is created manually via AuditLog::create([...]) rather than
 * through an AuditLogFactory. This pattern is intentional — no AuditLogFactory
 * exists because audit logs have complex relationships and the manual creation
 * keeps test setup explicit and readable. See AuditLog model for fillable fields:
 * action, module, entity_id, description, old_value, new_value, user_id, timestamp.
 */
class TrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ]);

        return $case;
    }

    public function test_build_tracking_data_contains_milestone_timeline_key(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'client_id' => null]);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertTrue(array_key_exists('milestoneTimeline', $data));
        // The legacy audit-log timeline must no longer be shipped to the client.
        $this->assertArrayNotHasKey('caseTimeline', $data);
    }

    public function test_agency_cards_include_public_milestones_link(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertArrayHasKey('milestonesUrl', $data['trackingAgencies'][0]);
        $this->assertStringContainsString('/track/case/'.$case->tracker_number.'/referrals/'.$referral->id.'/milestones', $data['trackingAgencies'][0]['milestonesUrl']);
    }

    public function test_milestone_timeline_excludes_raw_audit_log_entries(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();

        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'case',
            'entity_id' => $case->id,
            'description' => 'set internal_note to confidential; set category_id to a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $this->loadRelations($case);
        $data = $service->buildTrackingData($case);

        $this->assertEmpty($data['milestoneTimeline']);

        foreach ($data['milestoneTimeline'] as $item) {
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['title'] ?? '');
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title'] ?? '');
        }
    }

    public function test_milestone_timeline_includes_case_opened_event(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        app(CaseEventRecorder::class)->caseOpened($case);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $timeline = $data['milestoneTimeline'];

        $this->assertEquals('case_opened', $timeline[0]['type']);
        $this->assertEquals('Your case has been opened', $timeline[0]['title']);
        $this->assertNull($timeline[0]['agency']);
    }

    public function test_milestone_timeline_includes_referral_sent_events(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        app(CaseEventRecorder::class)->referralSent($referral);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $sentItems = array_values(array_filter(
            $data['milestoneTimeline'],
            fn ($item) => $item['type'] === 'referral_sent'
        ));

        $this->assertNotEmpty($sentItems);
        $agencyName = $case->referrals->first()->agency->name;
        $this->assertEquals($agencyName, $sentItems[0]['agency']);
    }

    public function test_milestone_timeline_includes_milestone_events(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);
        app(CaseEventRecorder::class)->milestoneAdded($referral, $milestone);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $msItems = array_values(array_filter(
            $data['milestoneTimeline'],
            fn ($item) => $item['type'] === 'milestone_added'
        ));

        $this->assertNotEmpty($msItems);
        $this->assertEquals($milestone->title, $msItems[0]['title']);
    }

    public function test_milestone_timeline_sorted_chronologically(): void
    {
        $service = app(TrackingService::class);
        $recorder = app(CaseEventRecorder::class);
        $case = CaseFile::factory()->create(['created_at' => now()->subDays(5)]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        // Record out of order — the timeline must sort by occurred_at.
        $this->travelTo(now()->subDay(), fn () => $recorder->milestoneAdded($referral, $milestone));
        $this->travelTo(now()->subDays(5), fn () => $recorder->caseOpened($case));
        $this->travelTo(now()->subDays(3), fn () => $recorder->referralSent($referral));

        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $dates = array_column($data['milestoneTimeline'], 'date');

        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates);
        $this->assertCount(3, $dates);
    }

    public function test_case_status_mapping_open_returns_in_progress(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals('IN_PROGRESS', $data['trackedCase']['status']);
        $this->assertNotEquals('COMPLETED', $data['trackedCase']['status']);
    }

    public function test_case_status_mapping_closed_returns_resolved(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['status' => 'CLOSED']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals('RESOLVED', $data['trackedCase']['status']);
    }

    public function test_case_status_mapping_archived_returns_archived(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals('ARCHIVED', $data['trackedCase']['status']);
        $this->assertNotEquals('COMPLETED', $data['trackedCase']['status']);
    }

    public function test_agency_steps_has_correct_count_for_processing(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $steps = $data['trackingAgencies'][0]['steps'];

        // PROCESSING with no compliance history → 5 steps: Created, Referred, Received, Processing, Completed
        $this->assertCount(5, $steps);
        $this->assertEquals('Created', $steps[0]['label']);
        $this->assertEquals('complete', $steps[0]['state']);
        $this->assertStringContainsString('Referred to', $steps[1]['label']);
        $this->assertEquals('complete', $steps[1]['state']);
        $this->assertStringContainsString('Received by', $steps[2]['label']);
        $this->assertEquals('complete', $steps[2]['state']);
        $this->assertEquals('Processing', $steps[3]['label']);
        $this->assertEquals('active', $steps[3]['state']);
        $this->assertEquals('Completed', $steps[4]['label']);
        $this->assertEquals('pending', $steps[4]['state']);
    }

    public function test_agency_steps_for_compliance_is_active(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'FOR_COMPLIANCE']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $steps = $data['trackingAgencies'][0]['steps'];

        // FOR_COMPLIANCE → 6 steps: Created, Referred, Received, For Compliance, Processing after compliance, Completed
        $this->assertCount(6, $steps);
        $this->assertEquals('Created', $steps[0]['label']);
        $this->assertEquals('complete', $steps[0]['state']);
        $this->assertStringContainsString('Referred to', $steps[1]['label']);
        $this->assertEquals('complete', $steps[1]['state']);
        $this->assertStringContainsString('Received by', $steps[2]['label']);
        $this->assertEquals('complete', $steps[2]['state']);
        $this->assertEquals('For Compliance', $steps[3]['label']);
        $this->assertEquals('active', $steps[3]['state']);
        $this->assertEquals('Processing after compliance', $steps[4]['label']);
        $this->assertEquals('pending', $steps[4]['state']);
        $this->assertEquals('Completed', $steps[5]['label']);
        $this->assertEquals('pending', $steps[5]['state']);
    }

    public function test_agency_cards_have_no_internal_route_links(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id]);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        foreach ($data['trackingAgencies'] as $agencyCard) {
            $this->assertArrayNotHasKey('latestMilestonePath', $agencyCard);
        }
    }

    public function test_completion_percentage_key_exists(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertTrue(array_key_exists('completionPercentage', $data));
    }

    public function test_completion_percentage_zero_when_no_referrals(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['client_id' => null, 'status' => 'OPEN']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals(0, $data['completionPercentage']);
    }

    public function test_completion_percentage_all_pending_low(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->count(3)->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        // PENDING = 10 each, 3 referrals = 30/300 = 10%
        $this->assertEquals(10, $data['completionPercentage']);
    }

    public function test_completion_percentage_mixed_statuses(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);      // 10
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'FOR_COMPLIANCE']); // 33
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);    // 66
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'COMPLETED']);     // 100
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        // (10 + 33 + 66 + 100) / 400 = 209/400 = 52%
        $this->assertEquals(52, $data['completionPercentage']);
    }

    public function test_completion_percentage_excludes_rejected_referrals(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->count(2)->create(['case_id' => $case->id, 'status' => 'COMPLETED']);
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'REJECTED']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        // Rejection is an outcome, not progress: (100 + 100 + 0) / 300 = 67%
        $this->assertEquals(67, $data['completionPercentage']);
        $this->assertEquals(1, $data['rejectedCount']);
    }

    public function test_all_rejected_case_reports_zero_progress(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->count(3)->create(['case_id' => $case->id, 'status' => 'REJECTED']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals(0, $data['completionPercentage']);
        $this->assertEquals(3, $data['rejectedCount']);
    }

    public function test_timeline_uses_one_event_query_and_never_touches_audit_logs(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        // Several non-pending referrals — the old implementation issued one
        // audit-log query per non-pending referral.
        Referral::factory()->count(3)->create(['case_id' => $case->id, 'status' => 'PROCESSING']);
        $this->loadRelations($case);

        DB::enableQueryLog();
        $service->buildTrackingData($case);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

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
