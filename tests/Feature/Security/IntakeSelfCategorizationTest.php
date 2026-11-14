<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Public intake must not attach referral classification or staff review fields. */
class IntakeSelfCategorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('object-storage');
    }

    private function validPayload(): array
    {
        return [
            'client_type' => 'OFW',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-01-15',
            'sex' => 'MALE',
            'email' => 'ofw@example.test',
            'contact_number' => '09171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'summary' => 'Please help me.',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent' => true,
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }

    public function test_intake_rejects_caller_supplied_referral_categories(): void
    {
        $category = CaseCategory::factory()->create(['is_active' => true]);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email_verified_at' => now()]);

        $this->actingAsWithMfa($ofw)->postJson(route('ofw.intake.store'), array_merge($this->validPayload(), [
            'category_id' => $category->id,
            'category_ids' => [$category->id],
        ]))->assertStatus(422)->assertJsonValidationErrors(['category_id', 'category_ids']);

        $this->assertSame(0, CaseFile::count());
    }

    public function test_intake_rejects_caller_supplied_case_issue(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email_verified_at' => now()]);

        $this->actingAsWithMfa($ofw)->postJson(route('ofw.intake.store'), array_merge($this->validPayload(), [
            'case_issue_id' => fake()->uuid(),
        ]))->assertStatus(422)->assertJsonValidationErrors('case_issue_id');

        $this->assertSame(0, CaseFile::count());
    }
}
