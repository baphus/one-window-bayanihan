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

    private Referral $interventionReferral;

    private Referral $standardReferral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dmw = Agency::factory()->create([
            'name' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
        ]);

        $this->interventionReferral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Intervention Service',
            'status' => 'PROCESSING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->dmw->id,
            'type' => 'intervention',
        ]);

        $this->standardReferral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Standard Service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->dmw->id,
            'type' => 'standard',
        ]);
    }

    #[Test]
    public function cm_can_add_milestone_to_intervention(): void
    {
        $cmUser = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cmUser)->post(
            route('referrals.milestones.store', $this->interventionReferral),
            [
                'title' => 'CM Milestone on Intervention',
                'description' => 'Case manager added milestone to intervention referral.',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestones', [
            'title' => 'CM Milestone on Intervention',
            'description' => 'Case manager added milestone to intervention referral.',
            'refr_id' => $this->interventionReferral->id,
            'user_id' => $cmUser->id,
        ]);
    }

    #[Test]
    public function cm_can_update_status_on_intervention(): void
    {
        $cmUser = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cmUser)->patch(
            route('referrals.update-status', $this->interventionReferral),
            [
                'status' => 'COMPLETED',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('referrals', [
            'id' => $this->interventionReferral->id,
            'status' => 'COMPLETED',
        ]);
    }

    #[Test]
    public function cm_cannot_update_status_on_standard(): void
    {
        $cmUser = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cmUser)->patch(
            route('referrals.update-status', $this->standardReferral),
            [
                'status' => 'COMPLETED',
            ],
        );

        $response->assertForbidden();
    }

    #[Test]
    public function agency_can_add_milestone_to_intervention(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->dmw->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.milestones.store', $this->interventionReferral),
            [
                'title' => 'Agency Milestone on Intervention',
                'description' => 'Agency added milestone to intervention referral.',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestones', [
            'title' => 'Agency Milestone on Intervention',
            'description' => 'Agency added milestone to intervention referral.',
            'refr_id' => $this->interventionReferral->id,
            'user_id' => $agencyUser->id,
        ]);
    }

    #[Test]
    public function agency_can_update_status_on_intervention(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->dmw->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($agencyUser)->patch(
            route('referrals.update-status', $this->interventionReferral),
            [
                'status' => 'COMPLETED',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('referrals', [
            'id' => $this->interventionReferral->id,
            'status' => 'COMPLETED',
        ]);
    }
}
