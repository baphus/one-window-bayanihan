<?php

namespace Tests\Unit;

use Illuminate\Mail\Markdown;
use Tests\TestCase;

class MailRenderTest extends TestCase
{
    public function test_can_render_mail(): void
    {
        $markdown = app(Markdown::class);

        // Check paths
        $htmlPaths = $markdown->htmlComponentPaths();
        $this->assertNotEmpty($htmlPaths, 'htmlComponentPaths should not be empty');

        $view = app('view');
        $view->replaceNamespace('mail', $htmlPaths);

        $this->assertTrue($view->exists('mail::status-badge'), 'mail::status-badge should exist');
        $this->assertTrue($view->exists('mail::message'), 'mail::message should exist');
        $this->assertTrue($view->exists('mail::timeline'), 'mail::timeline should exist');
        $this->assertTrue($view->exists('mail::action-card'), 'mail::action-card should exist');
        $this->assertTrue($view->exists('mail::security-notice'), 'mail::security-notice should exist');
        $this->assertTrue($view->exists('mail::contact-footer'), 'mail::contact-footer should exist');

        // Now test actual rendering
        $case = new \stdClass;
        $case->status = 'OPEN';
        $case->case_number = 'TEST-001';
        $case->updated_at = now();
        $case->first_name = 'Test';
        $case->tracker_number = 'TRACK-001';
        $case->caseEvents = [];
        $case->category = null;
        $case->categories = collect();
        $case->referrals = collect();

        $html = $markdown->render('emails.client-update', [
            'case' => $case,
            'message' => 'Test',
            'updatedBy' => 'system',
        ]);

        $this->assertStringContainsString('status-badge', $html, 'Rendered HTML should contain status-badge');
        $this->assertStringContainsString('Next Steps', $html);
        $this->assertStringContainsString('You will receive another notification when there is further progress.', $html);

        $case->status = 'REJECTED';
        $message = 'Your assistance request was not approved. Reason: Incomplete employment history.';
        $html = $markdown->render('emails.client-update', [
            'case' => $case,
            'message' => $message,
            'updatedBy' => 'system',
        ]);

        $this->assertStringContainsString($message, $html);
        $this->assertStringContainsString('Track Your Request', $html);
        $this->assertStringNotContainsString('Next Steps', $html);
        $this->assertStringNotContainsString('No action is required on your part', $html);
        $this->assertStringNotContainsString('You will receive another notification', $html);
    }
}
