<?php

namespace Tests\Feature;

use App\DTOs\FileStoreResult;
use App\Enums\UserRole;
use App\Mail\ClientUpdateMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use App\Services\ReferralService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/**
 * The queue connections keep `after_commit => false`, so a queue push made
 * before COMMIT is visible to the worker straight away. If the surrounding
 * transaction then rolls back, the client has been mailed about a referral,
 * status change or milestone that never persisted.
 *
 * These tests roll the outer transaction back on purpose and assert nothing was
 * dispatched, while the happy-path tests elsewhere
 * (ReferralServiceNotificationTest) keep proving the notifications still fire.
 */
class NotificationDispatchAfterCommitTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        $this->service = app(ReferralService::class);
    }

    public function test_rolled_back_referral_creation_dispatches_no_notifications_or_client_email(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        User::factory()->create([
            'agcy_id' => $agency->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        $this->rollBackAfter(fn () => $this->service->createReferralWithDocuments(
            [
                'case_id' => $case->id,
                'agcy_id' => $agency->id,
                'notes' => 'Rolled back',
            ],
            $caseManager->id,
            [UploadedFile::fake()->create('document.pdf', 1)],
            $this->fakeStorage(),
        ));

        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_rolled_back_referral_status_change_dispatches_no_notifications_or_client_email(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();
        Mail::fake();

        $this->rollBackAfter(fn () => $this->service->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id));

        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_rolled_back_milestone_dispatches_no_notifications_or_client_email(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();
        Mail::fake();

        $this->rollBackAfter(fn () => $this->service->addMilestone($referral->id, 'Rolled back milestone', 'Description', $caseManager->id));

        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_committed_milestone_still_dispatches_notifications_and_client_email(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();
        Mail::fake();

        $this->service->addMilestone($referral->id, 'Committed milestone', 'Description', $caseManager->id);

        // Deferring to after commit must not swallow the notification.
        Notification::assertSentTo($caseManager, MilestoneAdded::class);
        Mail::assertQueued(ClientUpdateMail::class);
        $this->assertDatabaseHas('case_notifications', [
            'client_email' => 'ofw@example.com',
            'type' => 'milestone_added',
        ]);
    }

    public function test_committed_referral_creation_still_dispatches_notifications(): void
    {
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
        ], $caseManager->id);

        Notification::assertSentTo($agencyUser, ReferralCreated::class);
    }

    public function test_committed_referral_status_change_still_dispatches_notifications(): void
    {
        $caseManager = $this->createUser(UserRole::CASE_MANAGER->value);
        $case = $this->createCase($caseManager);
        $agency = Agency::factory()->create();
        $referral = $this->service->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $caseManager->id);

        Notification::fake();

        $this->service->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);

        Notification::assertSentTo($caseManager, ReferralStatusChanged::class);
    }

    /**
     * Run $work inside an outer transaction and roll it back, mirroring
     * createReferralWithDocuments committing a referral and then failing while
     * inserting its case-document rows.
     */
    private function rollBackAfter(callable $work): void
    {
        DB::beginTransaction();

        try {
            $work();
            DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    private function fakeStorage(): StorageService
    {
        $storage = Mockery::mock(StorageService::class);
        $storage->shouldReceive('store')
            ->andReturnUsing(fn (UploadedFile $file, string $directory) => new FileStoreResult(
                path: $directory.'/stored.pdf',
                originalName: $file->getClientOriginalName(),
                storedName: 'stored.pdf',
                type: 'application/pdf',
                size: 123,
            ));
        $storage->shouldReceive('delete')->andReturnTrue();

        return $storage;
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
