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

    public function test_agency_steps_has_four_steps(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);

        $this->assertCount(4, $data['trackingAgencies'][0]['steps']);
    }

    public function test_agency_steps_for_compliance_is_active(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'FOR_COMPLIANCE']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $steps = $data['trackingAgencies'][0]['steps'];

        $this->assertEquals('complete', $steps[0]['state']); // Received
        $this->assertEquals('complete', $steps[1]['state']); // Processing
        $this->assertEquals('active', $steps[2]['state']); // Compliance
        $this->assertEquals('pending', $steps[3]['state']); // Completed
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
