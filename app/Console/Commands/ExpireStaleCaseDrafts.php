<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesCaseDraftMaintenanceConnection;
use App\Services\CaseDraftService;
use Illuminate\Console\Command;

class ExpireStaleCaseDrafts extends Command
{
    use UsesCaseDraftMaintenanceConnection;

    protected $signature = 'case-drafts:expire-stale';

    protected $description = 'Expire case drafts that have been stale for 90 days';

    public function handle(CaseDraftService $caseDraftService): int
    {
        try {
            $this->useCaseDraftMaintenanceConnection();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $expired = $caseDraftService->expireStaleDrafts(90);

        $this->info("Expired {$expired} stale case drafts.");

        return self::SUCCESS;
    }
}
