<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeReferral(string $status = 'PENDING'): Referral
    {
        return Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => $status,
            'case_id' => CaseFile::factory()->create()->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);
    }

    public function test_case_manager_can_delete_a_pending_referral(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $referral = $this->makeReferral();
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        $response = $this->actingAs($user)->delete("/referrals/{$referral->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('referrals', ['id' => $referral->id]);
        $this->assertSoftDeleted('milestones', ['id' => $milestone->id]);
        $this->assertSame($user->id, $referral->fresh()->deleted_by);
        $this->assertTrue(AuditLog::where('module', 'referral')->where('action', 'DELETE')->exists());
    }

    public function test_admin_can_delete_any_referral(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $referral = $this->makeReferral();

        $response = $this->actingAs($admin)->delete("/referrals/{$referral->id}");

        $response->assertStatus(302);
        $this->assertSoftDeleted('referrals', ['id' => $referral->id]);
    }

    public function test_completed_referral_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $referral = $this->makeReferral('COMPLETED');

        $response = $this->actingAs($user)->delete("/referrals/{$referral->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'deleted_at' => null]);
    }

    public function test_agency_user_cannot_delete_a_referral(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $referral = $this->makeReferral();

        $response = $this->actingAs($user)->delete("/referrals/{$referral->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'deleted_at' => null]);
    }

    public function test_ofw_user_cannot_delete_a_referral(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $referral = $this->makeReferral();

        $response = $this->actingAs($user)->delete("/referrals/{$referral->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'deleted_at' => null]);
    }
}
