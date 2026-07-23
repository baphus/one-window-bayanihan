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
        $days = (int) ($this->option('days') ?: config('app.trash_retention_days', 90));
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
