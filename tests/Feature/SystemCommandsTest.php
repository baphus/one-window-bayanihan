<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_health_check_command_runs(): void
    {
        $exitCode = Artisan::call('system:health-check');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('health_check_logs', 4);
        $this->assertDatabaseHas('system_settings', ['key' => 'last_health_check_at']);
        $this->assertDatabaseHas('system_settings', ['key' => 'last_health_check_status']);
    }

    #[Test]
    public function test_alert_check_command_runs(): void
    {
        $exitCode = Artisan::call('insights:check-alerts');

        $this->assertSame(0, $exitCode);
    }

    #[Test]
    public function test_cleanup_command_runs(): void
    {
        $oldTime = now()->subDays(31);
        $newTime = now();

        DB::table('health_check_logs')->insert([
            'id' => (string) Str::uuid(),
            'check_type' => 'disk',
            'status' => 'critical',
            'metric_value' => '95%',
            'message' => 'old health log',
            'checked_at' => $oldTime,
            'created_at' => $oldTime,
            'updated_at' => $oldTime,
        ]);

        DB::table('health_check_logs')->insert([
            'id' => (string) Str::uuid(),
            'check_type' => 'disk',
            'status' => 'healthy',
            'metric_value' => '20%',
            'message' => 'new health log',
            'checked_at' => $newTime,
            'created_at' => $newTime,
            'updated_at' => $newTime,
        ]);

        DB::table('system_alert_logs')->insert([
            'id' => (string) Str::uuid(),
            'alert_type' => 'test',
            'severity' => 'warning',
            'message' => 'old alert log',
            'created_at' => $oldTime,
            'updated_at' => $oldTime,
        ]);

        DB::table('system_alert_logs')->insert([
            'id' => (string) Str::uuid(),
            'alert_type' => 'test',
            'severity' => 'warning',
            'message' => 'new alert log',
            'created_at' => $newTime,
            'updated_at' => $newTime,
        ]);

        $logsDir = storage_path('logs');
        $oldLog = $logsDir.DIRECTORY_SEPARATOR.'laravel-1999-01-01-000000.log';
        $newLog = $logsDir.DIRECTORY_SEPARATOR.'laravel-2999-01-01-000000.log';
        file_put_contents($oldLog, 'old');
        file_put_contents($newLog, 'new');
        touch($oldLog, $oldTime->timestamp);
        touch($newLog, $newTime->timestamp);

        $exitCode = Artisan::call('logs:cleanup');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('health_check_logs', 1);
        $this->assertDatabaseCount('system_alert_logs', 1);
        $this->assertFileDoesNotExist($oldLog);
        $this->assertFileExists($newLog);

        @unlink($newLog);
    }
}
