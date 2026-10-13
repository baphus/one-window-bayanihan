<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\PeerReferralCreated;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReferralServiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = app(ReferralService::class);
    }

    public function test_creating_referral_dispatches_notification_to_agency_users(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'notes' => 'Test notes',
        ], $caseManager->id);

        Notification::assertSentTo($agencyUser, ReferralCreated::class);
    }

    public function test_creating_referral_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'referral_created',
        ]);
    }

    public function test_updating_referral_status_dispatches_notification_to_case_manager(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();

        $this->service->updateStatus($referral->id, 'PROCESSING', null, null, $caseManager->id);

        Notification::assertSentTo($caseManager, ReferralStatusChanged::class);
    }

    public function test_updating_referral_status_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        $this->service->updateStatus($referral->id, 'PROCESSING', null, null, $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'referral_status_changed',
        ]);
    }

    public function test_duplicate_status_update_does_not_resend_notifications(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();

        $this->service->updateStatus($referral->id, 'PROCESSING', null, null, $caseManager->id);
        // Duplicate request for the same status must be an idempotent no-op.
        $this->service->updateStatus($referral->id, 'PROCESSING', null, null, $caseManager->id);

        Notification::assertSentTimes(ReferralStatusChanged::class, 1);
    }

    public function test_adding_milestone_dispatches_notification(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();

        $this->service->addMilestone($referral->id, 'First Milestone', 'Description', $caseManager->id);

        Notification::assertSentTo($caseManager, MilestoneAdded::class);
    }

    public function test_adding_milestone_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        $this->service->addMilestone($referral->id, 'First Milestone', 'Description', $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'milestone_added',
        ]);
    }

    public function test_creating_referral_notifies_peer_agencies_on_same_case(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);

        // First agency already has an active referral on this case
        $agencyA = Agency::factory()->create();
        $agencyAUser = User::factory()->create([
            'agcy_id' => $agencyA->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agencyA->id,
        ], $caseManager->id);

        Notification::fake();

        // Second referral on the same case → agencyA users should get PeerReferralCreated
        $agencyB = Agency::factory()->create();
        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agencyB->id,
        ], $caseManager->id);

        Notification::assertSentTo($agencyAUser, PeerReferralCreated::class);
    }

    public function test_peer_notification_not_sent_to_receiving_agency(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);

        $agencyA = Agency::factory()->create();
        $agencyAUser = User::factory()->create([
            'agcy_id' => $agencyA->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agencyA->id,
        ], $caseManager->id);

        Notification::fake();

        // New referral to agencyA on same case — should NOT get PeerReferralCreated (already gets ReferralCreated)
        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agencyA->id,
        ], $caseManager->id);

        Notification::assertNotSentTo($agencyAUser, PeerReferralCreated::class);
    }

    public function test_peer_notification_not_sent_when_no_other_active_referrals(): void
    {
        Notification::fake();

        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);

        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        // First referral — no other referrals exist, so no peer notification
        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::assertNotSentTo($agencyUser, PeerReferralCreated::class);
    }

    public function test_referral_can_be_rejected_after_processing_begins(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        $this->service->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);
        $this->service->updateStatus($referral->id, 'REJECTED', 'REJECT', 'Agency cannot complete this service.', $caseManager->id, 'OUTSIDE_MANDATE');

        $fresh = $referral->fresh();
        $this->assertSame('REJECTED', $fresh->status);
        $this->assertSame('REJECT', $fresh->decision);
        $this->assertSame('OUTSIDE_MANDATE', $fresh->rejection_reason);
    }

    public function test_referral_can_be_rejected_from_for_compliance(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        $this->service->updateStatus($referral->id, 'FOR_COMPLIANCE', 'ACCEPT', null, $caseManager->id);
        $this->service->updateStatus($referral->id, 'REJECTED', 'REJECT', 'Client did not respond.', $caseManager->id, 'CLIENT_WITHDREW');

        $this->assertSame('REJECTED', $referral->fresh()->status);
        $this->assertSame('CLIENT_WITHDREW', $referral->fresh()->rejection_reason);
    }

    public function test_completed_referral_cannot_be_rejected(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        $this->service->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);
        $this->service->updateStatus($referral->id, 'COMPLETED', null, null, $caseManager->id);

        try {
            $this->service->updateStatus($referral->id, 'REJECTED', 'REJECT', 'Too late.', $caseManager->id);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot change referral status', $e->getMessage());
        }

        $this->assertSame('COMPLETED', $referral->fresh()->status);
    }

    public function test_rejecting_without_a_reason_is_refused(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        try {
            $this->service->updateStatus($referral->id, 'REJECTED', 'REJECT', 'No reason given.', $caseManager->id);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('rejection reason', $e->getMessage());
        }

        $this->assertSame('PENDING', $referral->fresh()->status);
    }

    public function test_rejecting_with_other_reason_requires_a_detailed_comment(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        foreach ([null, 'Too short'] as $comment) {
            try {
                $this->service->updateStatus($referral->id, 'REJECTED', 'REJECT', $comment, $caseManager->id, 'OTHER');
                $this->fail('Expected InvalidArgumentException was not thrown.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('at least 10 characters', $e->getMessage());
            }
        }

        $updated = $this->service->updateStatus($referral->id, 'REJECTED', 'REJECT', 'Duplicate of an earlier referral sent in error.', $caseManager->id, 'OTHER');

        $this->assertSame('REJECTED', $updated->status);
        $this->assertSame('OTHER', $updated->rejection_reason);
    }

    public function test_rejection_reason_is_cleared_when_referral_moves_out_of_rejected(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        // Stale reason on a non-rejected referral (e.g. legacy data): moving
        // forward must clear it rather than carry it along.
        $referral = Referral::factory()->processing()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'rejection_reason' => 'NO_SERVICE_CAPACITY',
        ]);

        $updated = $this->service->updateStatus($referral->id, 'COMPLETED', null, null, $caseManager->id);

        $this->assertSame('COMPLETED', $updated->status);
        $this->assertNull($updated->rejection_reason);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => 'Test '.$role,
            'email' => strtolower($role).'@example.com',
        ]);
    }

    private function createCase(User $user): CaseFile
    {
        $client = Client::factory()->create([
            'email' => 'ofw@example.com',
        ]);

        return CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
    }
}
