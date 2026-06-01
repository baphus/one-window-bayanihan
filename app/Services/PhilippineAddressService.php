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
}
