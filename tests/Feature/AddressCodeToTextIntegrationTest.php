<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
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

    /** @var array<string, string> Real codes from resources/js/data/philippine-addresses.ts for Region VII */
    private const REGION_CODE = '0700000000';

    private const REGION_NAME = 'Region VII (Central Visayas)';

    private const PROVINCE_CODE = '0702200000';

    private const PROVINCE_NAME = 'Cebu';

    private const CITY_CODE = '0702205000';

    private const CITY_NAME = 'Argao';

    private const BARANGAY_CODE = '0702205001';

    private const BARANGAY_NAME = 'Alambijud';

    protected function setUp(): void
    {
        parent::setUp();
        $this->addressService = app(PhilippineAddressService::class);
    }

    public function test_full_flow_create_client_with_address(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();

        $service = app(CaseService::class);
        $service->createCase([
            'client_type' => 'OFW',
            'client' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'selected_client_id' => $client->id,
            'address' => [
                'region' => self::REGION_CODE,
                'province' => self::PROVINCE_CODE,
                'city_municipality' => self::CITY_CODE,
                'barangay' => self::BARANGAY_CODE,
                'street' => '123 Main St',
            ],
        ], $user->id);

        $address = $client->addresses()->first();

        $this->assertNotNull($address);
        $this->assertEquals(self::REGION_NAME, $address->region);
        $this->assertEquals(self::PROVINCE_NAME, $address->province);
        $this->assertEquals(self::CITY_NAME, $address->city_municipality);
        $this->assertEquals(self::BARANGAY_NAME, $address->barangay);
        $this->assertEquals('123 Main St', $address->street);
    }

    public function test_edit_existing_client_address(): void
    {
        $client = Client::factory()->create();
        $address = ClientAddress::create([
            'client_id' => $client->id,
            'region' => self::REGION_NAME,
            'province' => self::PROVINCE_NAME,
            'city_municipality' => self::CITY_NAME,
            'barangay' => self::BARANGAY_NAME,
            'street' => '456 Oak Ave',
        ]);

        $codes = $this->addressService->resolveAddressToCodes([
            'region' => self::REGION_NAME,
            'province' => self::PROVINCE_NAME,
            'city_municipality' => self::CITY_NAME,
            'barangay' => self::BARANGAY_NAME,
        ]);

        $this->assertEquals(self::REGION_CODE, $codes['region']);
        $this->assertEquals(self::PROVINCE_CODE, $codes['province']);
        $this->assertEquals(self::CITY_CODE, $codes['city_municipality']);
        $this->assertEquals(self::BARANGAY_CODE, $codes['barangay']);

        // Verify the DB still holds text names after the code resolution
        $this->assertDatabaseHas('client_addresses', [
            'id' => $address->id,
            'region' => self::REGION_NAME,
            'province' => self::PROVINCE_NAME,
        ]);
    }

    public function test_reports_still_aggregate_correctly(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        ClientAddress::create([
            'client_id' => $clientA->id,
            'region' => self::REGION_NAME,
            'province' => self::PROVINCE_NAME,
            'city_municipality' => self::CITY_NAME,
        ]);
        ClientAddress::create([
            'client_id' => $clientB->id,
            'region' => self::REGION_NAME,
            'province' => self::PROVINCE_NAME,
            'city_municipality' => self::CITY_NAME,
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
        $this->assertContains(self::PROVINCE_NAME, $distribution['labels']);

        $cebuIndex = array_search(self::PROVINCE_NAME, $distribution['labels']);
        $this->assertEquals(2, $distribution['data'][$cebuIndex]);
    }

    // insights_still_aggregate_correctly removed — InsightsService does not exist in the codebase
}
