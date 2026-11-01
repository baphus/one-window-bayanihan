<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseManagerWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $cmUser;

    protected User $adminUser;

    protected User $ofwUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cmUser = User::factory()->create([
            'name' => 'Test Case Manager',
            'email' => 'cm.audit@test.local',
            'role' => UserRole::CASE_MANAGER->value,
            'password' => bcrypt('password'),
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin.audit@test.local',
            'role' => UserRole::ADMIN->value,
            'password' => bcrypt('password'),
        ]);

        $this->ofwUser = User::factory()->create([
            'name' => 'Test OFW',
            'email' => 'ofw.audit@test.local',
            'role' => UserRole::OFW->value,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_cm_can_access_cases_index(): void
    {
        $response = $this->actingAs($this->cmUser)->get('/cases');
        $response->assertStatus(200);
    }

    public function test_cm_can_access_cases_create(): void
    {
        $response = $this->actingAs($this->cmUser)->get('/cases/create');
        $response->assertStatus(200);
    }

    public function test_cm_can_access_review_pending(): void
    {
        $response = $this->actingAs($this->cmUser)->get('/cases/review-pending');
        $response->assertStatus(200);
    }

    public function test_cm_can_access_referrals(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->cmUser->id]);
        Referral::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($this->cmUser)->get('/referrals');
        $response->assertStatus(200);
    }

    public function test_cm_cannot_access_admin_users(): void
    {
        $response = $this->actingAs($this->cmUser)->get('/admin/users');
        $response->assertStatus(403);
    }

    public function test_cm_cannot_access_admin_agencies(): void
    {
        $response = $this->actingAs($this->cmUser)->get('/admin/agencies');
        $response->assertStatus(403);
    }

    /**
     * P1 — authorizeCaseAccess() grants ALL CMs access to ALL cases: no
     * ownership check at the controller level. update() / toggleStatus()
     * delegate to CaseService which also skips ownership. Any CM can modify
     * another CM's published case.
     */
    public function test_cm_can_show_another_cm_case(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $otherCM->id]);

        $response = $this->actingAs($this->cmUser)
            ->get("/cases/{$case->id}");
        $response->assertStatus(200);
    }

    public function test_cm_can_edit_another_cm_case(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'OPEN',
        ]);

        $category = CaseCategory::create(['name' => 'Legal Assistance', 'is_active' => true]);

        $response = $this->actingAs($this->cmUser)
            ->patch("/cases/{$case->id}", ['category_ids' => [$category->id]]);
        $response->assertStatus(302);

        $this->assertSame([$category->id], $case->fresh()->categories()->pluck('case_categories.id')->all());
    }

    public function test_cm_can_toggle_status_of_another_cm_case(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->cmUser)
            ->post("/cases/{$case->id}/toggle-status");
        $response->assertStatus(302);
    }

    /**
     * delete-archived is the destructive half of the trash pair, so it shares
     * restore's ownership scope: a CASE_MANAGER may trash only a case they own
     * and ADMIN keeps global access. This test used to pin the opposite — that
     * any CM could trash any ARCHIVED case once route binding stopped
     * interfering — and now pins the guard that closed it.
     */
    public function test_case_manager_cannot_delete_another_managers_archived_case(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'ARCHIVED',
        ]);

        $this->actingAs($this->cmUser)
            ->delete("/cases/{$case->id}/delete-archived", [
                'deletion_reason' => 'another manager trying to delete this case',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => false]);
    }

    public function test_admin_can_delete_another_managers_archived_case(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'ARCHIVED',
        ]);

        $this->actingAs($admin)
            ->delete("/cases/{$case->id}/delete-archived", [
                'deletion_reason' => 'admin removing a duplicate record',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
    }

    /**
     * Route binding resolves `{case}` without withTrashed, so an already-trashed
     * case yields 404 before the controller runs. That is correct: only
     * ARCHIVED (live) cases are deletable.
     */
    public function test_trashed_case_route_binding_blocks_delete_archived(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'ARCHIVED',
        ]);
        $case->delete(); // soft-delete → trashed

        $this->actingAs($this->cmUser)
            ->delete("/cases/{$case->id}/delete-archived", [
                'deletion_reason' => 'test deletion reason',
            ])
            ->assertStatus(404);
    }

    public function test_cm_can_see_all_referrals(): void
    {
        $otherCM = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $caseA = CaseFile::factory()->create(['user_id' => $otherCM->id]);
        $caseB = CaseFile::factory()->create(['user_id' => $this->cmUser->id]);

        Referral::factory()->create(['case_id' => $caseA->id]);
        Referral::factory()->create(['case_id' => $caseB->id]);

        $response = $this->actingAs($this->cmUser)->get('/referrals');
        $response->assertStatus(200);
    }

    /**
     * P0 — OFW had no role guard on the /referrals index or create form, so the
     * unscoped listing (client addresses included) was readable by any filer.
     */
    public function test_ofw_is_denied_the_referrals_index(): void
    {
        $response = $this->actingAs($this->ofwUser)->get('/referrals');
        $response->assertStatus(403);
    }

    public function test_ofw_is_denied_the_referrals_create_form(): void
    {
        $response = $this->actingAs($this->ofwUser)->get('/referrals/create');
        $response->assertStatus(403);
    }
}
