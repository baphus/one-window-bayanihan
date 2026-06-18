<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $this->assertTrue(array_key_exists('caseTimeline', $data));
    }

    public function test_milestone_timeline_excludes_raw_audit_log_entries(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();

        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'case',
            'entity_id' => $case->id,
            'description' => 'set draft_client_data to {"foo":"bar"}; set category_id to a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $this->loadRelations($case);
        $data = $service->buildTrackingData($case);

        foreach ($data['milestoneTimeline'] as $item) {
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['title'] ?? '');
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title'] ?? '');
        }
    }

    public function test_milestone_timeline_includes_case_opened_event(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
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
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
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
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $msItems = array_values(array_filter(
            $data['milestoneTimeline'],
            fn ($item) => $item['type'] === 'milestone'
        ));

        $this->assertNotEmpty($msItems);
        $this->assertEquals($milestone->title, $msItems[0]['title']);
    }

    public function test_milestone_timeline_sorted_chronologically(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['created_at' => now()->subDays(5)]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'created_at' => now()->subDays(3),
        ]);
        Milestone::factory()->create([
            'refr_id' => $referral->id,
            'created_at' => now()->subDay(),
        ]);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $dates = array_column($data['milestoneTimeline'], 'date');

        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates);
    }

    public function test_case_status_mapping_open_returns_in_progress(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals('IN_PROGRESS', $data['trackedCase']['status']);
    }

    public function test_case_status_mapping_draft_returns_being_prepared(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['status' => 'DRAFT']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertEquals('BEING_PREPARED', $data['trackedCase']['status']);
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
}
