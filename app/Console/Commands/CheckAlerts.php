<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckAlerts extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Check alert thresholds and create alert logs';

    public function handle()
    {
        $configs = DB::table('alert_configs')->where('enabled', true)->where('is_deleted', false)->get();
        $alertsTriggered = 0;

        foreach ($configs as $config) {
            $triggered = false;
            $message = '';

            if ($config->alert_type === 'low_storage') {
                $storagePercent = DB::table('health_check_logs')
                    ->where('check_type', 'disk')
                    ->orderBy('checked_at', 'desc')
                    ->value('metric_value');

                if ($storagePercent && (float) $storagePercent >= (float) $config->threshold_value) {
                    $triggered = true;
                    $message = "Disk storage at {$storagePercent}%, exceeding threshold of {$config->threshold_value}%";
                }
            }

            if ($config->alert_type === 'backup_failure') {
                $backupStatus = DB::table('health_check_logs')
                    ->where('check_type', 'backup')
                    ->orderBy('checked_at', 'desc')
                    ->value('status');

                if ($backupStatus === 'critical' || $backupStatus === 'warning') {
                    $triggered = true;
                    $message = "Backup check returned {$backupStatus} status";
                }
            }

            if ($config->alert_type === 'health_check_failure') {
                $criticalCount = DB::table('health_check_logs')
                    ->where('status', 'critical')
                    ->where('checked_at', '>=', now()->subHours(24))
                    ->count();

                if ($criticalCount > 0) {
                    $triggered = true;
                    $message = "{$criticalCount} critical health check(s) in the last 24 hours";
                }
            }

            if ($triggered) {
                DB::table('system_alert_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'alert_type' => $config->alert_type,
                    'severity' => 'warning',
                    'message' => $message,
                    'metadata' => json_encode(['config_id' => $config->id]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('alert_configs')->where('id', $config->id)->update([
                    'last_triggered_at' => now(),
                ]);

                $alertsTriggered++;
            }
        }

        $this->info("Alert check complete: {$alertsTriggered} alert(s) triggered");

        return 0;
    }
}
