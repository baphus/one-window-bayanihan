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
            'https://psgc.cloud/api/provinces' => Http::response([
                ['code' => 'P001', 'name' => 'Province A', 'regionCode' => 'R01'],
                ['code' => 'P002', 'name' => 'Province B', 'regionCode' => 'R02'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces?region=R01');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([
                ['code' => 'P001', 'name' => 'Province A', 'regionCode' => 'R01'],
            ]);
    }

    public function test_provinces_returns_all_without_region_filter(): void
    {
        Http::fake([
            'https://psgc.cloud/api/provinces' => Http::response([
                ['code' => 'P001', 'name' => 'Province A', 'regionCode' => 'R01'],
                ['code' => 'P002', 'name' => 'Province B', 'regionCode' => 'R02'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces');

        $response->assertOk()
            ->assertJsonCount(2);
    }

    public function test_cities_filters_by_province(): void
    {
        Http::fake([
            'https://psgc.cloud/api/cities' => Http::response([
                ['code' => 'C001', 'name' => 'City A', 'type' => 'City', 'provinceCode' => 'P001'],
                ['code' => 'C002', 'name' => 'Municipality A', 'type' => 'Municipality', 'provinceCode' => 'P002'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/cities?province=P001');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([
                ['code' => 'C001', 'name' => 'City A', 'provinceCode' => 'P001'],
            ]);
    }

    public function test_barangays_filters_by_city(): void
    {
        Http::fake([
            'https://psgc.cloud/api/barangays' => Http::response([
                ['code' => 'B001', 'name' => 'Barangay A', 'cityCode' => 'C001'],
                ['code' => 'B002', 'name' => 'Barangay B', 'cityCode' => 'C002'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=C001');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([
                ['code' => 'B001', 'name' => 'Barangay A', 'cityCode' => 'C001'],
            ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/address/regions');

        $response->assertUnauthorized();
    }

    public function test_barangays_uses_municipality_code_fallback(): void
    {
        Http::fake([
            'https://psgc.cloud/api/barangays' => Http::response([
                ['code' => 'B001', 'name' => 'Barangay A', 'municipalityCode' => 'M001'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=M001');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([
                ['code' => 'B001', 'name' => 'Barangay A', 'cityCode' => 'M001'],
            ]);
    }
}
