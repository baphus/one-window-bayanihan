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

/**
 * Employment dates must stay in chronological order, never point to the
 * future, and must not push the worker below the minimum working age of 15:
 * start_date is on/after DOB + 15 years and on/before today, and end_date
 * (when present) is on/after start_date and on/before today.
 *
 * These rules live in StoreCaseRequest (POST /cases), so they are exercised
 * through the HTTP layer for the authed filing path (→ PENDING_REVIEW).
 */
class DateOfEmploymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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

        $this->user = User::factory()->mfaEnabled()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $this->withSession([
            MfaPendingState::MARKER_KEY => [
                'user_id' => (string) $this->user->id,
                'credential_fingerprint' => hash('sha256', (string) $this->user->password),
            ],
        ]);
    }

    private function dateOfBirth(): string
    {
        return now()->subYears(30)->toDateString();
    }

    #[Test]
    public function publish_rejects_start_before_date_of_birth(): void
    {
        $dob = $this->dateOfBirth();
        $start = now()->parse($dob)->subDay()->toDateString();

        $this->actingAsWithMfa($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, $this->dayAfter($start)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_start_date');
    }

    #[Test]
    public function publish_rejects_start_before_working_age(): void
    {
        $dob = $this->dateOfBirth();
        $start = now()->parse($dob)->addYears(14)->toDateString();

        $this->actingAsWithMfa($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, $start))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_start_date');
    }

    #[Test]
    public function publish_accepts_start_at_working_age(): void
    {
        $start = now()->parse($this->dateOfBirth())->addYears(15)->toDateString();

        $response = $this->actingAsWithMfa($this->user)->post(route('cases.store'), $this->validPublishData($start, $start));

        $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_rejects_a_span_that_crosses_working_age(): void
    {
        // A 21-year-old reporting a 10-year span would have started at age 11.
        $dob = now()->subYears(21)->toDateString();
        $data = array_replace($this->validPublishData(now()->subYears(10)->toDateString(), now()->toDateString()), [
            'date_of_birth' => $dob,
        ]);

        $this->actingAsWithMfa($this->user)
            ->postJson(route('cases.store'), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_start_date');
    }

    #[Test]
    public function publish_accepts_the_maximum_span_for_a_young_worker(): void
    {
        // The same 21-year-old may report up to a 6-year span (start at 15).
        $dob = now()->subYears(21)->toDateString();
        $data = array_replace($this->validPublishData(now()->parse($dob)->addYears(15)->toDateString(), now()->toDateString()), [
            'date_of_birth' => $dob,
        ]);

        $response = $this->actingAsWithMfa($this->user)->post(route('cases.store'), $data);

        $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_accepts_start_today(): void
    {
        $today = now()->toDateString();

        $response = $this->actingAsWithMfa($this->user)->post(route('cases.store'), $this->validPublishData($today, $today));

        $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_rejects_future_start(): void
    {
        $start = now()->addDay()->toDateString();

        $this->actingAsWithMfa($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, $start))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_start_date');
    }

    #[Test]
    public function publish_rejects_end_before_start(): void
    {
        $start = '2020-01-01';

        $this->actingAsWithMfa($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, '2019-12-31'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_end_date');
    }

    #[Test]
    public function publish_accepts_end_today(): void
    {
        $response = $this->actingAsWithMfa($this->user)
            ->post(route('cases.store'), $this->validPublishData('2020-01-01', now()->toDateString()));

        $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_rejects_future_end(): void
    {
        $this->actingAsWithMfa($this->user)
            ->postJson(route('cases.store'), $this->validPublishData('2020-01-01', now()->addDay()->toDateString()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_end_date');
    }

    #[Test]
    public function publish_accepts_present_employment_without_end(): void
    {
        $data = $this->validPublishData('2020-01-01', null);
        $data['employment_end_date'] = null;

        $response = $this->actingAsWithMfa($this->user)->post(route('cases.store'), $data);

        $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    private function dayAfter(string $date): string
    {
        return now()->parse($date)->addDay()->toDateString();
    }

    /**
     * A complete, publishable OFW payload. callers override $start/$end, or
     * merge extra fields through array_replace.
     */
    private function validPublishData(string $start, ?string $end): array
    {
        return [
            'client_type' => 'OFW',
            'intake_date' => now()->toDateString(),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => $this->dateOfBirth(),
            'sex' => 'MALE',
            'email' => 'employment.test@example.com',
            'contact_number' => '09171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'employment_employer_name' => 'ACME Corp',
            'employment_last_country' => 'UAE',
            'employment_last_position' => 'Engineer',
            'employment_country' => 'UAE',
            'employment_date_of_arrival' => '2024-01-15',
            'employment_start_date' => $start,
            'employment_end_date' => $end,
            'summary' => 'Employment date validation test',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'consent_method' => 'WRITTEN',
            'consent_evidence' => 'Signed consent form on file',
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }
}
