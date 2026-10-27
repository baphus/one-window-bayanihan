<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Listeners\EmailEventSubscriber;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class TestFailedMailable extends Mailable
{
    public $to = [['address' => 'failed@example.com', 'name' => '']];

    public $subject = 'Failed Email';
}

class TestResendMailable extends Mailable
{
    public $to = [['address' => 'resend@example.com', 'name' => '']];

    public $subject = 'Resend Test';
}

class EmailLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);

        // Create the admin user for tests that need auth
        $this->adminUser = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    #[Test]
    public function message_sent_creates_email_log(): void
    {
        // Create the Symfony Email
        $email = new Email;
        $email->from(new Address('sender@example.com'));
        $email->to(new Address('test@example.com'));
        $email->subject('Test Subject');
        $email->text('Test body');

        // Create the Symfony SentMessage (needs envelope with recipient)
        $symfonySentMessage = new SymfonySentMessage($email, Envelope::create($email));
        $sentMessage = new SentMessage($symfonySentMessage);

        // Dispatch the event
        Event::dispatch(new MessageSent($sentMessage));

        $log = EmailLog::where('to_email', 'test@example.com')->first();

        $this->assertNotNull($log);
        $this->assertEquals('test@example.com', $log->to_email);
        $this->assertEquals('Test Subject', $log->subject);
        $this->assertEquals('sent', $log->status);
    }

    #[Test]
    public function failed_job_creates_email_log(): void
    {
        // Create a SendQueuedMailable wrapping a real test mailable class
        $mailable = new TestFailedMailable;
        $queued = new SendQueuedMailable($mailable);

        // Simulate a failed mail job by creating a failed_jobs record
        $uuid = (string) Str::uuid();
        $payload = json_encode([
            'displayName' => get_class($mailable),
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'data' => [
                'command' => serialize($queued),
            ],
        ]);

        \DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $payload,
            'exception' => 'Test exception: Connection refused',
            'failed_at' => now(),
        ]);

        // Run the sync command
        $this->artisan('emails:sync-failed')
            ->assertSuccessful();

        $log = EmailLog::where('job_uuid', $uuid)->first();

        $this->assertNotNull($log);
        $this->assertEquals('failed@example.com', $log->to_email);
        $this->assertEquals('Failed Email', $log->subject);
        $this->assertEquals('failed', $log->status);
        $this->assertStringContainsString('Connection refused', $log->error_message);
    }

    #[Test]
    public function sync_command_skips_duplicates(): void
    {
        $uuid = (string) Str::uuid();

        // Create an existing log entry
        EmailLog::create([
            'to_email' => 'dup@example.com',
            'subject' => 'Duplicate',
            'mailable_type' => 'App\Mail\TestMailable',
            'status' => 'failed',
            'job_uuid' => $uuid,
        ]);

        // Insert matching failed_jobs record
        $payload = json_encode([
            'displayName' => 'App\Mail\TestMailable',
            'data' => ['command' => serialize((object) ['to' => ['dup@example.com'], 'subject' => 'Duplicate'])],
        ]);

        \DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $payload,
            'exception' => 'Error',
            'failed_at' => now(),
        ]);

        $this->artisan('emails:sync-failed')
            ->assertSuccessful();

        // Should still be 1 entry (not duplicated)
        $this->assertEquals(1, EmailLog::where('job_uuid', $uuid)->count());
    }

    #[Test]
    public function prune_command_removes_old_logs(): void
    {
        // Create a log entry older than 90 days
        $oldLog = EmailLog::create([
            'to_email' => 'old@example.com',
            'subject' => 'Old Email',
            'mailable_type' => 'App\Mail\TestMailable',
            'status' => 'sent',
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        // Fix the timestamps directly in DB
        EmailLog::where('id', $oldLog->id)->update([
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        // Create a recent log entry
        EmailLog::create([
            'to_email' => 'recent@example.com',
            'subject' => 'Recent Email',
            'mailable_type' => 'App\Mail\TestMailable',
            'status' => 'sent',
        ]);

        $this->artisan('emails:prune --days=90')
            ->expectsOutputToContain('Pruned')
            ->assertSuccessful();

        $this->assertNull(EmailLog::find($oldLog->id));
        $this->assertNotNull(EmailLog::where('to_email', 'recent@example.com')->first());
    }

    /**
     * A queued retry must not log the same send twice.
     *
     * The queue payload uuid survives release(), so both attempts of one job
     * report the same job_uuid and the second must be recognised as the same
     * send rather than a new one.
     */
    #[Test]
    public function a_retried_job_does_not_create_a_second_log(): void
    {
        $this->fakeQueuedJob($uuid = (string) Str::uuid());

        $this->dispatchMessageSent('retry@example.com', 'Retried Subject');
        // Same job uuid, a second attempt far outside the time window.
        $this->travel(10)->minutes();
        $this->dispatchMessageSent('retry@example.com', 'Retried Subject');

        $this->assertEquals(1, EmailLog::where('to_email', 'retry@example.com')->count());
        $this->assertEquals($uuid, EmailLog::where('to_email', 'retry@example.com')->value('job_uuid'));
    }

    /**
     * Two genuinely separate sends must both be recorded.
     *
     * Distinct job uuids are different send attempts, so they are logged
     * individually even when the recipient and subject match.
     */
    #[Test]
    public function separate_sends_each_create_a_log(): void
    {
        $this->fakeQueuedJob((string) Str::uuid());
        $this->dispatchMessageSent('twice@example.com', 'Verification');

        $this->travel(5)->seconds();

        $this->fakeQueuedJob((string) Str::uuid());
        $this->dispatchMessageSent('twice@example.com', 'Verification');

        $this->assertEquals(2, EmailLog::where('to_email', 'twice@example.com')->count());
    }

    /**
     * Logging must never fail the mail job.
     *
     * This is the defect that caused every email to be delivered three times:
     * the listener threw after the transport had already sent the message, the
     * worker treated the job as failed and retried it, and each retry sent the
     * mail again. A broken log write has to degrade to a warning instead.
     */
    #[Test]
    public function a_failing_log_write_does_not_escape_the_listener(): void
    {
        $this->fakeQueuedJob((string) Str::uuid());

        // Force the listener's own insert to fail.
        EmailLog::creating(fn () => throw new \RuntimeException('logging is broken'));

        $this->dispatchMessageSent('boom@example.com', 'Should not throw');

        // Reaching here without an exception is the assertion: nothing is
        // recorded, but the mail job is allowed to complete.
        $this->assertEquals(0, EmailLog::where('to_email', 'boom@example.com')->count());
    }

    /**
     * The listener must tolerate its own schema not being deployed.
     *
     * resent_at arrived in a later migration. When the column is missing the
     * resend-promotion query throws, which is exactly how the triple-send bug
     * started, so a missing column may only disable that feature.
     */
    #[Test]
    public function a_missing_resent_at_column_does_not_break_logging(): void
    {
        Schema::table('email_logs', fn ($table) => $table->dropColumn('resent_at'));

        $this->fakeQueuedJob((string) Str::uuid());
        $this->dispatchMessageSent('nocolumn@example.com', 'Still works');

        $this->assertEquals(1, EmailLog::where('to_email', 'nocolumn@example.com')->count());
    }

    /**
     * The log table carries the columns the listener queries.
     *
     * The original bug was a committed migration that never ran: the code
     * referenced resent_at and the database did not have it. This pins the
     * schema the listener assumes so a pending migration fails the suite
     * instead of silently resending every email in production.
     */
    #[Test]
    public function email_logs_has_the_columns_the_listener_queries(): void
    {
        foreach (['resent_at', 'job_uuid', 'provider_message_id', 'sent_at', 'delivered_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('email_logs', $column),
                "email_logs is missing the {$column} column the mail listener queries."
            );
        }
    }

    /**
     * Put the subscriber into the state the worker leaves it in mid-job.
     */
    private function fakeQueuedJob(string $uuid): void
    {
        app()->instance(EmailEventSubscriber::class.'.current_job_uuid', $uuid);
    }

    private function dispatchMessageSent(string $to, string $subject): void
    {
        $email = new Email;
        $email->from(new Address('sender@example.com'));
        $email->to(new Address($to));
        $email->subject($subject);
        $email->text('Body');

        Event::dispatch(new MessageSent(
            new SentMessage(new SymfonySentMessage($email, Envelope::create($email)))
        ));
    }
}
