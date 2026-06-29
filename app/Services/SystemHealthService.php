<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SystemHealthService
{
    public function getOverview(): array
    {
        $checks = DB::table('health_check_logs as h1')
            ->select('h1.check_type', 'h1.status', 'h1.metric_value', 'h1.message', 'h1.checked_at')
            ->whereRaw('h1.id = (select h2.id from health_check_logs h2 where h2.check_type = h1.check_type order by h2.checked_at desc limit 1)')
            ->orderBy('h1.checked_at', 'desc')
            ->get();

        $overall = 'healthy';
        foreach ($checks as $check) {
            if ($check->status === 'critical') {
                $overall = 'critical';
                break;
            }
            if ($check->status === 'warning') {
                $overall = 'warning';
            }
        }

        return ['overall' => $overall, 'checks' => $checks];
    }

    public function runAllChecks(): array
    {
        $results = [
            $this->checkQueue(),
            $this->checkCache(),
            $this->checkDisk(),
            $this->checkDatabase(),
        ];

        $now = now();
        foreach ($results as $r) {
            DB::table('health_check_logs')->insert([
                'id' => (string) Str::uuid(),
                'check_type' => $r['type'],
                'status' => $r['status'],
                'metric_value' => $r['metric_value'] ?? null,
                'message' => $r['message'] ?? null,
                'checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $results;
    }

    private function checkQueue(): array
    {
        try {
            $count = DB::table('jobs')->count();

            return ['type' => 'queue', 'status' => 'healthy', 'metric_value' => (string) $count, 'message' => "$count job(s) in queue"];
        } catch (\Exception $e) {
            Log::error('SystemHealth: Queue check failed', ['message' => $e->getMessage(), 'exception' => $e]);

            return ['type' => 'queue', 'status' => 'warning', 'metric_value' => '0', 'message' => 'Queue health check failed'];
        }
    }

    private function checkCache(): array
    {
        try {
            $count = DB::table('cache')->count();

            return ['type' => 'cache', 'status' => 'healthy', 'metric_value' => (string) $count, 'message' => "$count cache entries"];
        } catch (\Exception $e) {
            Log::error('SystemHealth: Cache check failed', ['message' => $e->getMessage(), 'exception' => $e]);

            return ['type' => 'cache', 'status' => 'warning', 'metric_value' => '0', 'message' => 'Cache health check failed'];
        }
    }

    private function checkDisk(): array
    {
        $path = storage_path('logs');
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $percent = $total > 0 ? round(($total - $free) / $total * 100, 1) : 0;
        $freeGb = round($free / 1073741824, 2);
        $status = $percent > 90 ? 'critical' : ($percent > 75 ? 'warning' : 'healthy');

        return ['type' => 'disk', 'status' => $status, 'metric_value' => "$percent%", 'message' => "{$freeGb}GB free ({$percent}% used)"];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $migrations = DB::table('migrations')->count();

            return ['type' => 'database', 'status' => 'healthy', 'metric_value' => (string) $migrations, 'message' => "Connected, $migrations migrations run"];
        } catch (\Exception $e) {
            Log::error('SystemHealth: Database check failed', ['message' => $e->getMessage(), 'exception' => $e]);

            return ['type' => 'database', 'status' => 'critical', 'metric_value' => '0', 'message' => 'Database health check failed'];
        }
    }
}
