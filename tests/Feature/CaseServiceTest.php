<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IntakeDocumentService;
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
        // Crockford base32, 10 characters. See CaseNumberGenerator for why the
        // ambiguous glyphs I, L, O and U are excluded.
        $this->assertMatchesRegularExpression('/^OWBAP-[0-9A-HJKMNP-TV-Z]{10}$/', $number);
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

    public function test_create_case_resolves_address_codes_to_names(): void
    {
        $service = app(CaseService::class);
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $data = CaseFile::factory()->withIntake()->make()->only(CaseFile::INTAKE_FIELDS);
        $result = $service->createCase(array_merge($data, [
            'client_type' => 'OFW',
            'address_region_code' => '0700000000', 'address_province_code' => '0702200000',
            'consent' => true,
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'missing_document_reasons' => array_fill_keys(['passport_front_page', 'arrival_stamp', 'employment_evidence', 'sworn_affidavit'], 'Awaiting copy'),
        ]), $user->id);

        $this->assertEquals('Region VII (Central Visayas)', $result->address_region);
        $this->assertEquals('Cebu', $result->address_province);
        $this->assertNull($result->client_id);
    }
}
