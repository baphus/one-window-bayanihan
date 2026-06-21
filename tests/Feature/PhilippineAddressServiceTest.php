<?php

namespace Tests\Feature;

use App\Models\PhilippineAddress;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhilippineAddressServiceTest extends TestCase
{
    use RefreshDatabase;

    private PhilippineAddressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PhilippineAddressService::class);
    }

    public function test_resolve_address_to_codes_resolves_hierarchically(): void
    {
        PhilippineAddress::insert([
            ['type' => 'region', 'code' => 'R01', 'name' => 'Region A', 'parent_code' => null],
        ]);
        PhilippineAddress::insert([
            ['type' => 'province', 'code' => 'P01', 'name' => 'Province A1', 'parent_code' => 'R01'],
        ]);
        PhilippineAddress::insert([
            ['type' => 'city', 'code' => 'C01', 'name' => 'City A1', 'parent_code' => 'P01'],
        ]);
        PhilippineAddress::insert([
            ['type' => 'barangay', 'code' => 'B01', 'name' => 'Barangay A1', 'parent_code' => 'C01'],
        ]);

        $result = $this->service->resolveAddressToCodes([
            'region' => 'Region A',
            'province' => 'Province A1',
            'city_municipality' => 'City A1',
            'barangay' => 'Barangay A1',
        ]);

        $this->assertSame('R01', $result['region']);
        $this->assertSame('P01', $result['province']);
        $this->assertSame('C01', $result['city_municipality']);
        $this->assertSame('B01', $result['barangay']);
    }

    public function test_resolve_address_to_codes_returns_null_for_unresolvable(): void
    {
        $result = $this->service->resolveAddressToCodes([
            'region' => 'NonExistent Region',
        ]);

        $this->assertNull($result['region']);
        $this->assertNull($result['province']);
        $this->assertNull($result['city_municipality']);
        $this->assertNull($result['barangay']);
    }

    public function test_resolve_address_to_codes_is_case_insensitive(): void
    {
        PhilippineAddress::insert([
            ['type' => 'region', 'code' => 'R01', 'name' => 'Region A', 'parent_code' => null],
        ]);

        $result = $this->service->resolveAddressToCodes([
            'region' => 'region a',
        ]);

        $this->assertSame('R01', $result['region']);
        $this->assertNull($result['province']);
        $this->assertNull($result['city_municipality']);
        $this->assertNull($result['barangay']);
    }

    public function test_resolve_address_to_codes_disambiguates_duplicate_barangay(): void
    {
        // Regions
        PhilippineAddress::insert([
            ['type' => 'region', 'code' => 'R01', 'name' => 'Region A', 'parent_code' => null],
            ['type' => 'region', 'code' => 'R02', 'name' => 'Region B', 'parent_code' => null],
        ]);
        // Provinces
        PhilippineAddress::insert([
            ['type' => 'province', 'code' => 'P01', 'name' => 'Province A1', 'parent_code' => 'R01'],
            ['type' => 'province', 'code' => 'P02', 'name' => 'Province B1', 'parent_code' => 'R02'],
        ]);
        // Cities
        PhilippineAddress::insert([
            ['type' => 'city', 'code' => 'C01', 'name' => 'City A1', 'parent_code' => 'P01'],
            ['type' => 'city', 'code' => 'C02', 'name' => 'City B1', 'parent_code' => 'P02'],
        ]);
        // Barangays with duplicate name across different parents
        PhilippineAddress::insert([
            ['type' => 'barangay', 'code' => 'B01', 'name' => 'Poblacion', 'parent_code' => 'C01'],
            ['type' => 'barangay', 'code' => 'B02', 'name' => 'Poblacion', 'parent_code' => 'C02'],
        ]);

        $result = $this->service->resolveAddressToCodes([
            'region' => 'Region A',
            'province' => 'Province A1',
            'city_municipality' => 'City A1',
            'barangay' => 'Poblacion',
        ]);

        $this->assertSame('B01', $result['barangay'],
            'Should resolve to Poblacion under City A1 (B01), not the one under City B1 (B02)');
    }
}
