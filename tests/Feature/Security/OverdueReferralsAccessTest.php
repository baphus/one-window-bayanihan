<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OverdueReferralsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function case_manager_can_access_overdue_referrals(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cm)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function admin_can_access_overdue_referrals(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function agency_can_access_only_overdue_referrals_referred_to_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $ownOverdueReferral = Referral::factory()->create([
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(8),
        ]);
        Referral::factory()->create([
            'agcy_id' => $otherAgency->id,
            'created_at' => now()->subDays(8),
        ]);

        $response = $this->actingAs($agencyUser)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/OverdueReferrals/Index')
                ->has('overdueReferrals.data', 1)
                ->where('overdueReferrals.data.0.id', $ownOverdueReferral->id)
            );
    }
}
