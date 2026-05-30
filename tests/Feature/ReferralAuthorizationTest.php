<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReferralAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'CASE_MANAGER']);
        Role::create(['name' => 'AGENCY_FOCAL_PERSON']);
    }

    public function test_agency_cross_agency_cannot_view_referral(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();

        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyA->id]);

        $case = CaseFile::factory()->create();
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agencyB->id,
        ]);

        $response = $this->actingAs($user)->get("/referrals/{$referral->id}");

        $response->assertStatus(403);
    }

    public function test_agency_own_agency_can_view_referral(): void
    {
        $agency = Agency::factory()->create();

        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $case = CaseFile::factory()->create();
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($user)->get("/referrals/{$referral->id}");

        $response->assertStatus(200);
    }

    public function test_case_manager_can_view_any_referral(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager->assignRole('CASE_MANAGER');

        $case = CaseFile::factory()->create();
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);

        $response = $this->actingAs($manager)->get("/referrals/{$referral->id}");

        $response->assertStatus(200);
    }

    public function test_admin_can_view_any_referral(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $case = CaseFile::factory()->create();
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);

        $response = $this->actingAs($admin)->get("/referrals/{$referral->id}");

        $response->assertStatus(200);
    }
}
