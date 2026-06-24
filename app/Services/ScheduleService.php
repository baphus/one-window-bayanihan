<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class ScheduleService
{
    public function getTasks(): array
    {
        $tasks = [];

        $taskConfigs = Cache::remember('scheduled_task_configs', 3600, function () {
            return SystemSetting::getByCategory('scheduled_task')
                ->keyBy('key')
                ->map(fn ($s) => json_decode($s->value, true) ?: ['enabled' => true])
                ->toArray();
        });

        $knownTasks = [
            'helpcenter:sync' => ['command' => 'helpcenter:sync', 'description' => 'Sync help center articles', 'default_interval' => 'every hour', 'enabled' => true],
        ];

        foreach ($knownTasks as $command => $defaults) {
            $config = $taskConfigs[$command] ?? ['enabled' => true];
            $tasks[] = [
                'command' => $command,
                'description' => $defaults['description'],
                'interval' => $defaults['default_interval'],
                'enabled' => (bool) ($config['enabled'] ?? true),
                'last_run' => $this->getLastRunTime($command),
            ];
        }

        return $tasks;
    }

    public function toggle(string $command): void
    {
        $existing = SystemSetting::where('key', $command)
            ->where('category', 'scheduled_task')
            ->first();

        if ($existing) {
            $config = json_decode($existing->value, true) ?: ['enabled' => true];
            $config['enabled'] = ! ($config['enabled'] ?? true);
            $existing->update(['value' => json_encode($config)]);
        } else {
            SystemSetting::create([
                'key' => $command,
                'value' => json_encode(['enabled' => false]),
                'category' => 'scheduled_task',
                'description' => "Scheduled task: $command",
            ]);
        }

        Cache::forget('scheduled_task_configs');
    }

    private function getLastRunTime(string $command): ?string
    {
        $val = SystemSetting::getValue("last_run_$command");

        return $val ?: null;
    }
}
