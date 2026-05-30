<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IpWhitelist;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
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
        $this->withoutMiddleware(IpWhitelist::class);
        Config::set('auth.ip_whitelist.enabled', false);

        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'CASE_MANAGER']);

        // Create the admin user for tests that need auth
        $this->adminUser = User::factory()->create(['role' => 'ADMIN']);
        $this->adminUser->assignRole('ADMIN');
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

    #[Test]
    public function admin_can_view_email_logs_page(): void
    {
        EmailLog::create([
            'to_email' => 'view@example.com',
            'subject' => 'View Test',
            'mailable_type' => 'App\Mail\TestMailable',
            'status' => 'sent',
        ]);

        $response = $this
            ->actingAs($this->adminUser)
            ->get('/admin/system/email-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/System/EmailLogs/Index')
            ->has('logs.data', 1)
        );
    }

    #[Test]
    public function non_admin_cannot_access_email_logs_page(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $caseManager->assignRole('CASE_MANAGER');

        $response = $this
            ->actingAs($caseManager)
            ->get('/admin/system/email-logs');

        $response->assertStatus(403);
    }

    #[Test]
    public function resend_with_job_uuid_retries_queue(): void
    {
        $jobUuid = (string) Str::uuid();

        // Mock Queue::retry() since SyncQueue doesn't support it
        Queue::shouldReceive('retry')->once()->with($jobUuid);

        $log = EmailLog::create([
            'to_email' => 'resend@example.com',
            'subject' => 'Resend Test',
            'mailable_type' => 'App\Mail\TestMailable',
            'status' => 'failed',
            'job_uuid' => $jobUuid,
        ]);

        $response = $this
            ->actingAs($this->adminUser)
            ->post("/admin/system/email-logs/{$log->id}/resend");

        $response->assertStatus(302); // redirect back
    }
}
