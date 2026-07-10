<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Milestone;
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

    /**
     * Create a referral with specific timestamps, bypassing $fillable protection
     * for created_at/updated_at.
     */
    private function createReferralWithAge(array $overrides = [], int $daysAgo = 12): Referral
    {
        $referral = new Referral;
        $referral->timestamps = false;

        $referral->required_services = $overrides['required_services'] ?? 'Test service';
        $referral->status = $overrides['status'] ?? 'PENDING';
        $referral->case_id = $overrides['case_id'] ?? CaseFile::factory()->create()->id;
        $referral->agcy_id = $overrides['agcy_id'] ?? Agency::factory()->create()->id;

        $referral->created_at = now()->subDays($daysAgo);
        $referral->updated_at = now()->subDays($daysAgo);
        $referral->save();

        $referral->timestamps = true;

        return $referral;
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
    public function agency_can_access_overdue_referrals_page(): void
    {
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => Agency::factory()]);

        $response = $this->actingAs($agencyUser)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function page_has_correct_inertia_props(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/OverdueReferrals/Index')
                ->has('stats')
                ->has('referrals')
                ->has('userRole')
                ->has('overdueDays')
                ->where('userRole', 'ADMIN')
            );
    }

    #[Test]
    public function admin_sees_all_agencies_overdue_referrals(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency1 = Agency::factory()->create();
        $agency2 = Agency::factory()->create();

        $this->createReferralWithAge(['agcy_id' => $agency1->id], daysAgo: 12);
        $this->createReferralWithAge(['agcy_id' => $agency2->id], daysAgo: 12);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 2)
            );
    }

    #[Test]
    public function case_manager_sees_only_their_cases_overdue_referrals(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherCm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $cmCase = CaseFile::factory()->create(['user_id' => $cm->id]);
        $otherCase = CaseFile::factory()->create(['user_id' => $otherCm->id]);

        $ownReferral = $this->createReferralWithAge(['case_id' => $cmCase->id], daysAgo: 12);
        $this->createReferralWithAge(['case_id' => $otherCase->id], daysAgo: 12);

        $response = $this->actingAs($cm)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $ownReferral->id)
            );
    }

    #[Test]
    public function agency_sees_only_referrals_to_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $ownReferral = $this->createReferralWithAge(['agcy_id' => $agency->id], daysAgo: 12);
        $this->createReferralWithAge(['agcy_id' => $otherAgency->id], daysAgo: 12);

        $response = $this->actingAs($agencyUser)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $ownReferral->id)
            );
    }

    #[Test]
    public function inactivity_based_overdue_excludes_referrals_with_recent_activity(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();
        $caseFile = CaseFile::factory()->create();

        // Stale referral — no milestones
        $staleReferral = $this->createReferralWithAge([
            'agcy_id' => $agency->id,
            'case_id' => $caseFile->id,
        ], daysAgo: 30);

        // Active referral — old but has a recent milestone
        $activeReferral = $this->createReferralWithAge([
            'agcy_id' => $agency->id,
            'case_id' => $caseFile->id,
        ], daysAgo: 30);

        Milestone::factory()->create([
            'refr_id' => $activeReferral->id,
            'created_at' => now()->subDays(2), // recent activity — not overdue
        ]);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $staleReferral->id)
            );
    }
}
