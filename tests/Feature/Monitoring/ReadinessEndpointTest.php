<?php

namespace Tests\Feature\Monitoring;

use App\Http\Controllers\Api\ReadinessController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReadinessEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-readiness-token';

    private function freshHeartbeat(): void
    {
        Cache::put(ReadinessController::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addHour());
    }

    // The probe reported this application ready while every outbound mail was
    // failing with a 500: the Resend key was shipped under a name config did
    // not read, so Mail::to() threw before anything was queued. /up cannot see
    // that, and neither could this endpoint.

    #[Test]
    public function it_reports_degraded_when_the_mailer_cannot_be_constructed(): void
    {
        config([
            'monitoring.readiness_token' => self::TOKEN,
            'mail.default' => 'resend',
            'services.resend.key' => null,
        ]);
        $this->freshHeartbeat();

        $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN])
            ->assertStatus(503)
            ->assertJsonPath('checks.mail.status', 'fail')
            ->assertJsonPath('status', 'degraded');
    }

    #[Test]
    public function it_reports_ok_when_the_mailer_can_be_constructed(): void
    {
        config([
            'monitoring.readiness_token' => self::TOKEN,
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key_value',
        ]);
        $this->freshHeartbeat();

        $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN])
            ->assertStatus(200)
            ->assertJsonPath('checks.mail.status', 'ok');
    }

    #[Test]
    public function it_flags_the_log_mailer_as_delivering_nothing(): void
    {
        // Not a failure — `log` is the deliberate pre-launch setting — but it
        // must never read as working delivery on a dashboard.
        config([
            'monitoring.readiness_token' => self::TOKEN,
            'mail.default' => 'log',
        ]);
        $this->freshHeartbeat();

        $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN])
            ->assertStatus(200)
            ->assertJsonPath('checks.mail.status', 'ok')
            ->assertJsonPath('checks.mail.detail', 'writes to the log; nothing is delivered');
    }

    #[Test]
    public function it_404s_when_no_token_is_configured(): void
    {
        // Fail closed. An unset secret must not expose backlog counts to
        // anonymous callers, and must not advertise that the route exists.
        config(['monitoring.readiness_token' => null]);

        $this->getJson('/api/readyz')->assertStatus(404);
    }

    #[Test]
    public function it_404s_when_the_token_does_not_match(): void
    {
        config(['monitoring.readiness_token' => self::TOKEN]);

        $this->getJson('/api/readyz', ['X-Monitoring-Token' => 'wrong'])
            ->assertStatus(404);
    }

    #[Test]
    public function it_404s_when_the_token_header_is_absent(): void
    {
        config(['monitoring.readiness_token' => self::TOKEN]);

        $this->getJson('/api/readyz')->assertStatus(404);
    }

    #[Test]
    public function it_reports_ok_when_every_check_passes(): void
    {
        config(['monitoring.readiness_token' => self::TOKEN]);
        $this->freshHeartbeat();

        $response = $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.database.status', 'ok');
        $response->assertJsonPath('checks.scheduler.status', 'ok');
        $this->assertSame([], $response->json('failing'));
    }

    #[Test]
    public function it_reports_degraded_when_no_scheduler_heartbeat_exists(): void
    {
        config(['monitoring.readiness_token' => self::TOKEN]);
        Cache::forget(ReadinessController::SCHEDULER_HEARTBEAT_KEY);

        $response = $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN]);

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'degraded');
        $response->assertJsonPath('checks.scheduler.status', 'fail');
        $this->assertContains('scheduler', $response->json('failing'));
    }

    #[Test]
    public function it_reports_degraded_when_the_scheduler_heartbeat_is_stale(): void
    {
        // The failure this exists to catch: during the staging bring-up the
        // scheduler could not open a database connection, so no scheduled job
        // ran, while /up answered 200 the entire time.
        config([
            'monitoring.readiness_token' => self::TOKEN,
            'monitoring.thresholds.scheduler_stale_seconds' => 300,
        ]);
        Cache::put(
            ReadinessController::SCHEDULER_HEARTBEAT_KEY,
            now()->subMinutes(30)->toIso8601String(),
            now()->addHour()
        );

        $response = $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN]);

        $response->assertStatus(503);
        $response->assertJsonPath('checks.scheduler.status', 'fail');
        $this->assertGreaterThan(300, $response->json('checks.scheduler.age_seconds'));
    }

    #[Test]
    public function it_skips_queue_checks_when_the_driver_is_not_database(): void
    {
        // The jobs table stops reflecting reality on another driver. Reporting
        // "skipped" is honest; reporting "ok" from a stale table is not.
        config([
            'monitoring.readiness_token' => self::TOKEN,
            'queue.default' => 'sync',
        ]);
        $this->freshHeartbeat();

        $response = $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN]);

        $response->assertStatus(200);
        $response->assertJsonPath('checks.queue_backlog.status', 'skipped');
        $response->assertJsonPath('checks.failed_jobs.status', 'skipped');
    }

    #[Test]
    public function it_reports_degraded_when_the_queue_backlog_exceeds_the_threshold(): void
    {
        config([
            'monitoring.readiness_token' => self::TOKEN,
            'queue.default' => 'database',
            'monitoring.thresholds.queue_backlog' => 2,
        ]);
        $this->freshHeartbeat();

        foreach (range(1, 3) as $i) {
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ]);
        }

        $response = $this->getJson('/api/readyz', ['X-Monitoring-Token' => self::TOKEN]);

        $response->assertStatus(503);
        $response->assertJsonPath('checks.queue_backlog.status', 'fail');
        $response->assertJsonPath('checks.queue_backlog.count', 3);
        $this->assertContains('queue_backlog', $response->json('failing'));
    }
}
