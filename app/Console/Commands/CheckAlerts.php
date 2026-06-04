<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckAlerts extends Command
{
    protected $signature = 'insights:check-alerts';

    protected $description = 'Check all alert conditions and create notifications';

    public function handle(AlertService $alertService): int
    {
        $result = $alertService->checkAlerts();

        $this->info("Alert check complete: {$result['created']} alert(s) created");

        return 0;
    }
}
