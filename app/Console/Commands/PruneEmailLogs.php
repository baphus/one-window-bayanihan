<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Settings\SettingRegistry;
use Illuminate\Console\Command;

class PruneEmailLogs extends Command
{
    protected $signature = 'emails:prune {--days= : Number of days to retain logs (defaults to the email_log_retention_days setting)}';

    protected $description = 'Delete email logs older than the specified number of days';

    public function handle(): int
    {
        $rawDays = $this->option('days');

        // Reject garbage the way cases:purge-trashed does. A bare `$days < 1`
        // guard is not enough: (int) '10abc' is 10 and casts silently, so a
        // typo in a cron line would quietly prune on a window nobody chose.
        // Read at run time: a default bound to the signature would freeze the
        // retention window at whatever it was when php artisan started.
        $days = SettingRegistry::daysOption($rawDays, 'email_log_retention_days');

        if ($days === null) {
            $this->error('The days option must be a whole number of at least 1.');

            return 1;
        }

        $cutoff = now()->subDays($days);

        $count = EmailLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} expired email log entries.");

        return 0;
    }
}
