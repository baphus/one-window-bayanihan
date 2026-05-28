<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReferralServiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    public function test_creating_referral_dispatches_notification_to_agency_users(): void
    {
        Notification::fake();

        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => 'AGENCY',
            'is_active' => true,
        ]);

        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Test Service',
            'notes' => 'Test notes',
        ], $caseManager->id);

        Notification::assertSentTo($agencyUser, ReferralCreated::class);
    }

    public function test_creating_referral_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Test Service',
        ], $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'referral_created',
        ]);
    }

    public function test_updating_referral_status_dispatches_notification_to_case_manager(): void
    {
        Notification::fake();

        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Test',
        ], $caseManager->id);

        Notification::fake();

        $this->service->updateStatus($referral->id, 'APPROVED', null, null, $caseManager->id);

        Notification::assertSentTo($caseManager, ReferralStatusChanged::class);
    }

    public function test_updating_referral_status_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Test',
        ], $caseManager->id);

        $this->service->updateStatus($referral->id, 'APPROVED', null, null, $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'referral_status_changed',
        ]);
    }

    public function test_adding_milestone_dispatches_notification(): void
    {
        Notification::fake();

        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => 'AGENCY',
            'is_active' => true,
        ]);

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Test',
        ], $caseManager->id);

        Notification::fake();

        $this->service->addMilestone($referral->id, 'First Milestone', 'Description', $caseManager->id);

        Notification::assertSentTo($caseManager, MilestoneAdded::class);
    }

    public function test_adding_milestone_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();

        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Test',
        ], $caseManager->id);

        $this->service->addMilestone($referral->id, 'First Milestone', 'Description', $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'milestone_added',
        ]);
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
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'status' => 'OPEN',
        ]);

        Client::factory()->create([
            'case_id' => $case->id,
            'email' => 'ofw@example.com',
        ]);

        return $case;
    }
}
