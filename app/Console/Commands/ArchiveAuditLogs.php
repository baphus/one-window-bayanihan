<?php

namespace App\Console\Commands;

use App\Services\AuditArchiveService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive
                            {--days= : Hot retention period in days (defaults to config audit.retention_days)}
                            {--dry-run : List eligible periods without archiving}';

    protected $description = 'Archive audit log months older than the hot retention window to immutable bundles';

    public function handle(AuditArchiveService $service): int
    {
        $days = (int) ($this->option('days') ?: config('audit.retention_days'));
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
