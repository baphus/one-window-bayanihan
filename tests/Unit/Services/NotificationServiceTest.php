<?php

namespace Tests\Unit\Services;

use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    public function test_notify_users_sends_notification(): void
    {
        NotificationFacade::fake();

        $user = User::factory()->create();
        $notification = $this->makeNotification();

        $this->service->notifyUsers([$user], $notification);

        NotificationFacade::assertSentTo($user, $notification::class);
    }

    public function test_notify_ofw_creates_case_notification(): void
    {
        $case = $this->makeCaseFile();
        $email = 'client@example.com';

        $result = $this->service->notifyOfw(
            $case,
            $email,
            'milestone_added',
            'New Milestone',
            'A new milestone was added.',
            ['milestone_id' => 'milestone-1'],
            '/tracking/'.$case->id,
        );

        $this->assertNotNull($result);
        $this->assertDatabaseHas('case_notifications', [
            'id' => $result->id,
            'case_id' => $case->id,
            'client_email' => $email,
            'type' => 'milestone_added',
            'title' => 'New Milestone',
        ]);
        $this->assertSame('milestone-1', $result->data['milestone_id']);
        $this->assertNull($result->read_at);
    }

    public function test_notify_ofw_skips_when_email_empty(): void
    {
        $case = $this->makeCaseFile();

        $result = $this->service->notifyOfw($case, '', 'test', 'Test', 'Test message');

        $this->assertNull($result);
    }

    public function test_notify_all_dispatches_both(): void
    {
        NotificationFacade::fake();

        $case = $this->makeCaseFile();
        $user = User::factory()->create();
        $email = 'client@example.com';
        $notification = $this->makeNotification();

        $this->service->notifyAll(
            $case,
            [$user],
            $email,
            $notification,
            'referral_created',
            'Referral Created',
            'A referral has been created.',
        );

        NotificationFacade::assertSentTo($user, $notification::class);

        $this->assertDatabaseHas('case_notifications', [
            'case_id' => $case->id,
            'client_email' => $email,
            'type' => 'referral_created',
        ]);
    }

    public function test_mark_as_read_for_user(): void
    {
        $user = User::factory()->create();
        $note = $user->notifications()->create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'type' => 'Dummy',
            'data' => '{}',
        ]);

        $result = $this->service->markAsRead($note->id, 'user');

        $this->assertTrue($result);
        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_mark_as_read_for_ofw(): void
    {
        $case = $this->makeCaseFile();
        $cn = CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => 'client@example.com',
            'type' => 'test',
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $result = $this->service->markAsRead($cn->id, 'ofw');

        $this->assertTrue($result);
        $this->assertNotNull($cn->fresh()->read_at);
    }

    public function test_mark_as_read_returns_false_for_missing(): void
    {
        $result = $this->service->markAsRead('00000000-0000-0000-0000-000000000000', 'ofw');

        $this->assertFalse($result);
    }

    public function test_mark_all_as_read_for_user(): void
    {
        $user = User::factory()->create();
        $user->notifications()->createMany([
            ['id' => '00000000-0000-0000-0000-000000000011', 'type' => 'Dummy', 'data' => '{}'],
            ['id' => '00000000-0000-0000-0000-000000000012', 'type' => 'Dummy', 'data' => '{}'],
            ['id' => '00000000-0000-0000-0000-000000000013', 'type' => 'Dummy', 'data' => '{}'],
        ]);

        $count = $this->service->markAllAsRead($user, 'user');

        $this->assertSame(3, $count);
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_mark_all_as_read_for_ofw(): void
    {
        $case = $this->makeCaseFile();
        $email = 'client@example.com';

        CaseNotification::create([
            'case_id' => $case->id, 'client_email' => $email,
            'type' => 'a', 'title' => 'A', 'message' => 'Notif A',
        ]);
        CaseNotification::create([
            'case_id' => $case->id, 'client_email' => $email,
            'type' => 'b', 'title' => 'B', 'message' => 'Notif B',
        ]);

        $count = $this->service->markAllAsRead($email, 'ofw');

        $this->assertSame(2, $count);
        $this->assertSame(0, $this->service->getUnreadCount($email, 'ofw'));
    }

    public function test_get_unread_count_for_user(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => '00000000-0000-0000-0000-000000000021',
            'type' => 'Dummy',
            'data' => '{}',
        ]);

        $count = $this->service->getUnreadCount($user, 'user');

        $this->assertSame(1, $count);
    }

    public function test_get_unread_count_for_ofw(): void
    {
        $case = $this->makeCaseFile();
        $email = 'client@example.com';

        CaseNotification::create([
            'case_id' => $case->id, 'client_email' => $email,
            'type' => 'test', 'title' => 'Test', 'message' => 'Test',
        ]);

        $count = $this->service->getUnreadCount($email, 'ofw');

        $this->assertSame(1, $count);
    }

    public function test_get_notifications_for_user_returns_paginated(): void
    {
        $user = User::factory()->create();
        $user->notifications()->createMany([
            ['id' => '00000000-0000-0000-0000-000000000031', 'type' => 'Dummy', 'data' => '{}'],
            ['id' => '00000000-0000-0000-0000-000000000032', 'type' => 'Dummy', 'data' => '{}'],
        ]);

        $result = $this->service->getNotifications($user, 'user', 10);

        $this->assertSame(2, $result->total());
    }

    public function test_get_notifications_for_ofw_returns_paginated(): void
    {
        $case = $this->makeCaseFile();
        $email = 'client@example.com';

        CaseNotification::create([
            'case_id' => $case->id, 'client_email' => $email,
            'type' => 'a', 'title' => 'A', 'message' => 'A',
        ]);
        CaseNotification::create([
            'case_id' => $case->id, 'client_email' => $email,
            'type' => 'b', 'title' => 'B', 'message' => 'B',
        ]);

        $result = $this->service->getNotifications($email, 'ofw', 10);

        $this->assertSame(2, $result->total());
    }

    private function makeCaseFile(): CaseFile
    {
        $user = User::create([
            'name' => 'Case Manager',
            'email' => 'manager_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'CASE_MANAGER',
        ]);

        return CaseFile::create([
            'case_number' => 'CASE-'.uniqid(),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.uniqid(),
            'summary' => 'Test case for notification service',
            'status' => 'OPEN',
            'user_id' => $user->id,
        ]);
    }

    private function makeNotification(): Notification
    {
        return new class extends Notification
        {
            public function via($notifiable): array
            {
                return ['database'];
            }

            public function toDatabase($notifiable): array
            {
                return ['message' => 'test'];
            }
        };
    }
}
