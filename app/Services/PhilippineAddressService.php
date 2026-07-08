<?php

namespace App\Services;

class PhilippineAddressService
{
    private static ?array $data = null;

    /**
     * Load and parse the philippine-addresses.ts file into a nested array.
     *
     * Structure:
     *   regions: [{code, name}]
     *   provincesByRegion: [regionCode => [{code, name}]]
     *   citiesByProvince: [provinceCode => [{code, name}]]
     *   citiesByRegion: [regionCode => [{code, name}]]
     *   barangaysByCity: [cityCode => [{code, name}]]
     *   allByCode: [code => {code, name, type, parent_code}]
     *   codeToName: [code => name]
     */
    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = resource_path('js/data/philippine-addresses.ts');
        if (! is_file($path)) {
            return self::$data = [];
        }

        $source = file_get_contents($path) ?: '';

        // Extract JSON portion between the data section opening { and the final };
        $start = strrpos($source, 'export const philippineAddressData');
        if ($start === false) {
            $start = strpos($source, 'philippineAddressData');
        }
        if ($start === false) {
            return self::$data = [];
        }
        $start = strpos($source, '{', $start);
        $end = strrpos($source, '};');
        if ($start === false || $end === false || $end <= $start) {
            return self::$data = [];
        }

        $json = substr($source, $start, $end - $start + 1);

        // Remove trailing commas before } or ] (JSON5 cleanup)
        $json = preg_replace('/,\s*([}\]])/', '$1', $json);

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return self::$data = [];
        }

        // Build flat code→name map for resolveNames
        $codeToName = [];
        $allByCode = [];

        foreach ($decoded['regions'] ?? [] as $r) {
            $codeToName[$r['code']] = $r['name'];
            $allByCode[$r['code']] = ['code' => $r['code'], 'name' => $r['name'], 'type' => 'region', 'parent_code' => null];
        }

        foreach ($decoded['provincesByRegion'] ?? [] as $regionCode => $provinces) {
            foreach ($provinces as $p) {
                $codeToName[$p['code']] = $p['name'];
                $allByCode[$p['code']] = ['code' => $p['code'], 'name' => $p['name'], 'type' => 'province', 'parent_code' => $regionCode];
            }
        }

        foreach ($decoded['citiesByProvince'] ?? [] as $provinceCode => $cities) {
            foreach ($cities as $c) {
                $codeToName[$c['code']] = $c['name'];
                // Determine if city or municipality (city codes end in 000, but this is heuristic)
                $type = in_array($c['code'], array_column($decoded['citiesByRegion'][$provinceCode] ?? [], 'code')) ? 'city' : 'municipality';
                $allByCode[$c['code']] = ['code' => $c['code'], 'name' => $c['name'], 'type' => $type, 'parent_code' => $provinceCode];
            }
        }

        foreach ($decoded['barangaysByCity'] ?? [] as $cityCode => $barangays) {
            foreach ($barangays as $b) {
                $codeToName[$b['code']] = $b['name'];
                $allByCode[$b['code']] = ['code' => $b['code'], 'name' => $b['name'], 'type' => 'barangay', 'parent_code' => $cityCode];
            }
        }

        self::$data = [
            'regions' => $decoded['regions'] ?? [],
            'provincesByRegion' => $decoded['provincesByRegion'] ?? [],
            'citiesByProvince' => $decoded['citiesByProvince'] ?? [],
            'citiesByRegion' => $decoded['citiesByRegion'] ?? [],
            'barangaysByCity' => $decoded['barangaysByCity'] ?? [],
            'allByCode' => $allByCode,
            'codeToName' => $codeToName,
        ];

        return self::$data;
    }

    public function getRegions(): array
    {
        $data = self::load();

        return $data['regions'] ?? [];
    }

    public function getProvinces(?string $regionCode = null): array
    {
        if (! $regionCode) {
            return [];
        }

        $data = self::load();

        return $data['provincesByRegion'][$regionCode] ?? [];
    }

    public function getCities(?string $provinceCode = null): array
    {
        if (! $provinceCode) {
            return [];
        }

        $data = self::load();

        return $data['citiesByProvince'][$provinceCode] ?? [];
    }

    public function getBarangays(?string $parentCode = null): array
    {
        if (! $parentCode) {
            return [];
        }

        $data = self::load();

        return $data['barangaysByCity'][$parentCode] ?? [];
    }

    public function resolveNames(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $data = self::load();
        $result = [];

        foreach ($codes as $code) {
            if (isset($data['codeToName'][$code])) {
                $result[$code] = $data['codeToName'][$code];
            }
        }

        return $result;
    }

    public function resolveAddressToCodes(array $address): array
    {
        $result = [
            'region' => null,
            'province' => null,
            'city_municipality' => null,
            'barangay' => null,
        ];

        if (empty($address['region'])) {
            return $result;
        }

        $data = self::load();

        // Find region by name (case-insensitive)
        $regionCode = null;
        foreach ($data['regions'] as $region) {
            if (strcasecmp(trim($region['name']), trim($address['region'])) === 0) {
                $regionCode = $region['code'];
                break;
            }
        }

        if (! $regionCode) {
            return $result;
        }

        $result['region'] = $regionCode;

        if (empty($address['province'])) {
            return $result;
        }

        // Find province under this region by name
        $provinceCode = null;
        foreach ($data['provincesByRegion'][$regionCode] ?? [] as $province) {
            if (strcasecmp(trim($province['name']), trim($address['province'])) === 0) {
                $provinceCode = $province['code'];
                break;
            }
        }

        if (! $provinceCode) {
            return $result;
        }

        $result['province'] = $provinceCode;

        if (empty($address['city_municipality'])) {
            return $result;
        }

        // Find city/municipality under this province by name
        $cityCode = null;
        foreach ($data['citiesByProvince'][$provinceCode] ?? [] as $city) {
            if (strcasecmp(trim($city['name']), trim($address['city_municipality'])) === 0) {
                $cityCode = $city['code'];
                break;
            }
        }

        if (! $cityCode) {
            return $result;
        }

        $result['city_municipality'] = $cityCode;

        if (empty($address['barangay'])) {
            return $result;
        }

        // Find barangay under this city by name
        foreach ($data['barangaysByCity'][$cityCode] ?? [] as $barangay) {
            if (strcasecmp(trim($barangay['name']), trim($address['barangay'])) === 0) {
                $result['barangay'] = $barangay['code'];
                break;
            }
        }

        return $result;
    }
}
