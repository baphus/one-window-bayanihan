<?php

namespace Tests\Feature;

use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhilippineAddressServiceTest extends TestCase
{
    use RefreshDatabase;

    private PhilippineAddressService $service;

    /** @var string Region VII code from philippine-addresses.ts */
    private const REGION = '0700000000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PhilippineAddressService::class);
    }

    public function test_resolve_address_to_codes_resolves_hierarchically(): void
    {
        $result = $this->service->resolveAddressToCodes([
            'region' => 'Region VII (Central Visayas)',
            'province' => 'Cebu',
            'city_municipality' => 'Argao',
            'barangay' => 'Alambijud',
        ]);

        $this->assertSame('0700000000', $result['region']);
        $this->assertSame('0702200000', $result['province']);
        $this->assertSame('0702205000', $result['city_municipality']);
        $this->assertSame('0702205001', $result['barangay']);
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
        $result = $this->service->resolveAddressToCodes([
            'region' => 'region vii (central visayas)',
        ]);

        $this->assertSame('0700000000', $result['region']);
        $this->assertNull($result['province']);
        $this->assertNull($result['city_municipality']);
        $this->assertNull($result['barangay']);
    }

    public function test_resolve_address_to_codes_resolves_partial(): void
    {
        // Partial address (region + province only) returns codes for those, null for rest
        $result = $this->service->resolveAddressToCodes([
            'region' => 'Region VII (Central Visayas)',
            'province' => 'Cebu',
        ]);

        $this->assertSame('0700000000', $result['region']);
        $this->assertSame('0702200000', $result['province']);
        $this->assertNull($result['city_municipality']);
        $this->assertNull($result['barangay']);
    }
}
