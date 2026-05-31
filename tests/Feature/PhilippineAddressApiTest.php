<?php

namespace Tests\Feature;

use App\Models\PhilippineAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    private function seedRegions(): void
    {
        PhilippineAddress::insert([
            ['type' => 'region', 'code' => '130000000', 'name' => 'Region X', 'parent_code' => null],
            ['type' => 'region', 'code' => '140000000', 'name' => 'Region XI', 'parent_code' => null],
        ]);
    }

    private function seedProvinces(): void
    {
        PhilippineAddress::insert([
            ['type' => 'province', 'code' => 'P001', 'name' => 'Province A', 'parent_code' => '130000000'],
            ['type' => 'province', 'code' => 'P002', 'name' => 'Province B', 'parent_code' => '130000000'],
        ]);
    }

    private function seedCities(): void
    {
        PhilippineAddress::insert([
            ['type' => 'city', 'code' => 'C001', 'name' => 'City A', 'parent_code' => 'P001'],
            ['type' => 'municipality', 'code' => 'M001', 'name' => 'Municipality A', 'parent_code' => 'P001'],
        ]);
    }

    private function seedBarangays(): void
    {
        PhilippineAddress::insert([
            ['type' => 'barangay', 'code' => 'B001', 'name' => 'Barangay A', 'parent_code' => 'C001'],
            ['type' => 'barangay', 'code' => 'B002', 'name' => 'Barangay B', 'parent_code' => 'C001'],
        ]);
    }

    public function test_regions_returns_all_regions(): void
    {
        $this->seedRegions();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/regions');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['code' => '130000000', 'name' => 'Region X'],
                ['code' => '140000000', 'name' => 'Region XI'],
            ]);
    }

    public function test_regions_returns_empty_when_no_data(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/regions');

        $response->assertOk()->assertJson([]);
    }

    public function test_provinces_filters_by_region(): void
    {
        $this->seedRegions();
        $this->seedProvinces();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/provinces?region=130000000');

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
        $this->seedRegions();
        $this->seedProvinces();
        $this->seedCities();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/cities?province=P001');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['code' => 'C001', 'name' => 'City A'],
                ['code' => 'M001', 'name' => 'Municipality A'],
            ]);
    }

    public function test_barangays_filters_by_parent(): void
    {
        $this->seedRegions();
        $this->seedProvinces();
        $this->seedCities();
        $this->seedBarangays();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=C001');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['code' => 'B001', 'name' => 'Barangay A'],
                ['code' => 'B002', 'name' => 'Barangay B'],
            ]);
    }

    public function test_unauthenticated_requests_return_regions(): void
    {
        PhilippineAddress::insert([
            ['type' => 'region', 'code' => '130000000', 'name' => 'Region X', 'parent_code' => null],
        ]);

        $response = $this->getJson('/api/address/regions');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([['code' => '130000000', 'name' => 'Region X']]);
    }

    public function test_barangays_returns_empty_for_nonexistent_parent(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/address/barangays?city=NONEXISTENT');

        $response->assertOk()->assertJson([]);
    }
}
