<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public NOK filing is removed: only case managers create NEXT_OF_KIN cases.
 * Public OFW submissions must reject NOK classification and relative fields.
 */
class NextOfKinFilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('object-storage');
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'client_type' => 'OFW',
            'intake_date' => now()->toDateString(),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-01-01',
            'sex' => 'MALE',
            'email' => 'ofw@example.test',
            'contact_number' => '+639170000000',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'employment_employer_name' => 'Employer',
            'employment_country' => 'UAE',
            'employment_last_position' => 'Worker',
            'employment_date_of_arrival' => '2021-02-01',
            'summary' => 'NOK filing test',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'consent_method' => 'WRITTEN',
            'consent_evidence' => 'Signed form on file',
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ], $overrides);
    }

    public function test_case_manager_creates_nok_case_with_affected_ofw_and_relative_fields(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAsWithMfa($manager)->postJson(route('cases.store'), $this->basePayload([
            'client_type' => 'NEXT_OF_KIN',
            'nok_name' => 'Maria Cruz',
            'nok_relationship' => 'Spouse',
            'nok_contact_number' => '+639180000001',
            'nok_email' => 'nok@example.test',
        ]));

        if ($response->status() === 422) {
            $this->fail('cases.store 422: '.json_encode($response->json('errors')));
        }
        $response->assertRedirect();

        $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->latest()->firstOrFail();
        $this->assertSame('NEXT_OF_KIN', $case->client_type);
        $this->assertSame('Maria Cruz', $case->nok_name);
        $this->assertSame('Spouse', $case->nok_relationship);
        $this->assertSame('+639180000001', $case->nok_contact_number);
        $this->assertSame('nok@example.test', $case->nok_email);
        $this->assertSame('Juan', $case->first_name);
    }

    public function test_nok_case_without_relative_fields_is_rejected(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($manager)
            ->postJson(route('cases.store'), $this->basePayload([
                'client_type' => 'NEXT_OF_KIN',
                'nok_name' => null,
                'nok_relationship' => null,
                'nok_contact_number' => null,
                'nok_email' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email']);
    }

    public function test_public_ofw_submission_rejects_nok_classification(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email_verified_at' => now()]);

        $this->actingAsWithMfa($ofw)
            ->postJson(route('ofw.intake.store'), $this->basePayload([
                'client_type' => 'NEXT_OF_KIN',
                'consent' => true,
                'consent_method' => null,
                'consent_evidence' => null,
                'nok_name' => 'Maria Cruz',
                'nok_relationship' => 'Spouse',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_type', 'nok_name', 'nok_relationship']);
    }

    public function test_public_ofw_submission_rejects_relative_fields_even_with_ofw_type(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email_verified_at' => now()]);

        $this->actingAsWithMfa($ofw)
            ->postJson(route('ofw.intake.store'), $this->basePayload([
                'consent' => true,
                'consent_method' => null,
                'consent_evidence' => null,
                'nok_name' => 'Maria Cruz',
                'nok_relationship' => 'Spouse',
                'nok_contact_number' => '+639180000001',
                'nok_email' => 'nok@example.test',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email']);
    }
}
