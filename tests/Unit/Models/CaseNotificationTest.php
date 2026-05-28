<?php

namespace Tests\Unit\Models;

use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_be_created_with_fillable_fields(): void
    {
        $case = $this->makeCaseFile();

        $notification = CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => 'client@example.com',
            'type' => 'case_updated',
            'title' => 'Case Updated',
            'message' => 'Your case has been updated.',
            'data' => ['case_id' => $case->id, 'old_status' => 'OPEN', 'new_status' => 'CLOSED'],
            'related_url' => '/cases/'.$case->id,
            'read_at' => null,
        ]);

        $this->assertDatabaseHas('case_notifications', [
            'id' => $notification->id,
            'case_id' => $case->id,
            'client_email' => 'client@example.com',
            'type' => 'case_updated',
            'title' => 'Case Updated',
            'message' => 'Your case has been updated.',
            'related_url' => '/cases/'.$case->id,
        ]);
    }

    public function test_it_belongs_to_case_file(): void
    {
        $case = $this->makeCaseFile();
        $notification = CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => 'client@example.com',
            'type' => 'milestone_added',
            'title' => 'Milestone Added',
            'message' => 'A new milestone was added.',
        ]);

        $this->assertInstanceOf(CaseFile::class, $notification->caseFile);
        $this->assertTrue($notification->caseFile->is($case));
    }

    public function test_it_casts_data_and_read_at(): void
    {
        $case = $this->makeCaseFile();
        $readAt = '2026-05-28 12:34:56';

        $notification = CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => 'client@example.com',
            'type' => 'referral_created',
            'title' => 'Referral Created',
            'message' => 'A referral was created.',
            'data' => ['referral_id' => 'ref-123'],
            'read_at' => $readAt,
        ]);

        $this->assertIsArray($notification->data);
        $this->assertSame('ref-123', $notification->data['referral_id']);
        $this->assertInstanceOf(Carbon::class, $notification->read_at);
        $this->assertSame($readAt, $notification->read_at->format('Y-m-d H:i:s'));
    }

    private function makeCaseFile(): CaseFile
    {
        $user = User::create([
            'name' => 'Case Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('password'),
            'role' => 'CASE_MANAGER',
        ]);

        return CaseFile::create([
            'case_number' => 'CASE-0001',
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-ABC1234',
            'summary' => 'Test case',
            'status' => 'OPEN',
            'user_id' => $user->id,
        ]);
    }
}
