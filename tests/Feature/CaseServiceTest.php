<?php

namespace Tests\Feature;

use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_tracker_number_format(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'generateTrackerNumber');
        $reflection->setAccessible(true);
        $number = $reflection->invoke($service);
        $this->assertMatchesRegularExpression('/^OWBAP-[A-Z0-9]{7}$/', $number);
    }

    public function test_generate_tracker_number_unique(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'generateTrackerNumber');
        $reflection->setAccessible(true);
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $reflection->invoke($service);
        }
        $this->assertCount(10, array_unique($numbers));
    }

    // ─── Address code-to-name resolution tests ─────────────────────────────

    public function test_normalize_nok_data_resolves_codes_to_names(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'normalizeNokData');
        $reflection->setAccessible(true);

        // Test flat format (codes at the top level)
        $flatData = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'relationship' => 'Spouse',
            'region' => '0700000000',
            'province' => '0702200000',
            'city_municipality' => '0702205000',
            'barangay' => '0702205001',
            'full_address' => '123 Main St, Argao',
        ];

        $result = $reflection->invoke($service, $flatData);
        $this->assertEquals('Region VII (Central Visayas)', $result['region']);
        $this->assertEquals('Cebu', $result['province']);
        $this->assertEquals('Argao', $result['city_municipality']);
        $this->assertEquals('Alambijud', $result['barangay']);
        $this->assertEquals('123 Main St, Argao', $result['full_address']);

        // Test nested format (codes in nok_address sub-array)
        $nestedData = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'relationship' => 'Sibling',
            'nok_address' => [
                'region' => '0700000000',
                'province' => '0702200000',
                'city_municipality' => '0702205000',
                'barangay' => '0702205001',
            ],
            'full_address' => '456 Oak Ave, Alambijud',
        ];

        $result = $reflection->invoke($service, $nestedData);
        $this->assertEquals('Region VII (Central Visayas)', $result['region']);
        $this->assertEquals('Cebu', $result['province']);
        $this->assertEquals('Argao', $result['city_municipality']);
        $this->assertEquals('Alambijud', $result['barangay']);
        $this->assertEquals('456 Oak Ave, Alambijud', $result['full_address']);
    }
}
