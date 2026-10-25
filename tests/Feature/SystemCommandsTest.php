<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_cleanup_command_runs(): void
    {
        $logsDir = storage_path('logs');
        $oldTime = now()->subDays(31);
        $newTime = now();

        $oldLog = $logsDir.DIRECTORY_SEPARATOR.'laravel-1999-01-01-000000.log';
        $newLog = $logsDir.DIRECTORY_SEPARATOR.'laravel-2999-01-01-000000.log';
        file_put_contents($oldLog, 'old');
        file_put_contents($newLog, 'new');
        touch($oldLog, $oldTime->timestamp);
        touch($newLog, $newTime->timestamp);

        $exitCode = Artisan::call('logs:cleanup');

        $this->assertSame(0, $exitCode);
        $this->assertFileDoesNotExist($oldLog);
        $this->assertFileExists($newLog);

        @unlink($newLog);
    }

    #[Test]
    public function test_single_file_log_is_purged(): void
    {
        $logsDir = storage_path('logs');
        $oldTime = now()->subDays(31)->timestamp;

        // LOG_STACK=single writes laravel.log, not the daily laravel-*.log name.
        $single = $logsDir.DIRECTORY_SEPARATOR.'laravel.log';

        try {
            file_put_contents($single, 'single');
        } catch (\ErrorException $e) {
            $this->markTestSkipped('laravel.log is held open by another process: '.$e->getMessage());
        }

        touch($single, $oldTime);

        $exitCode = Artisan::call('logs:cleanup');

        $this->assertSame(0, $exitCode);
        $this->assertFileDoesNotExist($single);
    }

    #[Test]
    public function test_stack_style_log_is_purged(): void
    {
        $logsDir = storage_path('logs');
        $oldTime = now()->subDays(31)->timestamp;

        // Log::stack() used to concatenate channel names into one filename.
        $stackLog = $logsDir.DIRECTORY_SEPARATOR.'laravel-stack-daily.log';
        file_put_contents($stackLog, 'stack');
        touch($stackLog, $oldTime);

        $exitCode = Artisan::call('logs:cleanup');

        $this->assertSame(0, $exitCode);
        $this->assertFileDoesNotExist($stackLog);
    }

    #[Test]
    public function test_retention_commands_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events());

        $emailPrune = $events->first(fn ($event): bool => str_contains((string) $event->command, 'emails:prune'));
        $this->assertNotNull($emailPrune, 'emails:prune is scheduled.');
        $this->assertSame('0 0 * * *', $emailPrune->expression);

        $failedJobs = $events->first(fn ($event): bool => str_contains((string) $event->command, 'queue:prune-failed'));
        $this->assertNotNull($failedJobs, 'queue:prune-failed is scheduled.');
        $this->assertStringContainsString('--hours=168', (string) $failedJobs->command);
        $this->assertSame('0 0 * * 0', $failedJobs->expression);
    }
}
