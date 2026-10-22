<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Mail\OtpMail;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendFallbackMail extends Mailable
{
    public $to = [['address' => 'fallback@example.com', 'name' => '']];

    public $subject = 'Fallback Resend';
}

class OptionalArgumentMail extends Mailable
{
    public $to = [['address' => 'optional@example.com', 'name' => '']];

    public $subject = 'Optional Argument';

    public function __construct(public ?string $token = null) {}
}

class EmailLogRetryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    /**
     * Regression: resend() used to call Queue::retry(), which does not exist on
     * the queue manager, so any email carrying a job_uuid threw a
     * BadMethodCallException instead of re-queueing the job.
     */
    #[Test]
    public function resend_requeues_the_matching_failed_job(): void
    {
        $jobUuid = (string) Str::uuid();

        $this->insertFailedJob($jobUuid);

        $log = $this->failedEmailLog(['job_uuid' => $jobUuid]);

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // The failed row is consumed and its payload is back on the queue.
        $this->assertSame(0, DB::table('failed_jobs')->where('uuid', $jobUuid)->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame('default', DB::table('jobs')->value('queue'));

        // Marked as pending so the delivery listener can advance this same row.
        $this->assertNotNull($log->fresh()->resent_at);
    }

    #[Test]
    public function resend_leaves_unrelated_failed_jobs_untouched(): void
    {
        $jobUuid = (string) Str::uuid();
        $otherUuid = (string) Str::uuid();

        $this->insertFailedJob($jobUuid);
        $this->insertFailedJob($otherUuid);

        $log = $this->failedEmailLog(['job_uuid' => $jobUuid]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertRedirect();

        $this->assertSame(1, DB::table('failed_jobs')->where('uuid', $otherUuid)->count());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    #[Test]
    public function resend_falls_back_to_the_mailable_when_no_failed_job_matches(): void
    {
        Mail::fake();

        $log = $this->failedEmailLog([
            'job_uuid' => (string) Str::uuid(),
            'mailable_type' => ResendFallbackMail::class,
        ]);

        $this->insertFailedJob('unrelated-'.Str::uuid());

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertQueued(ResendFallbackMail::class);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertNotNull($log->fresh()->resent_at);
    }

    #[Test]
    public function resend_reports_an_error_when_neither_failed_job_nor_mailable_resolves(): void
    {
        $log = $this->failedEmailLog([
            'job_uuid' => (string) Str::uuid(),
            'mailable_type' => 'App\\Mail\\DoesNotExist',
        ]);

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertNull($log->fresh()->resent_at);
    }

    #[Test]
    public function resend_without_job_uuid_or_mailable_reports_an_error(): void
    {
        $log = $this->failedEmailLog(['mailable_type' => 'App\\Mail\\DoesNotExist']);

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull($log->fresh()->resent_at);
    }

    #[Test]
    public function resend_reports_an_error_when_the_mailable_cannot_be_reconstructed(): void
    {
        Mail::fake();

        // OtpMail takes its code and purpose as required constructor arguments,
        // and has no resend() method to rebuild itself from the log row.
        $log = $this->failedEmailLog([
            'mailable_type' => OtpMail::class,
        ]);

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');

        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertNull($log->fresh()->resent_at);
    }

    #[Test]
    public function resend_reports_an_error_when_a_mailable_needing_data_has_no_job_reference(): void
    {
        Mail::fake();

        // Same real mailable, but with a job_uuid whose failed_jobs row is gone,
        // mirroring a pruned or retried-and-forgotten job.
        $log = $this->failedEmailLog([
            'mailable_type' => OtpMail::class,
            'job_uuid' => (string) Str::uuid(),
        ]);

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('jobs')->count());
    }

    /**
     * Regression: an optional constructor parameter was treated as
     * reconstructable, so `new OptionalArgumentMail` queued a mail with a null
     * payload that fails on every send — a permanent failure loop.
     */
    #[Test]
    public function resend_refuses_a_mailable_whose_constructor_arguments_are_optional(): void
    {
        Mail::fake();

        $log = $this->failedEmailLog([
            'mailable_type' => OptionalArgumentMail::class,
        ]);

        $response = $this
            ->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertNull($log->fresh()->resent_at);
    }

    #[Test]
    public function non_failed_email_is_not_resent(): void
    {
        $jobUuid = (string) Str::uuid();
        $this->insertFailedJob($jobUuid);

        $log = EmailLog::create([
            'to_email' => 'sent@example.com',
            'subject' => 'Already Sent',
            'mailable_type' => ResendFallbackMail::class,
            'status' => 'sent',
            'job_uuid' => $jobUuid,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('failed_jobs')->where('uuid', $jobUuid)->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    /**
     * Regression: resent_at marks an in-flight retry, so a second click on a
     * row that still reads 'failed' used to queue a second copy of the same
     * mail for the recipient.
     */
    #[Test]
    public function a_pending_resend_cannot_be_queued_a_second_time(): void
    {
        $jobUuid = (string) Str::uuid();
        $this->insertFailedJob($jobUuid);

        $log = $this->failedEmailLog(['job_uuid' => $jobUuid]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('success');

        $resentAt = $log->fresh()->resent_at;
        $this->assertNotNull($resentAt);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('error');

        // Exactly one copy of the mail is in flight, and the first attempt's
        // marker is untouched.
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertTrue($resentAt->equalTo($log->fresh()->resent_at));
    }

    #[Test]
    public function a_row_already_awaiting_a_retry_is_refused(): void
    {
        $log = $this->failedEmailLog(['resent_at' => now()->subMinute()]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('error');

        $this->assertSame(0, DB::table('jobs')->count());
    }

    /**
     * Regression: a retried mail that finally delivered used to INSERT a second
     * 'sent' row, leaving the original stuck on 'failed' with a live resend
     * button beside a delivered email.
     */
    #[Test]
    public function a_delivered_retry_advances_the_original_failed_row(): void
    {
        Mail::fake();

        $log = $this->failedEmailLog([
            'job_uuid' => (string) Str::uuid(),
            'to_email' => 'fallback@example.com',
            'subject' => 'Fallback Resend',
            'mailable_type' => ResendFallbackMail::class,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('success');

        Event::dispatch($this->messageSentEvent(
            'fallback@example.com',
            'Fallback Resend',
            ResendFallbackMail::class,
            'resend-message-1',
        ));

        $log->refresh();

        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error_message);
        // Carried over so later delivery webhooks still resolve this message.
        $this->assertSame('resend-message-1', $log->provider_message_id);

        $this->assertSame(1, EmailLog::count());
        $this->assertSame($log->id, EmailLog::sole()->id);
    }

    /**
     * Regression: an unrelated send must still get its own row — promotion only
     * claims rows an admin actually asked to retry.
     */
    #[Test]
    public function an_unrelated_send_still_gets_its_own_row(): void
    {
        $log = $this->failedEmailLog(['resent_at' => now()->subMinute()]);

        Event::dispatch($this->messageSentEvent(
            'someone-else@example.com',
            'Fallback Resend',
            ResendFallbackMail::class,
        ));

        $log->refresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame(2, EmailLog::count());
    }

    /**
     * Regression: a retry that failed again used to INSERT another failed row
     * for the same mail, leaving indistinguishable duplicates for an admin to
     * resend twice over.
     */
    #[Test]
    public function a_retry_that_fails_again_reuses_the_original_row(): void
    {
        $jobUuid = (string) Str::uuid();
        $this->insertFailedJob($jobUuid);

        $log = $this->failedEmailLog([
            'job_uuid' => $jobUuid,
            'to_email' => 'fallback@example.com',
            'subject' => 'Fallback Resend',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('success');

        // queue:retry keeps the payload uuid, so the retried job reports the
        // same job_uuid as the original failure.
        Event::dispatch(new JobFailed(
            'database',
            $this->fakeMailJob($jobUuid),
            new RuntimeException('SMTP refused the retry'),
        ));

        $log->refresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame('SMTP refused the retry', $log->error_message);
        $this->assertSame($jobUuid, $log->job_uuid);
        // Pending marker cleared, so the row is retryable again on its own.
        $this->assertNull($log->resent_at);
        $this->assertSame(1, EmailLog::count());
    }

    /**
     * The same guarantee for the mailable-rebuild path, where the re-queued job
     * carries a brand new uuid and can only be matched back through resent_at.
     */
    #[Test]
    public function a_rebuilt_mailable_that_fails_again_reuses_the_original_row(): void
    {
        Mail::fake();

        $log = $this->failedEmailLog([
            'to_email' => 'fallback@example.com',
            'subject' => 'Fallback Resend',
            'mailable_type' => ResendFallbackMail::class,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.system.email-logs.resend', $log->id))
            ->assertSessionHas('success');

        Event::dispatch(new JobFailed(
            'database',
            $this->fakeMailJob((string) Str::uuid()),
            new RuntimeException('SMTP refused the rebuild'),
        ));

        $log->refresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame('SMTP refused the rebuild', $log->error_message);
        $this->assertNull($log->resent_at);
        $this->assertSame(1, EmailLog::count());
    }

    /**
     * Insert a row shaped like a `database-uuids` failer record. The payload
     * carries no `data.command`, which is what `queue:retry` unserializes; a
     * realistic one would need a live mailable.
     */
    private function insertFailedJob(string $uuid): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'Illuminate\\Mail\\SendQueuedMailable',
            ]),
            'exception' => 'Connection refused',
            'failed_at' => now(),
        ]);
    }

    /**
     * A queued-mail job carrying $uuid, shaped the way the failure listener
     * reads it off the failed_jobs payload.
     */
    private function fakeMailJob(string $uuid): JobContract
    {
        $payload = json_encode([
            'uuid' => $uuid,
            'displayName' => 'Illuminate\\Mail\\SendQueuedMailable',
            'data' => [
                'command' => serialize(new SendQueuedMailable(new ResendFallbackMail)),
            ],
        ]);

        $job = $this->createMock(JobContract::class);
        // The framework's Job::payload() returns the decoded array.
        $job->method('payload')->willReturn(json_decode($payload, true));
        $job->method('getJobId')->willReturn($uuid);

        return $job;
    }

    private function messageSentEvent(string $to, string $subject, string $mailableType, ?string $providerMessageId = null): MessageSent
    {
        $email = new Email;
        $email->from(new Address('sender@example.com'));
        $email->to(new Address($to));
        $email->subject($subject);
        $email->text('Body');

        if ($providerMessageId !== null) {
            $email->getHeaders()->addTextHeader('X-Resend-Email-ID', $providerMessageId);
        }

        $sentMessage = new SentMessage(new SymfonySentMessage($email, Envelope::create($email)));

        return new MessageSent($sentMessage, ['__laravel_mailable' => $mailableType]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function failedEmailLog(array $attributes = []): EmailLog
    {
        return EmailLog::create(array_merge([
            'to_email' => 'failed@example.com',
            'subject' => 'Failed Email',
            'mailable_type' => ResendFallbackMail::class,
            'status' => 'failed',
        ], $attributes));
    }
}
