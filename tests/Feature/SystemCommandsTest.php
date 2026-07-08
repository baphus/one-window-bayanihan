<?php

namespace Tests\Feature;

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
}
