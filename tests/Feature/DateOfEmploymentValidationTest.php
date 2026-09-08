<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Employment dates must stay in chronological order, never point to the
 * future, and must not push the worker below the minimum working age of 15:
 * start_date is on/after DOB + 15 years and on/before today, and end_date
 * (when present) is on/after start_date and on/before today.
 *
 * These rules live in the case/intake FormRequests (POST /cases, intake
 * submit, cases.save-draft), so they are exercised through the HTTP layer for
 * both the publish (is_draft=false) and the draft-save paths.
 */
class DateOfEmploymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CaseCategory $category;

    private CaseIssue $caseIssue;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);

        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->category = CaseCategory::factory()->create(['is_active' => true]);
        $this->caseIssue = CaseIssue::create([
            'name' => 'Unpaid wages',
            'is_active' => true,
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

        $this->actingAs($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, $this->dayAfter($start)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');
    }

    #[Test]
    public function publish_rejects_start_before_working_age(): void
    {
        $dob = $this->dateOfBirth();
        $start = now()->parse($dob)->addYears(14)->toDateString();

        $this->actingAs($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, $start))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');
    }

    #[Test]
    public function publish_accepts_start_at_working_age(): void
    {
        $start = now()->parse($this->dateOfBirth())->addYears(15)->toDateString();

        $response = $this->actingAs($this->user)->post(route('cases.store'), $this->validPublishData($start, $start));

        $case = CaseFile::where('status', 'OPEN')->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_rejects_a_span_that_crosses_working_age(): void
    {
        // A 21-year-old reporting a 10-year span would have started at age 11.
        $dob = now()->subYears(21)->toDateString();
        $data = array_replace_recursive($this->validPublishData(now()->subYears(10)->toDateString(), now()->toDateString()), [
            'client' => ['date_of_birth' => $dob],
        ]);

        $this->actingAs($this->user)
            ->postJson(route('cases.store'), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');
    }

    #[Test]
    public function publish_accepts_the_maximum_span_for_a_young_worker(): void
    {
        // The same 21-year-old may report up to a 6-year span (start at 15).
        $dob = now()->subYears(21)->toDateString();
        $data = array_replace_recursive($this->validPublishData(now()->parse($dob)->addYears(15)->toDateString(), now()->toDateString()), [
            'client' => ['date_of_birth' => $dob],
        ]);

        $response = $this->actingAs($this->user)->post(route('cases.store'), $data);

        $case = CaseFile::where('status', 'OPEN')->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_accepts_start_today(): void
    {
        $today = now()->toDateString();

        $response = $this->actingAs($this->user)->post(route('cases.store'), $this->validPublishData($today, $today));

        $case = CaseFile::where('status', 'OPEN')->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_rejects_future_start(): void
    {
        $start = now()->addDay()->toDateString();

        $this->actingAs($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, $start))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');
    }

    #[Test]
    public function publish_rejects_end_before_start(): void
    {
        $start = '2020-01-01';

        $this->actingAs($this->user)
            ->postJson(route('cases.store'), $this->validPublishData($start, '2019-12-31'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.end_date');
    }

    #[Test]
    public function publish_accepts_end_today(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cases.store'), $this->validPublishData('2020-01-01', now()->toDateString()));

        $case = CaseFile::where('status', 'OPEN')->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function publish_rejects_future_end(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('cases.store'), $this->validPublishData('2020-01-01', now()->addDay()->toDateString()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.end_date');
    }

    #[Test]
    public function publish_accepts_present_employment_without_end(): void
    {
        $data = array_replace_recursive($this->validPublishData('2020-01-01', now()->toDateString()), [
            'employment' => ['is_present' => true, 'end_date' => null],
        ]);

        $response = $this->actingAs($this->user)->post(route('cases.store'), $data);

        $case = CaseFile::where('status', 'OPEN')->firstOrFail();
        $response->assertRedirect(route('cases.show', $case));
    }

    #[Test]
    public function draft_save_accepts_valid_dates(): void
    {
        $case = $this->makeDraft();

        $this->actingAs($this->user)
            ->put(route('cases.save-draft', $case->id), [
                'client_type' => 'OFW',
                'client' => ['date_of_birth' => $this->dateOfBirth()],
                'employment' => ['start_date' => '2020-01-01', 'end_date' => now()->toDateString()],
            ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    #[Test]
    public function draft_save_rejects_future_dates(): void
    {
        $case = $this->makeDraft();

        // Future end date fails even when every other draft field is relaxed.
        $this->actingAs($this->user)
            ->put(route('cases.save-draft', $case->id), [
                'client_type' => 'OFW',
                'client' => ['date_of_birth' => $this->dateOfBirth()],
                'employment' => ['start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString()],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.end_date');

        // Future start date fails on its own rule too.
        $this->actingAs($this->user)
            ->put(route('cases.save-draft', $case->id), [
                'client_type' => 'OFW',
                'client' => ['date_of_birth' => $this->dateOfBirth()],
                'employment' => ['start_date' => now()->addDay()->toDateString(), 'end_date' => null],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');
    }

    #[Test]
    public function draft_save_rejects_start_before_working_age(): void
    {
        $case = $this->makeDraft();
        $dob = $this->dateOfBirth();
        $floor = now()->parse($dob)->addYears(15)->toDateString();

        $this->actingAs($this->user)
            ->put(route('cases.save-draft', $case->id), [
                'client_type' => 'OFW',
                'client' => ['date_of_birth' => $dob],
                'employment' => ['start_date' => now()->parse($dob)->addYears(14)->toDateString(), 'end_date' => null],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');

        // On the working-age boundary itself the draft save succeeds.
        $this->actingAs($this->user)
            ->put(route('cases.save-draft', $case->id), [
                'client_type' => 'OFW',
                'client' => ['date_of_birth' => $dob],
                'employment' => ['start_date' => $floor, 'end_date' => null],
            ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    #[Test]
    public function intake_rejects_a_span_that_crosses_working_age(): void
    {
        $this->withSession(['intake_verified_email' => 'young-span@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData())
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment.start_date');
    }

    #[Test]
    public function draft_save_accepts_missing_employment(): void
    {
        $case = $this->makeDraft();

        $this->actingAs($this->user)
            ->put(route('cases.save-draft', $case->id), [
                'client_type' => 'OFW',
            ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    private function makeDraft(): CaseFile
    {
        return CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
        ]);
    }

    /**
     * A complete self-filed intake payload for a 21-year-old reporting a
     * 10-year employment span (started at age 11 — below working age).
     */
    private function validIntakeData(): array
    {
        return [
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_name' => 'Mendoza',
                'suffix' => null,
                'date_of_birth' => now()->subYears(21)->toDateString(),
                'sex' => 'MALE',
                'contact_number' => '+639171234567',
            ],
            'address' => [
                'region' => '0700000000',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => '123 Main Street',
            ],
            'employment' => [
                'employer_name' => 'Gulf Construction Co.',
                'position' => 'Welder',
                'country' => 'Saudi Arabia',
                'start_date' => now()->subYears(10)->toDateString(),
                'end_date' => null,
                'is_present' => true,
            ],
            'summary' => 'I need help with unpaid wages from my employer for the past 3 months.',
            'consent' => true,
        ];
    }

    private function dayAfter(string $date): string
    {
        return now()->parse($date)->addDay()->toDateString();
    }

    /**
     * A complete, publishable OFW payload. callers override $start/$end, or
     * merge extra fields (e.g. is_present) through array_replace_recursive.
     */
    private function validPublishData(string $start, string $end): array
    {
        return [
            'client_type' => 'OFW',
            'category_id' => $this->category->id,
            'case_issue_id' => $this->caseIssue->id,
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'date_of_birth' => $this->dateOfBirth(),
                'sex' => 'MALE',
                'email' => 'employment.test@example.com',
                'contact_number' => '09171234567',
            ],
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Barangay 1',
            ],
            'employment' => [
                'employer_name' => 'ACME Corp',
                'last_country' => 'UAE',
                'last_position' => 'Engineer',
                'date_of_arrival' => '2024-01-15',
                'start_date' => $start,
                'end_date' => $end,
                'is_present' => false,
            ],
            'next_of_kin' => [],
            'vulnerability_indicator' => 'None',
            'summary' => 'Employment date validation test',
            'is_draft' => false,
            'consent' => true,
        ];
    }
}
