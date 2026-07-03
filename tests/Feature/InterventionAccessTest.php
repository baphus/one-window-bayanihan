<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterventionAccessTest extends TestCase
{
    use RefreshDatabase;

    private Agency $dmw;

    private CaseFile $case;

    private User $caseManager;

    private Referral $referral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dmw = Agency::factory()->create([
            'name' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        $this->caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->case = CaseFile::factory()->create([
            'user_id' => $this->caseManager->id,
            'status' => 'OPEN',
        ]);

        $this->referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service',
            'status' => 'PROCESSING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->dmw->id,
        ]);
    }

    #[Test]
    public function cm_can_add_milestone_to_own_referral(): void
    {
        $response = $this->actingAs($this->caseManager)->post(
            route('referrals.milestones.store', $this->referral),
            [
                'title' => 'CM Milestone',
                'description' => 'Case manager added milestone to referral.',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestones', [
            'title' => 'CM Milestone',
            'description' => 'Case manager added milestone to referral.',
            'refr_id' => $this->referral->id,
            'user_id' => $this->caseManager->id,
        ]);
    }

    #[Test]
    public function cm_can_update_status_on_own_referral(): void
    {
        $response = $this->actingAs($this->caseManager)->patch(
            route('referrals.update-status', $this->referral),
            [
                'status' => 'COMPLETED',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('referrals', [
            'id' => $this->referral->id,
            'status' => 'COMPLETED',
        ]);
    }

    #[Test]
    public function agency_can_add_milestone_to_their_referral(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->dmw->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.milestones.store', $this->referral),
            [
                'title' => 'Agency Milestone',
                'description' => 'Agency added milestone to referral.',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestones', [
            'title' => 'Agency Milestone',
            'description' => 'Agency added milestone to referral.',
            'refr_id' => $this->referral->id,
            'user_id' => $agencyUser->id,
        ]);
    }

    #[Test]
    public function agency_can_update_status_on_their_referral(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->dmw->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($agencyUser)->patch(
            route('referrals.update-status', $this->referral),
            [
                'status' => 'COMPLETED',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('referrals', [
            'id' => $this->referral->id,
            'status' => 'COMPLETED',
        ]);
    }
}
