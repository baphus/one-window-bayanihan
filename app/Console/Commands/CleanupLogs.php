<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupLogs extends Command
{
    protected $signature = 'logs:cleanup';

    protected $description = 'Clean up old health check logs and system alert logs';

    public function handle()
    {
        $cutoff = now()->subDays(30);

        $healthDeleted = DB::table('health_check_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $alertDeleted = DB::table('system_alert_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $logFiles = glob(storage_path('logs/laravel-*.log'));
        $logDeleted = 0;
        foreach ($logFiles as $file) {
            if (filemtime($file) < now()->subDays(7)->timestamp) {
                unlink($file);
                $logDeleted++;
            }
        }

        $this->info("Cleanup: deleted {$healthDeleted} health logs, {$alertDeleted} alert logs, {$logDeleted} log files");

        return 0;
    }
}
