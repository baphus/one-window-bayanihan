<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PhilippineAddressService
{
    private static ?array $data = null;

    /**
     * Load and index the address dataset into a nested array.
     *
     * Structure:
     *   regions: [{code, name}]
     *   provincesByRegion: [regionCode => [{code, name}]]
     *   citiesByProvince: [provinceCode => [{code, name}]]
     *   citiesByRegion: [regionCode => [{code, name}]]
     *   barangaysByCity: [cityCode => [{code, name}]]
     *   codeToName: [code => name]
     */
    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        // ponytail: parse once per cache TTL instead of once per cold worker;
        // bump the key if the dataset format ever changes.
        return self::$data = Cache::remember('ph-addresses-v1', now()->addDay(), function (): array {
            $decoded = self::readDataset();

            if ($decoded === null) {
                return [];
            }

            // ponytail: the former allByCode index (code => {code, name, type,
            // parent_code}) had no callers; derive it on demand from PSGC code
            // prefixes (2/4/7/9 digits = region/province/city_municipality/
            // barangay) if it is ever needed again.
            $codeToName = [];

            foreach ($decoded['regions'] ?? [] as $region) {
                $codeToName[$region['code']] = $region['name'];
            }

            foreach ($decoded['provincesByRegion'] ?? [] as $provinces) {
                foreach ($provinces as $province) {
                    $codeToName[$province['code']] = $province['name'];
                }
            }

            foreach ($decoded['citiesByProvince'] ?? [] as $cities) {
                foreach ($cities as $city) {
                    $codeToName[$city['code']] = $city['name'];
                }
            }

            foreach ($decoded['citiesByRegion'] ?? [] as $cities) {
                foreach ($cities as $city) {
                    $codeToName[$city['code']] = $city['name'];
                }
            }

            foreach ($decoded['barangaysByCity'] ?? [] as $barangays) {
                foreach ($barangays as $barangay) {
                    $codeToName[$barangay['code']] = $barangay['name'];
                }
            }

            return [
                'regions' => $decoded['regions'] ?? [],
                'provincesByRegion' => $decoded['provincesByRegion'] ?? [],
                'citiesByProvince' => $decoded['citiesByProvince'] ?? [],
                'citiesByRegion' => $decoded['citiesByRegion'] ?? [],
                'barangaysByCity' => $decoded['barangaysByCity'] ?? [],
                'codeToName' => $codeToName,
            ];
        });
    }

    /**
     * The dataset is a plain JSON file; nothing else is parsed at runtime.
     */
    private static function readDataset(): ?array
    {
        $jsonPath = resource_path('js/data/philippine-addresses.json');

        if (! is_file($jsonPath)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($jsonPath) ?: '', true);

        return is_array($decoded) ? $decoded : null;
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

    public function resolve(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::load()['codeToName'][$value] ?? $value;
    }

    public function format(?string $street, ?string $barangay, ?string $municipality, ?string $province, ?string $region): string
    {
        $parts = array_filter([
            $street ?? '',
            $this->resolve($barangay),
            $this->resolve($municipality),
            $this->resolve($province),
            $this->resolve($region),
        ]);

        return implode(', ', $parts);
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

        // Find region by name. Display names have the form
        // "Region VII (Central Visayas)" while stored and resubmitted values
        // may keep the full name, the parenthesized short form
        // ("Central Visayas"), the "Region VII" prefix, or the bare numeral
        // ("VII") — compare the label sets both sides can produce. Tightening
        // this to codes only would break those name resubmits.
        $regionForms = static function (string $name): array {
            $name = trim($name);

            if (preg_match('/^Region\s+(.+?)\s*\((.+)\)$/i', $name, $matches)) {
                return [
                    strtolower($name),
                    strtolower('Region '.$matches[1]),
                    strtolower($matches[1]),
                    strtolower($matches[2]),
                ];
            }

            return [strtolower($name)];
        };

        $targetForms = $regionForms($address['region']);

        $regionCode = null;
        foreach ($data['regions'] ?? [] as $region) {
            if (array_intersect($targetForms, $regionForms($region['name'])) !== []) {
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
