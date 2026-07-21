<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function owner_case_manager_can_access_referral(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);

        $response = $this->actingAs($manager)->get("/referrals/{$referral->id}");

        $response->assertOk();
    }

    #[Test]
    public function any_case_manager_can_access_referral(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $intruder = User::factory()->create(['role' => 'CASE_MANAGER']);

        $case = CaseFile::factory()->create(['user_id' => $owner->id]);
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);

        $response = $this->actingAs($intruder)->get("/referrals/{$referral->id}");

        $response->assertOk();
    }

    #[Test]
    public function admin_can_access_any_referral(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $case = CaseFile::factory()->create();
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);

        $response = $this->actingAs($admin)->get("/referrals/{$referral->id}");

        $response->assertOk();
    }
}
