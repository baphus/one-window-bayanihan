<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhilippineAddressApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_regions_returns_all_regions(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/regions');

        $response->assertOk();
        // Should contain at least the 17 regions from the TS file
        $response->assertJsonCount(17);
        $response->assertJsonFragment(['code' => '0700000000', 'name' => 'Region VII (Central Visayas)']);
    }

    public function test_provinces_filters_by_region(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces?region=0700000000');

        $response->assertOk();
        // Region VII has 4 provinces
        $response->assertJsonCount(4);
        $response->assertJsonFragment(['code' => '0702200000', 'name' => 'Cebu']);
    }

    public function test_provinces_returns_empty_without_region_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces');

        $response->assertOk()
            ->assertJson([]);
    }

    public function test_provinces_returns_empty_for_nonexistent_region(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces?region=NONEXISTENT');

        $response->assertOk()
            ->assertJson([]);
    }

    public function test_cities_merges_cities_and_municipalities(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/cities?province=0702200000');

        $response->assertOk();
        // Cebu province has many cities/municipalities
        $response->assertJsonCount(53);
        $response->assertJsonFragment(['code' => '0702205000', 'name' => 'Argao']);
    }

    public function test_barangays_filters_by_parent(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=0702205000');

        $response->assertOk();
        $response->assertJsonFragment(['code' => '0702205001', 'name' => 'Alambijud']);
    }

    public function test_unauthenticated_requests_return_regions(): void
    {
        $response = $this->getJson('/api/address/regions');

        $response->assertOk();
        $response->assertJsonCount(17);
        $response->assertJsonFragment(['code' => '0700000000', 'name' => 'Region VII (Central Visayas)']);
    }

    public function test_barangays_returns_empty_for_nonexistent_parent(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=NONEXISTENT');

        $response->assertOk()->assertJson([]);
    }
}
