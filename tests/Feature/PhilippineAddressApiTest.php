<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhilippineAddressApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'CASE_MANAGER']);

        Cache::flush();
    }

    public function test_regions_returns_data_from_psgc_api(): void
    {
        Http::fake([
            'https://psgc.cloud/api/regions' => Http::response([
                ['code' => '130000000', 'name' => 'Region X'],
                ['code' => '140000000', 'name' => 'Region XI'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/regions');

        $response->assertOk()
            ->assertJson([
                ['code' => '130000000', 'name' => 'Region X'],
                ['code' => '140000000', 'name' => 'Region XI'],
            ]);
    }

    public function test_regions_returns_empty_on_api_failure(): void
    {
        Http::fake([
            'https://psgc.cloud/api/regions' => Http::response(null, 500),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/regions');

        $response->assertOk()->assertJson([]);
    }

    public function test_regions_returns_empty_on_timeout(): void
    {
        Http::fake([
            'https://psgc.cloud/api/regions' => function () {
                throw new ConnectionException('Timeout');
            },
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/regions');

        $response->assertOk()->assertJson([]);
    }

    public function test_regions_are_cached(): void
    {
        Http::fake([
            'https://psgc.cloud/api/regions' => Http::response([
                ['code' => '130000000', 'name' => 'Region X'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        // First call — hits the API
        $this->actingAs($user)->getJson('/api/address/regions');

        // Second call — should use cache, not hit API again
        Http::assertSentCount(1);

        $this->actingAs($user)->getJson('/api/address/regions');

        // Still only 1 API call — cached
        Http::assertSentCount(1);
    }

    public function test_provinces_filters_by_region(): void
    {
        Http::fake([
            'https://psgc.cloud/api/regions/R01/provinces' => Http::response([
                ['code' => 'P001', 'name' => 'Province A'],
                ['code' => 'P002', 'name' => 'Province B'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces?region=R01');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['code' => 'P001', 'name' => 'Province A'],
                ['code' => 'P002', 'name' => 'Province B'],
            ]);
    }

    public function test_provinces_returns_empty_without_region_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces');

        $response->assertOk()
            ->assertJson([]);
    }

    public function test_cities_merges_cities_and_municipalities(): void
    {
        Http::fake([
            'https://psgc.cloud/api/provinces/P001/cities' => Http::response([
                ['code' => 'C001', 'name' => 'City A'],
            ]),
            'https://psgc.cloud/api/provinces/P001/municipalities' => Http::response([
                ['code' => 'C002', 'name' => 'Municipality A'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/cities?province=P001');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['code' => 'C001', 'name' => 'City A'],
                ['code' => 'C002', 'name' => 'Municipality A'],
            ]);
    }

    public function test_barangays_filters_by_city(): void
    {
        Http::fake([
            'https://psgc.cloud/api/cities/C001/barangays' => Http::response([
                ['code' => 'B001', 'name' => 'Barangay A'],
                ['code' => 'B002', 'name' => 'Barangay B'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=C001');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['code' => 'B001', 'name' => 'Barangay A'],
                ['code' => 'B002', 'name' => 'Barangay B'],
            ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/address/regions');

        $response->assertUnauthorized();
    }

    public function test_barangays_falls_back_to_municipality_endpoint(): void
    {
        Http::fake([
            'https://psgc.cloud/api/cities/M001/barangays' => Http::response(null, 404),
            'https://psgc.cloud/api/municipalities/M001/barangays' => Http::response([
                ['code' => 'B001', 'name' => 'Barangay A'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=M001');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([
                ['code' => 'B001', 'name' => 'Barangay A'],
            ]);
    }

    public function test_barangays_returns_empty_when_both_endpoints_fail(): void
    {
        Http::fake([
            'https://psgc.cloud/api/cities/X001/barangays' => Http::response(null, 404),
            'https://psgc.cloud/api/municipalities/X001/barangays' => Http::response(null, 500),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=X001');

        $response->assertOk()->assertJson([]);
    }
}
