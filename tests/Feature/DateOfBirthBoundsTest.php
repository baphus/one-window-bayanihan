<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DateOfBirthBoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    #[Test]
    public function intake_accepts_an_applicant_who_is_exactly_15(): void
    {
        $response = $this->withSession(['intake_verified_email' => 'age-15@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData(now()->subYears(15)->toDateString()));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function intake_accepts_an_applicant_who_is_exactly_100(): void
    {
        $response = $this->withSession(['intake_verified_email' => 'age-100@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData(now()->subYears(100)->toDateString()));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function intake_rejects_an_applicant_younger_than_15(): void
    {
        $response = $this->withSession(['intake_verified_email' => 'age-14@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData(now()->subYears(14)->toDateString()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client.date_of_birth');
    }

    #[Test]
    public function intake_rejects_an_applicant_older_than_100(): void
    {
        $response = $this->withSession(['intake_verified_email' => 'age-101@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData(now()->subYears(101)->toDateString()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client.date_of_birth');
    }

    #[Test]
    public function intake_rejects_a_future_date_of_birth(): void
    {
        $response = $this->withSession(['intake_verified_email' => 'future-dob@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData(now()->addDay()->toDateString()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client.date_of_birth');
    }

    #[Test]
    public function draft_save_rejects_an_out_of_bounds_date_of_birth(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
        ]);

        $this->actingAs($user)->put(route('cases.save-draft', $case->id), [
            'client_type' => 'OFW',
            'client' => ['date_of_birth' => now()->subYears(14)->toDateString()],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client.date_of_birth');

        $this->actingAs($user)->put(route('cases.save-draft', $case->id), [
            'client_type' => 'OFW',
            'client' => ['date_of_birth' => now()->subYears(30)->toDateString()],
        ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    #[Test]
    public function draft_save_still_accepts_a_nullable_date_of_birth(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
        ]);

        $this->actingAs($user)->put(route('cases.save-draft', $case->id), [
            'client_type' => 'OFW',
        ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    private function validIntakeData(string $dob): array
    {
        // Employment starts at the earliest allowed working age relative to the
        // given DOB, so upstream employment-date checks never interfere with
        // this suite, which is scoped to DOB bounds only.
        $employStart = $this->calcEmployStart($dob);

        return [
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_name' => 'Mendoza',
                'suffix' => null,
                'date_of_birth' => $dob,
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
                'start_date' => $employStart,
                'end_date' => null,
                'is_present' => true,
            ],
            'summary' => 'I need help with unpaid wages from my employer for the past 3 months.',
            'consent' => true,
        ];
    }

    private function calcEmployStart(string $dob): string
    {
        return now()->parse($dob)->addYears(15)->toDateString();
    }
}
