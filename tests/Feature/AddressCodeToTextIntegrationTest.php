<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IntakeDocumentService;
use App\Services\PhilippineAddressService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressCodeToTextIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private PhilippineAddressService $addressService;

    /** @var array<string, string> Real codes from resources/js/data/philippine-addresses.json for Region VII */
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

    public function test_case_intake_resolves_address_codes_to_case_owned_names(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $data = CaseFile::factory()->withIntake()->make()->only(CaseFile::INTAKE_FIELDS);
        $data['address_street'] = '123 Main St';
        $case = app(CaseService::class)->createCase(array_merge($data, [
            'client_type' => 'OFW',
            'address_region' => self::REGION_CODE,
            'address_province' => self::PROVINCE_CODE,
            'address_city_municipality' => self::CITY_CODE,
            'address_barangay' => self::BARANGAY_CODE,
            'consent_method' => 'WRITTEN',
            'consent_evidence' => 'Signed consent retained',
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'missing_document_reasons' => array_fill_keys(['passport_front_page', 'arrival_stamp', 'employment_evidence', 'sworn_affidavit'], 'Awaiting copy'),
        ]), $user->id);

        $this->assertSame(self::REGION_NAME, $case->address_region);
        $this->assertSame(self::PROVINCE_NAME, $case->address_province);
        $this->assertSame(self::CITY_NAME, $case->address_city_municipality);
        $this->assertSame(self::BARANGAY_NAME, $case->address_barangay);
        $this->assertSame('123 Main St', $case->address_street);
        $this->assertNull($case->client_id);
    }

    public function test_case_address_names_resolve_back_to_psgc_codes(): void
    {
        $codes = $this->addressService->resolveAddressToCodes([
            'region' => self::REGION_NAME,
            'province' => self::PROVINCE_NAME,
            'city_municipality' => self::CITY_NAME,
            'barangay' => self::BARANGAY_NAME,
        ]);

        $this->assertSame(self::REGION_CODE, $codes['region']);
        $this->assertSame(self::PROVINCE_CODE, $codes['province']);
        $this->assertSame(self::CITY_CODE, $codes['city_municipality']);
        $this->assertSame(self::BARANGAY_CODE, $codes['barangay']);
    }

    public function test_reports_still_aggregate_correctly(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory()->withIntake()->create([
            'user_id' => $user->id,
            'status' => 'OPEN',
            'address_region' => self::REGION_NAME,
            'address_province' => self::PROVINCE_NAME,
            'address_city_municipality' => self::CITY_NAME,
        ]);
        CaseFile::factory()->withIntake()->create([
            'user_id' => $user->id,
            'status' => 'OPEN',
            'address_region' => self::REGION_NAME,
            'address_province' => self::PROVINCE_NAME,
            'address_city_municipality' => self::CITY_NAME,
        ]);

        $reportService = app(ReportsService::class);
        $distribution = $reportService->getGeographicDistribution($user->id, UserRole::CASE_MANAGER->value);

        $this->assertArrayHasKey('labels', $distribution);
        $this->assertArrayHasKey('data', $distribution);
        $this->assertContains(self::PROVINCE_NAME, $distribution['labels']);

        $cebuIndex = array_search(self::PROVINCE_NAME, $distribution['labels']);
        $this->assertEquals(2, $distribution['data'][$cebuIndex]);
    }

    // insights_still_aggregate_correctly removed — InsightsService does not exist in the codebase
}
