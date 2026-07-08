<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupLogs extends Command
{
    protected $signature = 'logs:cleanup';

    protected $description = 'Clean up old log files';

    public function handle()
    {
        $logFiles = glob(storage_path('logs/laravel-*.log'));
        $logDeleted = 0;
        foreach ($logFiles as $file) {
            if (filemtime($file) < now()->subDays(7)->timestamp) {
                unlink($file);
                $logDeleted++;
            }
        }

        $this->info("Cleanup: deleted {$logDeleted} log files");

        return 0;
    }
}
