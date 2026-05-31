<?php

namespace App\Console\Commands;

use App\Models\PhilippineAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPhilippineAddresses extends Command
{
    protected $signature = 'philippine-addresses:sync';

    protected $description = 'Fetch Philippine address data from PSGC API and store in database';

    private string $apiBase;

    public function handle(): int
    {
        $this->apiBase = config('services.psgc.api_base', 'https://psgc.cloud/api');

        $this->info('Syncing Philippine addresses from PSGC API...');
        $this->newLine();

        PhilippineAddress::truncate();

        $this->syncRegions();

        $this->newLine(2);
        $this->info('Sync complete!');
        $this->table(
            ['Type', 'Count'],
            PhilippineAddress::query()
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderBy('type')
                ->get()
                ->map(fn ($row) => [$row->type, $row->count])
                ->toArray()
        );

        return Command::SUCCESS;
    }

    private function syncRegions(): void
    {
        $regions = $this->fetch($this->apiBase.'/regions');
        if (empty($regions)) {
            $this->warn('No regions fetched — aborting sync.');

            return;
        }

        $regionData = array_map(fn ($r) => [
            'type' => 'region',
            'code' => $r['code'],
            'name' => $r['name'],
            'parent_code' => null,
        ], $regions);

        PhilippineAddress::insert($regionData);
        $this->info('Regions: '.count($regionData));

        $bar = $this->output->createProgressBar(count($regions));
        $bar->start();

        foreach ($regions as $region) {
            $this->syncProvinces($region['code']);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function syncProvinces(string $regionCode): void
    {
        $provinces = $this->fetch($this->apiBase.'/regions/'.$regionCode.'/provinces');
        if (empty($provinces)) {
            return;
        }

        $provinceData = array_map(fn ($p) => [
            'type' => 'province',
            'code' => $p['code'],
            'name' => $p['name'],
            'parent_code' => $regionCode,
        ], $provinces);

        PhilippineAddress::insert($provinceData);

        foreach ($provinces as $province) {
            $this->syncCities($province['code']);
            $this->syncMunicipalities($province['code']);
        }
    }

    private function syncCities(string $provinceCode): void
    {
        $cities = $this->fetch($this->apiBase.'/provinces/'.$provinceCode.'/cities');
        if (empty($cities)) {
            return;
        }

        $cityData = array_map(fn ($c) => [
            'type' => 'city',
            'code' => $c['code'],
            'name' => $c['name'],
            'parent_code' => $provinceCode,
        ], $cities);

        PhilippineAddress::insert($cityData);

        foreach ($cities as $city) {
            $this->syncBarangays('cities', $city['code']);
        }
    }

    private function syncMunicipalities(string $provinceCode): void
    {
        $municipalities = $this->fetch($this->apiBase.'/provinces/'.$provinceCode.'/municipalities');
        if (empty($municipalities)) {
            return;
        }

        $munData = array_map(fn ($m) => [
            'type' => 'municipality',
            'code' => $m['code'],
            'name' => $m['name'],
            'parent_code' => $provinceCode,
        ], $municipalities);

        PhilippineAddress::insert($munData);

        foreach ($municipalities as $municipality) {
            $this->syncBarangays('municipalities', $municipality['code']);
        }
    }

    private function syncBarangays(string $type, string $parentCode): void
    {
        $barangays = $this->fetch($this->apiBase.'/'.$type.'/'.$parentCode.'/barangays');
        if (empty($barangays)) {
            return;
        }

        $barangayData = array_map(fn ($b) => [
            'type' => 'barangay',
            'code' => $b['code'],
            'name' => $b['name'],
            'parent_code' => $parentCode,
        ], $barangays);

        PhilippineAddress::insert($barangayData);
    }

    private function fetch(string $url): array
    {
        try {
            $response = Http::timeout(15)->get($url);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('PSGC API request failed', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::warning('PSGC API request threw exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
