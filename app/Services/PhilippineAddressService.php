<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhilippineAddressService
{
    private const CACHE_TTL = 86400; // 24 hours

    private const CACHE_KEY_REGIONS = 'psgc_regions';

    private function apiBase(): string
    {
        return config('services.psgc.api_base', 'https://psgc.cloud/api');
    }

    public function getRegions(): array
    {
        return Cache::remember(self::CACHE_KEY_REGIONS, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(10)->get($this->apiBase().'/regions');

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
        if (! $regionCode) {
            return [];
        }

        return Cache::remember('psgc_provinces_'.$regionCode, self::CACHE_TTL, function () use ($regionCode) {
            try {
                $response = Http::timeout(10)->get($this->apiBase().'/regions/'.$regionCode.'/provinces');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($p) => [
                        'code' => $p['code'],
                        'name' => $p['name'],
                    ])->values()->toArray();
                }

                Log::warning('PSGC API returned non-success for provinces', [
                    'region' => $regionCode,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for provinces', [
                    'region' => $regionCode,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    public function getCities(?string $provinceCode = null): array
    {
        if (! $provinceCode) {
            return [];
        }

        $cities = Cache::remember('psgc_cities_'.$provinceCode, self::CACHE_TTL, function () use ($provinceCode) {
            try {
                $response = Http::timeout(10)->get($this->apiBase().'/provinces/'.$provinceCode.'/cities');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($c) => [
                        'code' => $c['code'],
                        'name' => $c['name'],
                    ])->values()->toArray();
                }

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for cities', [
                    'province' => $provinceCode,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });

        $municipalities = Cache::remember('psgc_municipalities_'.$provinceCode, self::CACHE_TTL, function () use ($provinceCode) {
            try {
                $response = Http::timeout(10)->get($this->apiBase().'/provinces/'.$provinceCode.'/municipalities');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($m) => [
                        'code' => $m['code'],
                        'name' => $m['name'],
                    ])->values()->toArray();
                }

                return [];
            } catch (\Throwable $e) {
                Log::warning('PSGC API request failed for municipalities', [
                    'province' => $provinceCode,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });

        return array_merge($cities, $municipalities);
    }

    public function getBarangays(?string $cityCode = null): array
    {
        if (! $cityCode) {
            return [];
        }

        return Cache::remember('psgc_barangays_'.$cityCode, self::CACHE_TTL, function () use ($cityCode) {
            // Try cities endpoint first
            try {
                $response = Http::timeout(10)->get($this->apiBase().'/cities/'.$cityCode.'/barangays');

                if ($response->successful()) {
                    return collect($response->json())->map(fn ($b) => [
                        'code' => $b['code'],
                        'name' => $b['name'],
                    ])->values()->toArray();
                }

                // If 404, fallback to municipalities endpoint
                if ($response->status() === 404) {
                    return $this->fetchMunicipalityBarangays($cityCode);
                }

                Log::warning('PSGC API returned non-success for barangays', [
                    'city' => $cityCode,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Throwable $e) {
                // Network error — try municipalities as fallback
                Log::warning('PSGC API request failed for barangays (city), trying municipality fallback', [
                    'city' => $cityCode,
                    'error' => $e->getMessage(),
                ]);

                return $this->fetchMunicipalityBarangays($cityCode);
            }
        });
    }

    private function fetchMunicipalityBarangays(string $code): array
    {
        try {
            $response = Http::timeout(10)->get($this->apiBase().'/municipalities/'.$code.'/barangays');

            if ($response->successful()) {
                return collect($response->json())->map(fn ($b) => [
                    'code' => $b['code'],
                    'name' => $b['name'],
                ])->values()->toArray();
            }

            Log::warning('PSGC API returned non-success for barangays (municipality)', [
                'municipality' => $code,
                'status' => $response->status(),
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::warning('PSGC API request failed for barangays (municipality)', [
                'municipality' => $code,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
