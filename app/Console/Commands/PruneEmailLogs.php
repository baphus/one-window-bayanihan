<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use Illuminate\Console\Command;

class PruneEmailLogs extends Command
{
    protected $signature = 'emails:prune {--days=90 : Number of days to retain logs}';

    protected $description = 'Delete email logs older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be at least 1.');

            return 1;
        }

        $cutoff = now()->subDays($days);

        $count = EmailLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} expired email log entries.");

        return 0;
    }
}
