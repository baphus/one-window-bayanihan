<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use App\Notifications\MilestoneAdded;
use App\Notifications\NewIntakeSubmission;
use App\Notifications\OverdueReferralNotification;
use App\Notifications\PeerReferralCreated;
use App\Notifications\ReferralClientRequestActivity;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use App\Notifications\SystemAlertNotification;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPayloadEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_staff_notification_payload_has_consistent_inbox_keys(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $caseManager->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        $payloads = [
            (new MilestoneAdded($milestone, $referral, 'Stu Torres'))->toDatabase($caseManager),
            (new OverdueReferralNotification($referral, 9, 'Stu Torres'))->toDatabase($caseManager),
            (new ReferralCreated($referral, 'Stu Torres'))->toDatabase($caseManager),
            (new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING', 'Stu Torres'))->toDatabase($caseManager),
            (new NewIntakeSubmission($case, 'Juan Dela Cruz'))->toDatabase($caseManager),
            (new CaseUpdated($case, 'Stu Torres', ['summary' => ['old' => 'a', 'new' => 'b']]))->toDatabase($caseManager),
            (new CaseStatusUpdated($case, 'OPEN', 'CLOSED', 'Stu Torres'))->toDatabase($caseManager),
            (new PeerReferralCreated($referral, $referral, 'Stu Torres'))->toDatabase($caseManager),
            (new ReferralClientRequestActivity('created', 'req-1', $referral->id, 'Passport copy', 'OPEN', $case->case_number, 'Stu Torres'))->toDatabase($caseManager),
            (new SystemAlertNotification('queue_failure', 'critical', 'Queue worker stopped.'))->toDatabase($caseManager),
        ];

        foreach ($payloads as $payload) {
            foreach (['type', 'title', 'message', 'case_number', 'actor_name', 'url'] as $key) {
                $this->assertArrayHasKey($key, $payload, 'Missing key: '.$key);
            }
            $this->assertNotEmpty($payload['title']);
            $this->assertNotEmpty($payload['message']);
        }
    }

    public function test_referral_status_change_notifies_owning_agency_with_actionable_payload(): void
    {
        Notification::fake();
        Mail::fake();

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => 'AGENCY',
            'is_active' => true,
        ]);

        $service = app(ReferralService::class);
        $referral = $service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();

        $service->updateStatus($referral->id, 'PROCESSING', null, null, $caseManager->id);

        Notification::assertSentTo($agencyUser, ReferralStatusChanged::class, function (ReferralStatusChanged $notification) use ($case): bool {
            $payload = $notification->toDatabase(new \stdClass);

            return $payload['case_number'] === $case->case_number
                && $payload['actor_name'] !== null
                && $payload['url'] === "/referrals/{$notification->referral->id}"
                && $payload['title'] !== '';
        });
    }

    public function test_ofw_notifications_use_plain_language_without_status_codes(): void
    {
        Mail::fake();

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $service = app(ReferralService::class);
        $referral = $service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);
        $service->updateStatus($referral->id, 'PROCESSING', null, null, $caseManager->id);
        $service->addMilestone($referral->id, 'Documents verified', null, $caseManager->id);

        $items = CaseNotification::where('case_id', $case->id)->get();
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertNotEmpty($item->title);
            $this->assertNotEmpty($item->message);
            foreach (['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'client_request_delivery'] as $jargon) {
                $this->assertStringNotContainsString($jargon, (string) $item->title);
                $this->assertStringNotContainsString($jargon, (string) $item->message);
            }
        }
    }

    public function test_client_request_activity_carries_verb_case_and_actor(): void
    {
        $payload = (new ReferralClientRequestActivity(
            'client_reply',
            'request-id',
            'referral-id',
            'Passport copy',
            'CLIENT_RESPONDED',
            'CASE-2026-001',
            'Stu Torres',
        ))->toDatabase(new \stdClass);

        $this->assertStringContainsString('Client responded', (string) $payload['title']);
        $this->assertStringContainsString('CASE-2026-001', (string) $payload['message']);
        $this->assertStringContainsString('Stu Torres', (string) $payload['message']);
        $this->assertSame('CASE-2026-001', $payload['case_number']);
        $this->assertSame('Stu Torres', $payload['actor_name']);
        $this->assertArrayNotHasKey('body', $payload);
        $this->assertArrayNotHasKey('token', $payload);
    }

    public function test_legacy_construction_without_actor_still_produces_readable_payload(): void
    {
        $referral = Referral::factory()->create();

        $payload = (new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING'))->toDatabase(new \stdClass);

        $this->assertNull($payload['actor_name']);
        $this->assertNotEmpty($payload['title']);
        $this->assertNotEmpty($payload['message']);
        $this->assertNotEmpty($payload['url']);
    }

    private function createCase(User $user): CaseFile
    {
        $client = Client::factory()->create(['email' => 'ofw@example.com']);

        return CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
    }
}
