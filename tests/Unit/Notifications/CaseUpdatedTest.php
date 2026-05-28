<?php

namespace Tests\Unit\Notifications;

use App\Models\CaseFile;
use App\Notifications\CaseUpdated;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class CaseUpdatedTest extends TestCase
{
    public function test_it_returns_expected_channels(): void
    {
        $notification = new CaseUpdated(
            $this->makeCase(),
            'Test User',
            $this->makeChanges(),
        );

        $this->assertSame(['database', 'mail'], $notification->via((object) []));
    }

    public function test_it_returns_expected_database_payload(): void
    {
        $case = $this->makeCase();
        $changes = $this->makeChanges();

        $notification = new CaseUpdated($case, 'Test User', $changes);
        $data = $notification->toDatabase((object) []);

        $this->assertSame('case_updated', $data['type']);
        $this->assertSame($case->id, $data['case_id']);
        $this->assertSame($case->case_number, $data['case_number']);
        $this->assertSame('Test User', $data['updated_by']);
        $this->assertSame($changes, $data['changes']);
        $this->assertSame('Case updated by Test User', $data['message']);
        $this->assertSame(route('cases.show', $case->id), $data['url']);
    }

    public function test_it_returns_mail_message(): void
    {
        $notification = new CaseUpdated(
            $this->makeCase(),
            'Test User',
            $this->makeChanges(),
        );

        $mail = $notification->toMail((object) []);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    private function makeCase(): CaseFile
    {
        $case = new CaseFile([
            'case_number' => 'CASE-2026-001',
            'client_type' => 'Individual',
            'vulnerability_indicator' => 'None',
            'summary' => 'Initial summary',
        ]);

        $case->id = 'case-123';

        return $case;
    }

    private function makeChanges(): array
    {
        return [
            'summary' => [
                'old' => 'Initial summary',
                'new' => 'Updated summary',
            ],
            'client_type' => [
                'old' => 'Individual',
                'new' => 'Organization',
            ],
            'vulnerability_indicator' => [
                'old' => 'None',
                'new' => 'High',
            ],
        ];
    }
}
