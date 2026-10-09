<?php

namespace Tests\Unit\Notifications;

use App\Models\CaseFile;
use App\Notifications\CaseStatusUpdated;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class CaseStatusUpdatedTest extends TestCase
{
    public function test_via_returns_database_and_mail_channels(): void
    {
        $case = $this->makeCaseFile();
        $notification = new CaseStatusUpdated($case, 'open', 'closed');

        $this->assertSame(['database', 'mail'], $notification->via((object) []));
    }

    public function test_to_database_returns_expected_payload(): void
    {
        $case = $this->makeCaseFile();
        $notification = new CaseStatusUpdated($case, 'open', 'closed');

        $this->assertSame([
            'type' => 'case_status_updated',
            'title' => 'Case CASE-2026-0001 is now Closed',
            'message' => 'Case CASE-2026-0001 status changed from Open to Closed',
            'case_number' => $case->case_number,
            'actor_name' => null,
            'url' => route('cases.show', $case->id),
            'case_id' => $case->id,
            'old_status' => 'open',
            'new_status' => 'closed',
        ], $notification->toDatabase((object) []));
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $case = $this->makeCaseFile();
        $notification = new CaseStatusUpdated($case, 'open', 'closed');

        $this->assertInstanceOf(MailMessage::class, $notification->toMail((object) []));
    }

    private function makeCaseFile(): CaseFile
    {
        $case = new CaseFile;
        $case->forceFill([
            'id' => '11111111-1111-1111-1111-111111111111',
            'case_number' => 'CASE-2026-0001',
        ]);

        return $case;
    }
}
