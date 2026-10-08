<?php

namespace App\Services;

class AddressNameResolver
{
    public function resolve(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return app(PhilippineAddressService::class)->resolveNames([$value])[$value] ?? $value;
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
}
