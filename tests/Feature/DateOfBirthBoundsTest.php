<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DOB bounds (15–100 years) live in StoreCaseRequest and are exercised
 * through the authed filing path (POST /cases → PENDING_REVIEW).
 */
class DateOfBirthBoundsTest extends TestCase
{
    use RefreshDatabase;

    private User $filer;

    private Client $filingClient;

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

        $this->filer = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->filingClient = Client::factory()->create();
        $this->category = CaseCategory::factory()->create(['is_active' => true]);
        $this->caseIssue = CaseIssue::create([
            'name' => 'Unpaid wages',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function filing_accepts_an_applicant_who_is_exactly_15(): void
    {
        $response = $this->actingAs($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(15)->toDateString()));

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', ['status' => CaseFile::STATUS_PENDING_REVIEW]);
    }

    #[Test]
    public function filing_accepts_an_applicant_who_is_exactly_100(): void
    {
        $response = $this->actingAs($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(100)->toDateString()));

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', ['status' => CaseFile::STATUS_PENDING_REVIEW]);
    }

    #[Test]
    public function filing_rejects_an_applicant_younger_than_15(): void
    {
        $this->actingAs($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(14)->toDateString()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('client.date_of_birth');
    }

    #[Test]
    public function filing_rejects_an_applicant_older_than_100(): void
    {
        $this->actingAs($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->subYears(101)->toDateString()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('client.date_of_birth');
    }

    #[Test]
    public function filing_rejects_a_future_date_of_birth(): void
    {
        $this->actingAs($this->filer)
            ->postJson(route('cases.store'), $this->validFilingData(now()->addDay()->toDateString()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('client.date_of_birth');
    }

    private function validFilingData(string $dob): array
    {
        // Employment starts at the earliest allowed working age relative to the
        // given DOB, so upstream employment-date checks never interfere with
        // this suite, which is scoped to DOB bounds only.
        $employStart = now()->parse($dob)->addYears(15)->toDateString();

        return [
            'selected_client_id' => $this->filingClient->id,
            'category_ids' => [$this->category->id],
            'case_issue_id' => $this->caseIssue->id,
            'vulnerability_indicator' => 'None',
            'summary' => 'DOB bounds filing test.',
            'consent' => true,
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_name' => 'Mendoza',
                'suffix' => null,
                'date_of_birth' => $dob,
                'sex' => 'MALE',
                'email' => 'dob.bounds@example.test',
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
                'start_date' => $employStart,
                'end_date' => null,
                'is_present' => true,
                'last_country' => 'Saudi Arabia',
                'last_position' => 'Welder',
                'date_of_arrival' => now()->parse($dob)->addYears(20)->toDateString(),
            ],
        ];
    }
}
