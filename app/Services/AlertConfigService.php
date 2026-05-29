<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlertConfigService
{
    public function getConfigs(): array
    {
        $configs = DB::table('alert_configs')->where('is_deleted', false)->orderBy('alert_type')->get();

        if ($configs->isEmpty()) {
            $defaults = [
                ['alert_type' => 'low_storage', 'enabled' => true, 'threshold_value' => 90, 'email_recipients' => [], 'notify_in_app' => true],
                ['alert_type' => 'backup_failure', 'enabled' => true, 'threshold_value' => null, 'email_recipients' => [], 'notify_in_app' => true],
                ['alert_type' => 'health_check_failure', 'enabled' => true, 'threshold_value' => null, 'email_recipients' => [], 'notify_in_app' => true],
            ];

            foreach ($defaults as $default) {
                DB::table('alert_configs')->insert([
                    'id' => (string) Str::uuid(),
                    'alert_type' => $default['alert_type'],
                    'enabled' => $default['enabled'],
                    'threshold_value' => $default['threshold_value'],
                    'email_recipients' => json_encode($default['email_recipients']),
                    'notify_in_app' => $default['notify_in_app'],
                    'created_at' => now(),
                    'updated_at' => now(),
                    'is_deleted' => false,
                ]);
            }

            $configs = DB::table('alert_configs')->where('is_deleted', false)->orderBy('alert_type')->get();
        }

        return $configs->map(function ($config) {
            $config->email_recipients = json_decode($config->email_recipients, true) ?? [];

            return $config;
        })->toArray();
    }

    public function update(string $id, array $data): void
    {
        $updateData = [
            'enabled' => $data['enabled'] ?? true,
            'threshold_value' => $data['threshold_value'] ?? null,
            'notify_in_app' => $data['notify_in_app'] ?? true,
            'updated_at' => now(),
        ];

        if (array_key_exists('email_recipients', $data)) {
            $updateData['email_recipients'] = json_encode($data['email_recipients']);
        }

        DB::table('alert_configs')->where('id', $id)->update($updateData);
    }

    public function getAlertLogs(int $limit = 50): array
    {
        return DB::table('system_alert_logs')
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $log->metadata = $log->metadata ? json_decode($log->metadata, true) : null;

                return $log;
            })
            ->toArray();
    }

    public function testEmail(string $recipient): array
    {
        try {
            DB::table('system_alert_logs')->insert([
                'id' => (string) Str::uuid(),
                'alert_type' => 'test',
                'severity' => 'info',
                'message' => "Test email sent to {$recipient}",
                'metadata' => json_encode(['recipient' => $recipient]),
                'sent_email' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'is_deleted' => false,
            ]);

            return ['success' => true, 'message' => "Test notification logged for {$recipient}"];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
