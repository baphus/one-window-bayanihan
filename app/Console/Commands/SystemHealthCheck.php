<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';

    protected $description = 'Run system health checks and log results';

    public function handle(SystemHealthService $service)
    {
        $results = $service->runAllChecks();

        $critical = collect($results)->where('status', 'critical')->count();
        $warning = collect($results)->where('status', 'warning')->count();

        SystemSetting::setValue('last_health_check_at', now()->toDateTimeString(), 'system', 'Last health check timestamp');
        SystemSetting::setValue('last_health_check_status', $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy'), 'system', 'Last health check overall status');

        $this->info("Health check complete: {$critical} critical, {$warning} warning");

        return 0;
    }
}
