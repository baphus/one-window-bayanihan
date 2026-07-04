<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\PhilippineAddress;
use App\Models\User;
use App\Services\CaseService;
use App\Services\PhilippineAddressService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressCodeToTextIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private PhilippineAddressService $addressService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addressService = app(PhilippineAddressService::class);
    }

    private function seedAddressHierarchy(): void
    {
        PhilippineAddress::insert([
            ['type' => 'region', 'code' => '07000000', 'name' => 'Central Visayas', 'parent_code' => null],
            ['type' => 'province', 'code' => '07100000', 'name' => 'Cebu', 'parent_code' => '07000000'],
            ['type' => 'city', 'code' => '07101000', 'name' => 'Cebu City', 'parent_code' => '07100000'],
            ['type' => 'barangay', 'code' => '07101001', 'name' => 'Barangay 1', 'parent_code' => '07101000'],
        ]);
    }

    public function test_full_flow_create_client_with_address(): void
    {
        $this->seedAddressHierarchy();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();

        $service = app(CaseService::class);
        $service->createCase([
            'client_type' => 'OFW',
            'client' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'selected_client_id' => $client->id,
            'address' => [
                'region' => '07000000',
                'province' => '07100000',
                'city_municipality' => '07101000',
                'barangay' => '07101001',
                'street' => '123 Main St',
            ],
        ], $user->id);

        $address = $client->addresses()->first();

        $this->assertNotNull($address);
        $this->assertEquals('Central Visayas', $address->region);
        $this->assertEquals('Cebu', $address->province);
        $this->assertEquals('Cebu City', $address->city_municipality);
        $this->assertEquals('Barangay 1', $address->barangay);
        $this->assertEquals('123 Main St', $address->street);
    }

    public function test_edit_existing_client_address(): void
    {
        $this->seedAddressHierarchy();

        $client = Client::factory()->create();
        $address = ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Central Visayas',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Barangay 1',
            'street' => '456 Oak Ave',
        ]);

        $codes = $this->addressService->resolveAddressToCodes([
            'region' => 'Central Visayas',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Barangay 1',
        ]);

        $this->assertEquals('07000000', $codes['region']);
        $this->assertEquals('07100000', $codes['province']);
        $this->assertEquals('07101000', $codes['city_municipality']);
        $this->assertEquals('07101001', $codes['barangay']);

        // Verify the DB still holds text names after the code resolution
        $this->assertDatabaseHas('client_addresses', [
            'id' => $address->id,
            'region' => 'Central Visayas',
            'province' => 'Cebu',
        ]);
    }

    public function test_reports_still_aggregate_correctly(): void
    {
        $this->seedAddressHierarchy();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        ClientAddress::create([
            'client_id' => $clientA->id,
            'region' => 'Central Visayas',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
        ]);
        ClientAddress::create([
            'client_id' => $clientB->id,
            'region' => 'Central Visayas',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
        ]);

        CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $clientA->id,
            'status' => 'OPEN',
        ]);
        CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $clientB->id,
            'status' => 'OPEN',
        ]);

        $reportService = app(ReportsService::class);
        $distribution = $reportService->getGeographicDistribution($user->id, 'CASE_MANAGER');

        $this->assertArrayHasKey('labels', $distribution);
        $this->assertArrayHasKey('data', $distribution);
        $this->assertContains('Cebu', $distribution['labels']);

        $cebuIndex = array_search('Cebu', $distribution['labels']);
        $this->assertEquals(2, $distribution['data'][$cebuIndex]);
    }

    // insights_still_aggregate_correctly removed — InsightsService does not exist in the codebase
}
