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
        if ($rawDays !== null && $rawDays !== '' && ! ctype_digit((string) $rawDays)) {
            $this->error('The days option must be a whole number of at least 1.');

            return 1;
        }

        // Read at run time. A default bound to the command's signature would
        // freeze the retention window at whatever it was when php artisan was
        // last started, which is the failure this setting exists to avoid.
        $days = $rawDays !== null && $rawDays !== ''
            ? (int) $rawDays
            : SettingRegistry::int('email_log_retention_days');

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
