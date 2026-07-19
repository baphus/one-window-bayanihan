<?php

namespace Tests\Feature\TrackingService;

use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\User;
use App\Services\CaseService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class CaseEventRecordingTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Mail::fake();
    }

    private function makeCase(): array
    {
        $result = $this->createCompleteCase();

        return [$result['case'], $result['user']];
    }

    public function test_create_referral_records_referral_sent_event(): void
    {
        [$case, $user] = $this->makeCase();
        $agency = Agency::factory()->create();

        $referral = app(ReferralService::class)->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'services' => ['Legal Assistance'],
        ], $user->id);

        $event = CaseEvent::where('referral_id', $referral->id)
            ->where('type', CaseEvent::TYPE_REFERRAL_SENT)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($case->id, $event->case_id);
        $this->assertStringContainsString($agency->name, $event->title);
        $this->assertStringContainsString('Legal Assistance', $event->description);
    }

    public function test_every_intermediate_status_transition_is_recorded(): void
    {
        $result = $this->createCompleteCase(1);
        $referral = $result['referrals']->first();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $referral->agcy_id]);
        $service = app(ReferralService::class);

        foreach (['PROCESSING', 'COMPLETED'] as $status) {
            $service->updateStatus($referral->id, $status, null, null, $agencyUser->id);
        }

        $events = CaseEvent::where('referral_id', $referral->id)
            ->where('type', CaseEvent::TYPE_REFERRAL_STATUS_CHANGED)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $events);
        $this->assertEquals(
            [
                ['from' => 'PENDING', 'to' => 'PROCESSING'],
                ['from' => 'PROCESSING', 'to' => 'COMPLETED'],
            ],
            $events->pluck('meta')->toArray()
        );
    }

    public function test_same_status_update_records_no_event(): void
    {
        $result = $this->createCompleteCase(1);
        $referral = $result['referrals']->first();

        app(ReferralService::class)->updateStatus($referral->id, 'PENDING', null, null, $result['user']->id);

        $this->assertEquals(0, CaseEvent::where('referral_id', $referral->id)
            ->where('type', CaseEvent::TYPE_REFERRAL_STATUS_CHANGED)
            ->count());
    }

    public function test_add_milestone_records_event(): void
    {
        $result = $this->createCompleteCase(1);
        $referral = $result['referrals']->first();

        app(ReferralService::class)->addMilestone(
            $referral->id, 'Documents verified', 'All submitted documents check out.', $result['user']->id
        );

        $event = CaseEvent::where('referral_id', $referral->id)
            ->where('type', CaseEvent::TYPE_MILESTONE_ADDED)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('Documents verified', $event->title);
        $this->assertEquals('All submitted documents check out.', $event->description);
    }

    public function test_publishing_a_draft_records_case_opened(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sex' => 'FEMALE']);
        ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
            'street' => '123 Test St',
        ]);
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
            'client_type' => 'OFW',
            'category_id' => $category->id,
        ]);
        $case->categories()->attach($category->id);

        app(CaseService::class)->publishDraft($case->id, $user->id);

        $event = CaseEvent::where('case_id', $case->id)
            ->where('type', CaseEvent::TYPE_CASE_OPENED)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('Your case has been opened', $event->title);
        $this->assertEquals('case_manager', $event->actor_type);
    }

    public function test_close_and_reopen_case_record_events(): void
    {
        $result = $this->createCompleteCase(0);
        $case = $result['case'];
        $service = app(CaseService::class);

        $service->toggleCaseStatus($case->id, $result['user']->id);
        $service->toggleCaseStatus($case->id, $result['user']->id);

        $types = CaseEvent::where('case_id', $case->id)
            ->whereIn('type', [CaseEvent::TYPE_CASE_CLOSED, CaseEvent::TYPE_CASE_REOPENED])
            ->orderBy('created_at')
            ->pluck('type')
            ->toArray();

        $this->assertEquals([CaseEvent::TYPE_CASE_CLOSED, CaseEvent::TYPE_CASE_REOPENED], $types);
    }

    public function test_closing_case_via_update_case_records_event(): void
    {
        $result = $this->createCompleteCase(0);
        $case = $result['case'];

        app(CaseService::class)->updateCase($case->id, [
            'status' => 'CLOSED',
            'client_type' => $case->client_type,
        ], $result['user']->id);

        $this->assertNotNull(
            CaseEvent::where('case_id', $case->id)->where('type', CaseEvent::TYPE_CASE_CLOSED)->first(),
            'Closing a case through the edit endpoint must record case_closed'
        );

        // And reopening the same way records case_reopened
        app(CaseService::class)->updateCase($case->id, [
            'status' => 'OPEN',
            'client_type' => $case->client_type,
        ], $result['user']->id);

        $this->assertNotNull(
            CaseEvent::where('case_id', $case->id)->where('type', CaseEvent::TYPE_CASE_REOPENED)->first()
        );
    }

    public function test_case_update_notification_carries_no_staff_identity(): void
    {
        $result = $this->createCompleteCase(0);
        $case = $result['case'];
        $editor = User::factory()->create(['name' => 'Editha Stafferson Unique']);

        app(CaseService::class)->updateCase($case->id, [
            'status' => $case->status,
            'client_type' => $case->client_type,
            'summary' => 'Updated summary text for the client record.',
        ], $editor->id);

        $notification = CaseNotification::where('case_id', $case->id)
            ->where('type', 'case_updated')
            ->first();

        $this->assertNotNull($notification);
        $payload = json_encode([$notification->message, $notification->data]);
        $this->assertStringNotContainsString('Editha Stafferson Unique', $payload);
        $this->assertStringNotContainsString('changes', $payload);
    }

    public function test_rolled_back_transaction_persists_no_event(): void
    {
        $result = $this->createCompleteCase(1);
        $referral = $result['referrals']->first();

        try {
            DB::transaction(function () use ($referral, $result) {
                app(ReferralService::class)->updateStatus($referral->id, 'PROCESSING', null, null, $result['user']->id);
                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertEquals(0, CaseEvent::where('referral_id', $referral->id)->count());
    }

    public function test_events_carry_actor_type_but_never_staff_identity(): void
    {
        $result = $this->createCompleteCase(1);
        $referral = $result['referrals']->first();
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $referral->agcy_id,
            'name' => 'Juanito Dela Cruz Unique',
        ]);

        app(ReferralService::class)->updateStatus($referral->id, 'PROCESSING', null, null, $agencyUser->id);
        app(ReferralService::class)->addMilestone($referral->id, 'Initial review done', null, $result['user']->id);

        $events = CaseEvent::where('case_id', $result['case']->id)->get();
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertContains($event->actor_type, ['agency', 'case_manager', 'system']);
            $haystack = $event->title.' '.($event->description ?? '').' '.json_encode($event->meta);
            $this->assertStringNotContainsString('Juanito Dela Cruz Unique', $haystack);
            $this->assertStringNotContainsString($agencyUser->id, $haystack);
            $this->assertStringNotContainsString($result['user']->id, $haystack);
        }

        $statusEvent = $events->firstWhere('type', CaseEvent::TYPE_REFERRAL_STATUS_CHANGED);
        $this->assertEquals('agency', $statusEvent->actor_type);
        $milestoneEvent = $events->firstWhere('type', CaseEvent::TYPE_MILESTONE_ADDED);
        $this->assertEquals('case_manager', $milestoneEvent->actor_type);
    }
}
