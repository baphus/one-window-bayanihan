<?php

namespace App\Services;

use App\Models\PhilippineAddress;

class PhilippineAddressService
{
    public function getRegions(): array
    {
        return PhilippineAddress::where('type', 'region')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->toArray();
    }

    public function getProvinces(?string $regionCode = null): array
    {
        if (! $regionCode) {
            return [];
        }

        return PhilippineAddress::where('type', 'province')
            ->where('parent_code', $regionCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->toArray();
    }

    public function getCities(?string $provinceCode = null): array
    {
        if (! $provinceCode) {
            return [];
        }

        return PhilippineAddress::whereIn('type', ['city', 'municipality'])
            ->where('parent_code', $provinceCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->toArray();
    }

    public function getBarangays(?string $parentCode = null): array
    {
        if (! $parentCode) {
            return [];
        }

        return PhilippineAddress::where('type', 'barangay')
            ->where('parent_code', $parentCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->toArray();
    }

    public function resolveNames(array $codes): array
    {
        return PhilippineAddress::whereIn('code', $codes)
            ->pluck('name', 'code')
            ->toArray();
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

        $region = PhilippineAddress::where('type', 'region')
            ->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$address['region']])
            ->first(['code']);

        if (! $region) {
            return $result;
        }

        $result['region'] = $region->code;

        if (empty($address['province'])) {
            return $result;
        }

        $province = PhilippineAddress::where('type', 'province')
            ->where('parent_code', $region->code)
            ->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$address['province']])
            ->first(['code']);

        if (! $province) {
            return $result;
        }

        $result['province'] = $province->code;

        if (empty($address['city_municipality'])) {
            return $result;
        }

        $city = PhilippineAddress::whereIn('type', ['city', 'municipality'])
            ->where('parent_code', $province->code)
            ->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$address['city_municipality']])
            ->first(['code']);

        if (! $city) {
            return $result;
        }

        $result['city_municipality'] = $city->code;

        if (empty($address['barangay'])) {
            return $result;
        }

        $barangay = PhilippineAddress::where('type', 'barangay')
            ->where('parent_code', $city->code)
            ->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$address['barangay']])
            ->first(['code']);

        if ($barangay) {
            $result['barangay'] = $barangay->code;
        }

        return $result;
    }
}
