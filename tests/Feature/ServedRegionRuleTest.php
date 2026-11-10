<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\User;
use App\Rules\ServedRegion;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServedRegionRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('object-storage');
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    private function passes(string $region): bool
    {
        return Validator::make(
            ['region' => $region],
            ['region' => ['required', new ServedRegion]]
        )->passes();
    }

    #[Test]
    public function accepts_served_psgc_codes(): void
    {
        $this->assertTrue($this->passes('0700000000'));
        $this->assertTrue($this->passes('1800000000'));
    }

    #[Test]
    public function accepts_served_region_display_names(): void
    {
        $this->assertTrue($this->passes('Region VII (Central Visayas)'));
        $this->assertTrue($this->passes('Central Visayas'));
        $this->assertTrue($this->passes('Negros Island Region'));
    }

    #[Test]
    public function accepts_plain_labels_of_served_regions(): void
    {
        $this->assertTrue($this->passes('Region VII'));
        $this->assertTrue($this->passes('VII'));
        $this->assertTrue($this->passes('region vii'));
    }

    #[Test]
    public function rejects_regions_outside_served_scope(): void
    {
        $this->assertFalse($this->passes('NCR'));
        $this->assertFalse($this->passes('1300000000'));
        $this->assertFalse($this->passes('Region VI (Western Visayas)'));
        $this->assertFalse($this->passes('Region IV-A (CALABARZON)'));
    }

    #[Test]
    public function is_disabled_when_served_regions_are_not_configured(): void
    {
        config(['addresses.served_regions' => []]);

        $this->assertTrue($this->passes('NCR'));
        $this->assertTrue($this->passes('Region VI (Western Visayas)'));
    }

    #[Test]
    public function lets_nullable_fields_pass_when_blank(): void
    {
        $validator = Validator::make(
            ['region' => null],
            ['region' => ['nullable', new ServedRegion]]
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function filing_rejects_region_outside_served_scope(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($user)
            ->postJson(route('cases.store'), $this->validFilingData('NCR'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('address_region');
    }

    #[Test]
    public function filing_accepts_served_region(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAsWithMfa($user)
            ->postJson(route('cases.store'), $this->validFilingData('0700000000'));

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', ['status' => CaseFile::STATUS_PENDING_REVIEW]);
    }

    private function validFilingData(string $region): array
    {
        return [
            'client_type' => 'OFW',
            'intake_date' => now()->toDateString(),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-01-15',
            'sex' => 'MALE',
            'email' => 'region.validation@example.test',
            'contact_number' => '+639171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => $region,
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'employment_employer_name' => 'Gulf Construction Co.',
            'employment_country' => 'Saudi Arabia',
            'employment_start_date' => '2020-03-01',
            'employment_last_country' => 'Saudi Arabia',
            'employment_last_position' => 'Welder',
            'employment_date_of_arrival' => '2020-03-01',
            'summary' => 'Region validation filing test.',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'consent_method' => 'WRITTEN',
            'consent_evidence' => 'Signed form on file',
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }
}
