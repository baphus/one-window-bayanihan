<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private ?Cloudinary $cloudinary = null;

    public function __construct()
    {
        $cloudUrl = config('cloudinary.cloud_url');

        if (! $cloudUrl) {
            return;
        }

        try {
            $this->cloudinary = new Cloudinary($cloudUrl);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary initialization error: '.$e->getMessage());
        }
    }

    public function getStorageUsage(): array
    {
        if (! $this->cloudinary) {
            return ['error' => 'Cloudinary is not configured.'];
        }

        return Cache::remember('cloudinary_usage', 300, function () {
            try {
                $usage = $this->cloudinary->adminApi()->usage();

                return [
                    'storage' => [
                        'used_mb' => round($usage['storage']['used'] / 1048576, 2),
                        'limit_mb' => round($usage['storage']['limit'] / 1048576, 2),
                        'usage_percent' => $usage['storage']['usage'] ?? 0,
                    ],
                    'bandwidth' => [
                        'used_mb' => round(($usage['bandwidth']['used'] ?? 0) / 1048576, 2),
                        'limit_mb' => round(($usage['bandwidth']['limit'] ?? 0) / 1048576, 2),
                        'usage_percent' => $usage['bandwidth']['usage'] ?? 0,
                    ],
                    'credits' => [
                        'used' => $usage['credits']['used'] ?? 0,
                        'limit' => $usage['credits']['limit'] ?? 0,
                    ],
                ];
            } catch (\Exception $e) {
                Log::warning('Cloudinary API error: '.$e->getMessage());

                return ['error' => 'Could not fetch Cloudinary data: '.$e->getMessage()];
            }
        });
    }

    public function getRecentMedia(int $limit = 10): array
    {
        if (! $this->cloudinary) {
            return ['error' => 'Cloudinary is not configured.'];
        }

        try {
            $result = $this->cloudinary->adminApi()->assets([
                'max_results' => $limit,
                'sort_by' => 'created_at',
                'direction' => 'desc',
            ]);

            return collect($result['resources'] ?? [])->map(fn ($r) => [
                'public_id' => $r['public_id'],
                'format' => $r['format'],
                'bytes' => $r['bytes'],
                'size_mb' => round($r['bytes'] / 1048576, 2),
                'created_at' => $r['created_at'],
                'url' => $r['secure_url'],
            ])->toArray();
        } catch (\Exception $e) {
            return ['error' => 'Could not fetch recent media'];
        }
    }
}
