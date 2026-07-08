<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
                            {--days=365 : Retention period in days (logs older than this will be deleted)}
                            {--dry-run : Preview count without deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Prune audit log entries older than the specified retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $cutoff = now()->subDays($days);
        $count = AuditLog::where('timestamp', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No audit log entries older than {$days} days found.");

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] Would delete {$count} audit log entries older than {$cutoff->format('Y-m-d H:i:s')}");

            return Command::SUCCESS;
        }

        if (! $force) {
            if (! $this->confirm("Delete {$count} audit log entries older than {$cutoff->format('Y-m-d H:i:s')}?")) {
                $this->info('Pruning cancelled.');

                return Command::SUCCESS;
            }
        }

        $deleted = AuditLog::where('timestamp', '<', $cutoff)->forceDelete();
        $this->info("Pruned {$deleted} audit log entries.");

        return Command::SUCCESS;
    }
}
