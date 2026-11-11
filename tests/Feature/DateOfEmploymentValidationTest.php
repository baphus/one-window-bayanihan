<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureMfaSession;
use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use App\Services\MfaPendingState;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DateOfEmploymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('object-storage');
        $this->withoutMiddleware([VerifyTurnstile::class, PreventRequestForgery::class, ThrottleRequests::class, EnsureMfaSession::class]);
        $this->user = User::factory()->mfaEnabled()->create(['role' => UserRole::CASE_MANAGER->value, 'mfa_secret' => 'JBSWY3DPEHPK3PXP']);
        $this->withSession([MfaPendingState::MARKER_KEY => [
            'user_id' => (string) $this->user->id,
            'credential_fingerprint' => hash('sha256', (string) $this->user->password),
        ]]);
    }

    #[Test]
    public function arrival_date_cannot_precede_date_of_birth_or_be_in_future(): void
    {
        foreach ([now()->subYears(31)->toDateString(), now()->addDay()->toDateString()] as $arrival) {
            $this->actingAsWithMfa($this->user)
                ->postJson(route('cases.store'), $this->validPublishData($arrival))
                ->assertStatus(422)
                ->assertJsonValidationErrors('employment_date_of_arrival');
        }
    }

    #[Test]
    public function publish_accepts_valid_arrival_date_and_does_not_persist_retired_employment_fields(): void
    {
        $response = $this->actingAsWithMfa($this->user)->post(route('cases.store'), $this->validPublishData('2024-01-15'));
        $case = CaseFile::where('status', 'OPEN')->firstOrFail();

        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame('2024-01-15', $case->fresh()->employment_date_of_arrival->toDateString());
        $this->assertArrayNotHasKey('employment_last_country', $case->getAttributes());
        $this->assertArrayNotHasKey('employment_start_date', $case->getAttributes());
        $this->assertArrayNotHasKey('employment_end_date', $case->getAttributes());
    }

    private function validPublishData(string $arrival): array
    {
        return [
            'client_type' => 'OFW',
            'intake_date' => now()->toDateString(),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'sex' => 'MALE',
            'email' => 'employment.test@example.com',
            'contact_number' => '09171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'employment_country' => 'UAE',
            'employment_last_position' => 'Engineer',
            'employment_date_of_arrival' => $arrival,
            'summary' => 'Employment date validation test',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent' => true,
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }
}
