<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhilippineAddressService
{
    private const API_BASE = 'https://psgc.cloud/api';

    private const CACHE_TTL = 86400; // 24 hours

    private const CACHE_KEY_REGIONS = 'psgc_regions';

    private const CACHE_KEY_PROVINCES = 'psgc_provinces';

    private const CACHE_KEY_CITIES = 'psgc_cities';

    private const CACHE_KEY_BARANGAYS = 'psgc_barangays';

    public function getRegions(): array
    {
        return Cache::remember(self::CACHE_KEY_REGIONS, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)->get(self::API_BASE.'/regions');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($r) => [
                        'code' => $r['code'],
                        'name' => $r['name'],
                    ])->values()->toArray();
                }

                Log::warning('PSGC API returned non-success for regions', [
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for regions', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    public function getProvinces(?string $regionCode = null): array
    {
        $all = Cache::remember(self::CACHE_KEY_PROVINCES, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)->get(self::API_BASE.'/provinces');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($p) => [
                        'code' => $p['code'],
                        'name' => $p['name'],
                        'regionCode' => $p['regionCode'] ?? '',
                    ])->values()->toArray();
                }

                Log::warning('PSGC API returned non-success for provinces', [
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for provinces', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });

        if ($regionCode) {
            return collect($all)->filter(fn ($p) => $p['regionCode'] === $regionCode)->values()->toArray();
        }

        return $all;
    }

    public function getCities(?string $provinceCode = null): array
    {
        $all = Cache::remember(self::CACHE_KEY_CITIES, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)->get(self::API_BASE.'/cities');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($c) => [
                        'code' => $c['code'],
                        'name' => $c['name'],
                        'type' => $c['type'] ?? 'Municipality',
                        'provinceCode' => $c['provinceCode'] ?? '',
                    ])->values()->toArray();
                }

                Log::warning('PSGC API returned non-success for cities', [
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for cities', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });

        if ($provinceCode) {
            return collect($all)->filter(fn ($c) => $c['provinceCode'] === $provinceCode)->values()->toArray();
        }

        return $all;
    }

    public function getBarangays(?string $cityCode = null): array
    {
        $all = Cache::remember(self::CACHE_KEY_BARANGAYS, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)->get(self::API_BASE.'/barangays');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($b) => [
                        'code' => $b['code'],
                        'name' => $b['name'],
                        'cityCode' => $b['cityCode'] ?? $b['municipalityCode'] ?? '',
                    ])->values()->toArray();
                }

                Log::warning('PSGC API returned non-success for barangays', [
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for barangays', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });

        if ($cityCode) {
            return collect($all)->filter(fn ($b) => $b['cityCode'] === $cityCode)->values()->toArray();
        }

        return $all;
    }
}
