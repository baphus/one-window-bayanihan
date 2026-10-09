<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\User;
use App\Rules\ServedRegion;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
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
    public function intake_submission_rejects_region_outside_served_scope(): void
    {
        $data = $this->validIntakeData('NCR');

        $response = $this->withSession(['intake_verified_email' => 'region-gate@example.com'])
            ->postJson('/intake/submit', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('address.region');
    }

    #[Test]
    public function intake_submission_accepts_served_region(): void
    {
        $response = $this->withSession(['intake_verified_email' => 'served-region@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData('0700000000'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function intake_submission_validates_next_of_kin_region(): void
    {
        $data = $this->validIntakeData('0700000000');
        $data['next_of_kin'] = [[
            'first_name' => 'Maria',
            'last_name' => 'Dela Cruz',
            'relationship' => 'Spouse',
            'phone_number' => '+639181234567',
            'region' => 'NCR',
        ]];

        $response = $this->withSession(['intake_verified_email' => 'nok-region@example.com'])
            ->postJson('/intake/submit', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('next_of_kin.0.region');
    }

    #[Test]
    public function draft_save_rejects_region_outside_served_scope(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
        ]);

        $this->actingAs($user)->put(route('cases.save-draft', $case->id), [
            'client_type' => 'OFW',
            'address' => ['region' => 'NCR'],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('address.region');

        $this->actingAs($user)->put(route('cases.save-draft', $case->id), [
            'client_type' => 'OFW',
            'address' => ['region' => '0700000000'],
        ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    private function validIntakeData(string $region): array
    {
        return [
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_name' => 'Mendoza',
                'suffix' => null,
                'date_of_birth' => '1990-01-15',
                'sex' => 'MALE',
                'contact_number' => '+639171234567',
            ],
            'address' => [
                'region' => $region,
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => '123 Main Street',
            ],
            'employment' => [
                'employer_name' => 'Gulf Construction Co.',
                'position' => 'Welder',
                'country' => 'Saudi Arabia',
                'start_date' => '2020-03-01',
                'end_date' => null,
                'is_present' => true,
            ],
            'summary' => 'I need help with unpaid wages from my employer for the past 3 months.',
            'consent' => true,
        ];
    }
}
