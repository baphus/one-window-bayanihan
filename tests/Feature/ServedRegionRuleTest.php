<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
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
    public function filing_rejects_region_outside_served_scope(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $client = Client::factory()->create();
        $category = CaseCategory::factory()->create(['is_active' => true]);
        $caseIssue = CaseIssue::create(['name' => 'Unpaid wages', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('cases.store'), $this->validFilingData('NCR', $client->id, $category->id, $caseIssue->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('address.region');
    }

    #[Test]
    public function filing_accepts_served_region(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $client = Client::factory()->create();
        $category = CaseCategory::factory()->create(['is_active' => true]);
        $caseIssue = CaseIssue::create(['name' => 'Unpaid wages', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->postJson(route('cases.store'), $this->validFilingData('0700000000', $client->id, $category->id, $caseIssue->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', ['status' => CaseFile::STATUS_PENDING_REVIEW]);
    }

    #[Test]
    public function filing_validates_next_of_kin_region(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $client = Client::factory()->create();
        $category = CaseCategory::factory()->create(['is_active' => true]);
        $caseIssue = CaseIssue::create(['name' => 'Unpaid wages', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('cases.store'), $this->validFilingData('0700000000', $client->id, $category->id, $caseIssue->id, [
                'first_name' => 'Maria',
                'last_name' => 'Dela Cruz',
                'relationship' => 'Spouse',
                'phone_number' => '+639181234567',
                'email' => 'nok@example.test',
                'nok_address' => ['region' => 'NCR', 'city_municipality' => 'Quezon City', 'barangay' => 'Baclaran'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('next_of_kin.0.nok_address.region');
    }

    private function validFilingData(string $region, string $clientId, string $categoryId, string $caseIssueId, ?array $noks = null): array
    {
        return [
            'selected_client_id' => $clientId,
            'category_ids' => [$categoryId],
            'case_issue_id' => $caseIssueId,
            'vulnerability_indicator' => 'None',
            'summary' => 'Region validation filing test.',
            'consent' => true,
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_name' => 'Mendoza',
                'suffix' => null,
                'date_of_birth' => '1990-01-15',
                'sex' => 'MALE',
                'email' => 'region.validation@example.test',
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
                'last_country' => 'Saudi Arabia',
                'last_position' => 'Welder',
                'date_of_arrival' => '2020-03-01',
            ],
            'next_of_kin' => $noks !== null ? [$noks] : [],
        ];
    }
}
