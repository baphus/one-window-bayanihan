<?php

namespace App\Console\Commands;

use App\Services\CaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeTrashedCases extends Command
{
    protected $signature = 'cases:purge-trashed
                            {--days= : Retention period in days (defaults to config app.trash_retention_days or 90)}';

    protected $description = 'Permanently delete soft-deleted cases older than the configured retention period.';

    public function handle(CaseService $caseService): int
    {
        $rawDays = $this->option('days');

        // A rejected value must abort before anything is computed: a negative
        // or garbage option would otherwise be cast to a 0-day retention (or
        // flip the cutoff into the future) and purge every trashed case. Same
        // guard style as emails:prune — error message plus a failing exit code.
        if ($rawDays !== null && $rawDays !== '' && ! ctype_digit((string) $rawDays)) {
            $this->error('The days option must be a whole number of at least 1.');

            return Command::FAILURE;
        }

        $days = $rawDays !== null && $rawDays !== ''
            ? (int) $rawDays
            : (int) config('app.trash_retention_days', 90);

        // Zero is rejected as loudly as a negative value: a 0-day retention
        // flips the cutoff to "now" and would purge every trashed case in one
        // run, which no trash policy intends. The guard also covers the
        // config-derived default so a misconfigured APP_TRASH_RETENTION_DAYS
        // cannot silently turn the scheduled run into a mass purge.
        if ($days < 1) {
            $this->error('The days option must be at least 1; a retention period below 1 day would purge every trashed case.');

            return Command::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = $caseService->purgeTrashedCases($days);

        if ($count === 0) {
            $this->info('No cases to purge.');
            Log::info('cases:purge-trashed — no cases older than retention period.', [
                'retention_days' => $days,
                'cutoff' => $cutoff->toDateString(),
            ]);

            return Command::SUCCESS;
        }

        $message = "Purged {$count} case(s) older than retention period.";
        $this->info($message);

        Log::info("cases:purge-trashed — {$message}", [
            'purged_count' => $count,
            'retention_days' => $days,
            'cutoff' => $cutoff->toDateString(),
        ]);

        return Command::SUCCESS;
    }
}
