<?php

namespace Tests\Feature;

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
            'role' => 'CASE_MANAGER',
            'password' => bcrypt('password'),
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin.audit@test.local',
            'role' => 'ADMIN',
            'password' => bcrypt('password'),
        ]);

        $this->ofwUser = User::factory()->create([
            'name' => 'Test OFW',
            'email' => 'ofw.audit@test.local',
            'role' => 'OFW',
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

    public function test_cm_can_access_drafts(): void
    {
        $response = $this->actingAs($this->cmUser)->get('/cases/drafts');
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
        $otherCM = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $otherCM->id]);

        $response = $this->actingAs($this->cmUser)
            ->get("/cases/{$case->id}");
        $response->assertStatus(200);
    }

    public function test_cm_can_edit_another_cm_case(): void
    {
        $otherCM = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->cmUser)
            ->patch("/cases/{$case->id}", ['summary' => 'updated']);
        $response->assertStatus(302);
    }

    public function test_cm_can_toggle_status_of_another_cm_case(): void
    {
        $otherCM = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->cmUser)
            ->post("/cases/{$case->id}/toggle-status");
        $response->assertStatus(302);
    }

    /**
     * delete-archived route uses `{case}` implicit model binding (find by
     * UUID without withTrashed), so a trashed case yields 404 before the
     * controller's auth check runs. This is a secondary bug (broken path to
     * trashed-case deletion) — the primary authorization finding (any CM
     * can trash any ARCHIVED case) still stands once the binding is fixed.
     */
    public function test_trashed_case_route_binding_blocks_delete_archived(): void
    {
        $otherCM = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'ARCHIVED',
        ]);
        $case->delete(); // soft-delete → trashed

        $response = $this->actingAs($this->cmUser)
            ->delete("/cases/{$case->id}/delete-archived", [
                'deletion_reason' => 'test deletion reason',
            ]);
        // 404 from implicit model binding (CaseFile::find does not resolve
        // trashed rows). After fixing the binding, this should reach the
        // controller and succeed for any CM.
        $response->assertStatus(404);
    }

    public function test_cm_can_see_all_referrals(): void
    {
        $otherCM = User::factory()->create(['role' => 'CASE_MANAGER']);
        $caseA = CaseFile::factory()->create(['user_id' => $otherCM->id]);
        $caseB = CaseFile::factory()->create(['user_id' => $this->cmUser->id]);

        Referral::factory()->create(['case_id' => $caseA->id]);
        Referral::factory()->create(['case_id' => $caseB->id]);

        $response = $this->actingAs($this->cmUser)->get('/referrals');
        $response->assertStatus(200);
    }

    /**
     * P0 — OFW has no role guard on /referrals index or export.
     */
    public function test_ofw_can_access_referrals_index(): void
    {
        $response = $this->actingAs($this->ofwUser)->get('/referrals');
        // Confirmed bug: OFW gets 200 instead of 403
        $response->assertStatus(200);
    }

    public function test_draft_ownership_block(): void
    {
        $otherCM = User::factory()->create(['role' => 'CASE_MANAGER']);
        $draft = CaseFile::factory()->create([
            'user_id' => $otherCM->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->cmUser)
            ->get("/cases/{$draft->id}/edit-draft");
        $response->assertStatus(403);
    }

    public function test_self_filed_draft_editable_by_any_cm(): void
    {
        $draft = CaseFile::factory()->create([
            'user_id' => null,
            'status' => 'DRAFT',
            'source' => 'self_filed',
        ]);

        $response = $this->actingAs($this->cmUser)
            ->get("/cases/{$draft->id}/edit-draft");
        $response->assertStatus(200);
    }
}
