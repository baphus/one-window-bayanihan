<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackupStatusService
{
    public function getBackupStatus(): array
    {
        return Cache::remember('supabase_backups', 300, function () {
            try {
                $ref = $this->getProjectRef();
                $response = Http::withToken(config('services.supabase.service_key'))
                    ->get("https://api.supabase.com/v1/projects/{$ref}/database/backups");

                if (! $response->successful()) {
                    throw new \Exception('Supabase API returned '.$response->status());
                }

                $data = $response->json();
                $backups = collect($data['backups'] ?? [])->map(fn ($b) => [
                    'id' => $b['id'] ?? '',
                    'status' => $b['status'] ?? 'unknown',
                    'created_at' => $b['inserted_at'] ?? $b['created_at'] ?? '',
                    'size_mb' => isset($b['size_bytes']) ? round($b['size_bytes'] / 1048576, 2) : 0,
                    'type' => $b['type'] ?? 'scheduled',
                ])->toArray();

                $lastBackup = collect($backups)->where('status', 'completed')->first();

                return [
                    'backups' => $backups,
                    'last_backup' => $lastBackup['created_at'] ?? 'No completed backups',
                    'backup_count' => count($backups),
                    'total_size_mb' => round(collect($backups)->sum('size_mb'), 2),
                ];
            } catch (\Exception $e) {
                Log::warning('Supabase backup API error: '.$e->getMessage());

                return [
                    'error' => 'Could not fetch backup data: '.$e->getMessage(),
                    'backups' => [],
                    'last_backup' => 'Unavailable',
                    'backup_count' => 0,
                    'total_size_mb' => 0,
                ];
            }
        });
    }

    private function getProjectRef(): string
    {
        $url = config('services.supabase.url', env('SUPABASE_URL', ''));
        preg_match('/https:\/\/(.+)\.supabase\.co/', $url, $matches);

        return $matches[1] ?? $url;
    }
}
