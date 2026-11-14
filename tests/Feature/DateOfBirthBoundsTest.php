<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureMfaSession;
use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DOB bounds (15–100 years) live in StoreCaseRequest and are exercised
 * through the authed filing path (POST /cases → OPEN for staff filers).
 */
class DateOfBirthBoundsTest extends TestCase
{
    use RefreshDatabase;

    private User $filer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('object-storage');
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
            EnsureMfaSession::class,
        ]);

        $this->filer = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
    }

    #[Test]
    public function filing_accepts_an_applicant_who_is_exactly_15(): void
    {
        $response = $this->actingAsWithMfa($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(15)->toDateString()));

        $case = CaseFile::where('status', 'OPEN')->sole();
        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame(now()->subYears(15)->toDateString(), $case->date_of_birth->toDateString());
    }

    #[Test]
    public function filing_accepts_an_applicant_who_is_exactly_100(): void
    {
        $response = $this->actingAsWithMfa($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(100)->toDateString()));

        $response->assertRedirect();
    }

    #[Test]
    public function filing_rejects_an_applicant_younger_than_15(): void
    {
        $this->actingAsWithMfa($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(14)->toDateString()))
            ->assertJsonValidationErrors('date_of_birth');
    }

    #[Test]
    public function filing_rejects_an_applicant_older_than_100(): void
    {
        $this->actingAsWithMfa($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(101)->toDateString()))
            ->assertJsonValidationErrors('date_of_birth');
    }

    #[Test]
    public function filing_rejects_a_future_date_of_birth(): void
    {
        $this->actingAsWithMfa($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->addDay()->toDateString()))
            ->assertJsonValidationErrors('date_of_birth');
    }

    private function validFilingData(string $dob): array
    {

        return [
            'client_type' => 'OFW',
            'intake_date' => now()->toDateString(),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => $dob,
            'sex' => 'MALE',
            'email' => 'dob.bounds@example.test',
            'contact_number' => '+639171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'employment_employer_name' => 'Gulf Construction Co.',
            'employment_position' => 'Welder',
            'employment_country' => 'Saudi Arabia',
            'employment_date_of_arrival' => now()->toDateString(),
            'summary' => 'DOB bounds filing test.',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent' => true,
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }
}
