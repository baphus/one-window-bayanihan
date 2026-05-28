<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CaseServiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private CaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CaseService::class);
    }

    public function test_updating_case_dispatches_notification_to_case_manager(): void
    {
        Notification::fake();

        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);

        $this->service->updateCase($case->id, [
            'client_type' => 'OFW',
            'vulnerability_indicator' => 'Low',
            'summary' => 'Updated summary',
        ], $caseManager->id);

        Notification::assertSentTo($caseManager, CaseUpdated::class);
    }

    public function test_updating_case_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);

        $this->service->updateCase($case->id, [
            'client_type' => 'OFW',
            'vulnerability_indicator' => 'Low',
            'summary' => 'Updated summary',
        ], $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'case_updated',
        ]);
    }

    public function test_updating_case_without_changes_does_not_notify(): void
    {
        Notification::fake();

        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);

        // Update with same values
        $this->service->updateCase($case->id, [
            'client_type' => 'OFW',
            'vulnerability_indicator' => 'Low',
            'summary' => 'Test summary',
        ], $caseManager->id);

        Notification::assertNothingSent();
    }

    public function test_toggling_case_status_dispatches_notification(): void
    {
        Notification::fake();

        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);

        $this->service->toggleCaseStatus($case->id, $caseManager->id);

        Notification::assertSentTo($caseManager, CaseStatusUpdated::class);
    }

    public function test_toggling_case_status_creates_ofw_notification(): void
    {
        $caseManager = $this->createUser('CASE_MANAGER');
        $case = $this->createCase($caseManager);

        $this->service->toggleCaseStatus($case->id, $caseManager->id);

        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'case_status_updated',
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
            'client_type' => 'OFW',
            'vulnerability_indicator' => 'Low',
            'summary' => 'Test summary',
            'status' => 'OPEN',
        ]);

        Client::factory()->create([
            'case_id' => $case->id,
            'email' => 'ofw@example.com',
        ]);

        return $case;
    }
}
