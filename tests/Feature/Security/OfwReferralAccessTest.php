<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwReferralAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ofw_cannot_access_referral_index(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAs($ofw)->get('/referrals')->assertForbidden();
    }

    #[Test]
    public function ofw_cannot_access_referral_create(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAs($ofw)->get('/referrals/create')->assertForbidden();
    }

    #[Test]
    public function ofw_cannot_access_referral_show(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->create();
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);

        $this->actingAs($ofw)->get("/referrals/{$referral->id}")->assertForbidden();
    }

    #[Test]
    public function agency_without_agency_id_cannot_access_reports(): void
    {
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);
        $this->actingAs($agencyUser)->get('/reports')->assertForbidden();
    }

    #[Test]
    public function agency_with_agency_id_can_access_reports(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->actingAs($agencyUser)->get('/reports')->assertOk();
    }
}
