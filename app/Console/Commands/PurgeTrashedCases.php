<?php

namespace App\Console\Commands;

use App\Services\CaseService;
use App\Settings\SettingRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeTrashedCases extends Command
{
    protected $signature = 'cases:purge-trashed
                            {--days= : Retention period in days (defaults to the trash_retention_days setting)}';

    protected $description = 'Permanently delete soft-deleted cases older than the configured retention period.';

    public function handle(CaseService $caseService): int
    {
        $rawDays = $this->option('days');

        // A rejected value must abort before anything is computed: a negative
        // or garbage option would otherwise be cast to a 0-day retention (or
        // flip the cutoff into the future) and purge every trashed case.
        // SettingRegistry::daysOption rejects anything that is not a whole
        // number, so '10abc' cannot silently become 10.
        $days = SettingRegistry::daysOption($rawDays, 'trash_retention_days');

        // Zero is rejected as loudly as a negative value: a 0-day retention
        // flips the cutoff to "now" and would purge every trashed case in one
        // run, which no trash policy intends. The guard also covers the
        // setting-derived default so a misconfigured value cannot silently
        // turn the scheduled run into a mass purge. This command keeps its own
        // wording because a mass purge is the worst outcome in the system.
        if ($days === null || $days < 1) {
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
