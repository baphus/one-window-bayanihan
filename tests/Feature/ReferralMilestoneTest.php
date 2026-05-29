<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private CaseFile $case;

    private Referral $referral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::factory()->create();

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
        ]);

        $this->referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);
    }

    #[Test]
    public function agency_user_can_add_milestone(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.milestones.store', $this->referral),
            [
                'title' => 'Initial Assessment Completed',
                'description' => 'Completed the initial assessment for the client.',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestones', [
            'title' => 'Initial Assessment Completed',
            'description' => 'Completed the initial assessment for the client.',
            'refr_id' => $this->referral->id,
            'user_id' => $agencyUser->id,
        ]);
    }

    #[Test]
    public function milestone_requires_title(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.milestones.store', $this->referral),
            [
                'title' => '',
                'description' => 'Some description without a title.',
            ],
        );

        $response->assertSessionHasErrors('title');
    }

    #[Test]
    public function milestone_accepts_optional_description(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.milestones.store', $this->referral),
            [
                'title' => 'Milestone Without Description',
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('milestones', [
            'title' => 'Milestone Without Description',
            'refr_id' => $this->referral->id,
        ]);
    }

    #[Test]
    public function agency_can_add_milestone_without_description(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.milestones.store', $this->referral),
            [
                'title' => 'Progress Update',
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('milestones', [
            'title' => 'Progress Update',
            'description' => null,
            'refr_id' => $this->referral->id,
            'user_id' => $agencyUser->id,
        ]);
    }
}
