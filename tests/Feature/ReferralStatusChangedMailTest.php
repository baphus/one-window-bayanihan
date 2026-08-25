<?php

namespace Tests\Feature;

use App\Mail\ClientUpdateMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\ReferralStatusChanged;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReferralStatusChangedMailTest extends TestCase
{
    use RefreshDatabase;

    // ── Rendering ────────────────────────────────────────────────

    public function test_rendered_status_transition_is_humanized_and_uses_an_arrow_not_an_html_entity(): void
    {
        $user = User::factory()->create(['email' => 'recipient@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PENDING', 'FOR_COMPLIANCE'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('For Compliance', $html);
        $this->assertStringContainsString('→', $html);
        $this->assertStringNotContainsString('&rarr;', $html);
        $this->assertStringNotContainsString('PENDING', $html);
        $this->assertStringNotContainsString('FOR_COMPLIANCE', $html);
    }

    public function test_processing_email_contains_acceptance_message(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('accepted and is now being processed', $html);
    }

    public function test_completed_email_contains_completion_message(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PROCESSING', 'COMPLETED'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('completed', $html);
        $this->assertStringContainsString('Review the outcome', $html);
    }

    public function test_rejected_email_contains_rejection_message(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PROCESSING', 'REJECTED'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('declined', $html);
    }

    public function test_rejected_email_includes_decision_comment(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
            'decision_comment' => 'Agency lacks capacity.',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PROCESSING', 'REJECTED'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('Agency lacks capacity.', $html);
    }

    public function test_email_includes_case_number(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString($referral->caseFile->case_number ?? 'N/A', $html);
    }

    public function test_email_includes_agency_name(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING'))
            ->toMail($user)
            ->render();

        $agencyName = $referral->agency?->name ?? 'The assigned agency';
        $this->assertStringContainsString($agencyName, $html);
    }

    // ── Channel selection (via) ──────────────────────────────────

    public function test_via_includes_mail_when_user_has_email_and_mailer_is_smtp(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);
        $referral = Referral::factory()->create();

        config(['mail.default' => 'smtp']);

        $notification = new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_via_excludes_mail_when_user_has_no_email(): void
    {
        $referral = Referral::factory()->create();

        config(['mail.default' => 'smtp']);

        // Mock a notifiable with no email (users.email is NOT NULL in DB).
        $notifiable = new class
        {
            public ?string $email = null;
        };

        $notification = new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $channels = $notification->via($notifiable);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_via_excludes_mail_when_mailer_is_log(): void
    {
        $referral = Referral::factory()->create();

        config(['mail.default' => 'log']);

        $notifiable = new class
        {
            public string $email = 'cm@example.com';
        };

        $notification = new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $channels = $notification->via($notifiable);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    // ── Queue routing ────────────────────────────────────────────

    public function test_via_queues_routes_mail_to_notifications_queue(): void
    {
        $referral = Referral::factory()->create();
        $notification = new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $queues = $notification->viaQueues();

        $this->assertSame('default', $queues['database']);
        $this->assertSame('notifications', $queues['mail']);
    }

    // ── Database notification payload ────────────────────────────

    public function test_to_database_includes_status_fields(): void
    {
        $user = User::factory()->create();
        $referral = Referral::factory()->create();

        $notification = new ReferralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $data = $notification->toDatabase($user);

        $this->assertSame('referral_status_changed', $data['type']);
        $this->assertSame('PENDING', $data['old_status']);
        $this->assertSame('PROCESSING', $data['new_status']);
        $this->assertSame($referral->id, $data['referral_id']);
        $this->assertSame($referral->case_id, $data['case_id']);
    }

    public function test_to_database_humanizes_status_in_message(): void
    {
        $user = User::factory()->create();
        $referral = Referral::factory()->create();

        $notification = new ReferralStatusChanged($referral, 'PENDING', 'FOR_COMPLIANCE');
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('Pending', $data['message']);
        $this->assertStringContainsString('For Compliance', $data['message']);
    }

    // ── Integration: acceptance triggers mail to case manager ────

    public function test_accepting_referral_sends_mail_to_case_manager(): void
    {
        Mail::fake();

        $caseManager = User::factory()->create([
            'role' => 'CASE_MANAGER',
            'email' => 'cm@example.com',
        ]);
        $client = Client::factory()->create(['email' => 'ofw@example.com']);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        $agency = Agency::factory()->create();

        $referralService = app(ReferralService::class);
        $referral = $referralService->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Passport assistance',
        ], $caseManager->id);

        Notification::fake();

        $referralService->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);

        Notification::assertSentTo($caseManager, ReferralStatusChanged::class);
    }

    // ── Integration: acceptance sends ClientUpdateMail to OFW ────

    public function test_accepting_referral_queues_client_update_mail_to_ofw(): void
    {
        Mail::fake();

        $caseManager = User::factory()->create([
            'role' => 'CASE_MANAGER',
            'email' => 'cm@example.com',
        ]);
        $client = Client::factory()->create(['email' => 'ofw@example.com']);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        $agency = Agency::factory()->create();

        $referralService = app(ReferralService::class);
        $referral = $referralService->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Passport assistance',
        ], $caseManager->id);

        $referralService->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);

        Mail::assertQueued(ClientUpdateMail::class, function (ClientUpdateMail $mail) {
            return $mail->hasTo('ofw@example.com');
        });
    }

    public function test_client_update_mail_contains_acceptance_message(): void
    {
        Mail::fake();

        $caseManager = User::factory()->create([
            'role' => 'CASE_MANAGER',
            'email' => 'cm@example.com',
        ]);
        $client = Client::factory()->create(['email' => 'ofw@example.com']);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        $agency = Agency::factory()->create();

        $referralService = app(ReferralService::class);
        $referral = $referralService->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Passport assistance',
        ], $caseManager->id);

        $referralService->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);

        Mail::assertQueued(ClientUpdateMail::class, function (ClientUpdateMail $mail) {
            return str_contains($mail->message, 'Referral status changed from PENDING to PROCESSING');
        });
    }

    // ── No OFW email when client has no email ────────────────────

    public function test_accepting_referral_skips_client_update_mail_when_client_has_no_email(): void
    {
        Mail::fake();

        $caseManager = User::factory()->create([
            'role' => 'CASE_MANAGER',
            'email' => 'cm@example.com',
        ]);
        $client = Client::factory()->create(['email' => null]);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        $agency = Agency::factory()->create();

        $referralService = app(ReferralService::class);
        $referral = $referralService->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Passport assistance',
        ], $caseManager->id);

        $referralService->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);

        Mail::assertNotQueued(ClientUpdateMail::class);
    }

    // ── Idempotency ─────────────────────────────────────────────

    public function test_duplicate_acceptance_does_not_resend_notifications(): void
    {
        Mail::fake();
        Notification::fake();

        $caseManager = User::factory()->create([
            'role' => 'CASE_MANAGER',
            'email' => 'cm@example.com',
        ]);
        $client = Client::factory()->create(['email' => 'ofw@example.com']);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        $agency = Agency::factory()->create();

        $referralService = app(ReferralService::class);
        $referral = $referralService->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Passport assistance',
        ], $caseManager->id);

        Notification::fake();
        Mail::fake();

        $referralService->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);
        // Duplicate should be idempotent — no additional notifications.
        $referralService->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $caseManager->id);

        Notification::assertSentTimes(ReferralStatusChanged::class, 1);
        Mail::assertQueued(ClientUpdateMail::class, 1);
    }

    // ── Queue worker listens to both queues ──────────────────────

    public function test_dev_queue_worker_listens_to_notifications_queue(): void
    {
        $composerJson = file_get_contents(base_path('composer.json'));
        $composer = json_decode($composerJson, true);

        $devScript = $composer['scripts']['dev'][1] ?? '';

        $this->assertStringContainsString('queue:listen', $devScript);
        $this->assertStringContainsString('default,notifications', $devScript);
    }
}
