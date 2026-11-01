<?php

namespace App\Console\Commands;

use App\Services\AuditArchiveService;
use App\Settings\SettingRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive
                            {--days= : Hot retention period in days (defaults to the audit_retention_days setting)}
                            {--dry-run : List eligible periods without archiving}';

    protected $description = 'Archive audit log months older than the hot retention window to immutable bundles';

    public function handle(AuditArchiveService $service): int
    {
        // Resolved per run: the scheduled job must honour a change made from
        // the admin page without a redeploy.
        $days = SettingRegistry::daysOption($this->option('days'), 'audit_retention_days');

        if ($days === null) {
            $this->error('The days option must be a whole number of at least 1.');

            return Command::FAILURE;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        $periods = $service->eligiblePeriods($cutoff);

        if ($periods === []) {
            $this->info('No unarchived audit log periods older than the retention window.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Would archive periods: '.implode(', ', $periods));

            return Command::SUCCESS;
        }

        $failures = 0;
        foreach ($periods as $period) {
            try {
                $archive = $service->archivePeriod($period);
                $this->info("Archived {$period}: {$archive->row_count} entries → {$archive->path}");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("Failed to archive {$period}: {$e->getMessage()}");
                logger()->error("Audit archive failed for {$period}", ['exception' => $e]);
            }
        }

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
