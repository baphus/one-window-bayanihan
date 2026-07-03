<?php

namespace App\Services;

class AddressNameResolver
{
    private static ?array $nameByCode = null;

    public function resolve(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::nameByCode()[$value] ?? $value;
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

    private static function nameByCode(): array
    {
        if (self::$nameByCode !== null) {
            return self::$nameByCode;
        }

        $path = resource_path('js/data/philippine-addresses.ts');
        if (! is_file($path)) {
            return self::$nameByCode = [];
        }

        $source = file_get_contents($path) ?: '';
        preg_match_all('/"code"\s*:\s*"(\d{10})"\s*,\s*"name"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $source, $matches, PREG_SET_ORDER);

        $names = [];
        foreach ($matches as $match) {
            $names[$match[1]] = stripcslashes($match[2]);
        }

        return self::$nameByCode = $names;
    }
}
