<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The UI hides referral editing until the agency accepts. These assert the
 * server applies the same rule to a direct POST.
 */
class ReferralWriteGateTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Referral $referral;

    private User $agencyUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::factory()->create();
        $case = CaseFile::factory()->open()->create();
        $this->referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'status' => 'PENDING',
        ]);
        $this->agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $this->agency->id,
        ]);
    }

    #[Test]
    public function agency_cannot_add_a_milestone_before_accepting(): void
    {
        // Refused with the product's own message rather than a bare 403: the
        // page already hides the control and shows the same sentence.
        $this->actingAs($this->agencyUser)
            ->post(route('referrals.milestones.store', $this->referral), ['title' => 'Too early'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('milestones', ['title' => 'Too early']);
    }

    #[Test]
    public function agency_can_add_a_milestone_after_accepting(): void
    {
        $this->referral->update(['status' => 'PROCESSING']);

        $this->actingAs($this->agencyUser)
            ->post(route('referrals.milestones.store', $this->referral), ['title' => 'Accepted update'])
            ->assertRedirect();

        $this->assertDatabaseHas('milestones', ['title' => 'Accepted update', 'refr_id' => $this->referral->id]);
    }

    #[Test]
    public function case_manager_edits_are_not_blocked_by_the_acceptance_rule(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($manager)
            ->post(route('referrals.milestones.store', $this->referral), ['title' => 'Manager note'])
            ->assertRedirect();

        $this->assertDatabaseHas('milestones', ['title' => 'Manager note']);
    }

    #[Test]
    public function an_ofw_cannot_reach_referral_write_routes(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);

        $this->actingAs($ofw)
            ->post(route('referrals.milestones.store', $this->referral), ['title' => 'Not allowed'])
            ->assertForbidden();

        $this->actingAs($ofw)
            ->patch(route('referrals.update-status', $this->referral), ['status' => 'COMPLETED'])
            ->assertForbidden();
    }

    /**
     * The acceptance rule is a MILESTONE rule, not a referral-wide one. Service
     * assignment, requirements, attachments and comments are how an agency
     * takes up a referral, and the referral page offers all of them while it
     * is still PENDING (only `canAddMilestone` is status-gated in
     * Referral/Show). An earlier blanket gate on these handlers broke agency
     * service assignment entirely.
     */
    #[Test]
    public function the_acceptance_rule_does_not_block_service_work_on_a_pending_referral(): void
    {
        $service = Service::create([
            'name' => 'Legal Assistance',
            'agcy_id' => $this->agency->id,
        ]);

        $this->actingAs($this->agencyUser)
            ->post(route('referrals.services.add', $this->referral), ['service_id' => $service->id])
            ->assertRedirect();

        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $this->referral->id,
            'service_id' => $service->id,
        ]);

        // And removing it again is not gated either.
        $this->actingAs($this->agencyUser)
            ->delete(route('referrals.services.remove', [$this->referral, $service]))
            ->assertRedirect();

        $this->assertDatabaseMissing('referral_services', [
            'referral_id' => $this->referral->id,
            'service_id' => $service->id,
        ]);
    }

    /**
     * The route middleware must admit the roles the controller already answers
     * with its own message, or a case manager gets a bare 403 instead of the
     * "Only the receiving agency can manage services" redirect the UI renders.
     */
    #[Test]
    public function a_case_manager_is_refused_with_the_products_message_not_a_403(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($manager)
            ->delete(route('referrals.services.remove', [$this->referral, '00000000-0000-0000-0000-000000000000']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
