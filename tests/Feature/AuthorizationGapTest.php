<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationGapTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // CaseController::publish()
    // ──────────────────────────────────────────────

    public function test_case_manager_can_publish_own_draft(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->draft()->create(['user_id' => $manager->id]);

        $response = $this->actingAs($manager)->post("/cases/{$case->id}/publish");

        // Successful publish redirects to cases.show
        $response->assertRedirect();
    }

    public function test_agency_without_referral_cannot_publish_case(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->draft()->create(['user_id' => $manager->id]);

        $response = $this->actingAs($agencyUser)->post("/cases/{$case->id}/publish");

        $response->assertStatus(403);
    }

    public function test_agency_cannot_publish_case_middleware_blocks(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->draft()->create(['user_id' => $manager->id]);

        Referral::create([
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->post("/cases/{$case->id}/publish");

        // Route is gated by role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────
    // ReferralController::getAttachmentVersions()
    // ──────────────────────────────────────────────

    public function test_agency_can_get_own_referral_attachment_versions(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/referrals/{$referral->id}/attachments/00000000-0000-0000-0000-000000000000/versions");

        // No versions exist, but access is granted — should return empty array or 200
        $response->assertStatus(200);
    }

    public function test_cross_agency_cannot_get_referral_attachment_versions(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyA->id]);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agencyB->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/referrals/{$referral->id}/attachments/00000000-0000-0000-0000-000000000000/versions");

        $response->assertStatus(403);
    }

    public function test_case_manager_can_get_own_referral_attachment_versions(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $agency = Agency::factory()->create();
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($manager)
            ->getJson("/referrals/{$referral->id}/attachments/00000000-0000-0000-0000-000000000000/versions");

        $response->assertStatus(200);
    }

    public function test_case_manager_can_get_any_referral_attachment_versions(): void
    {
        $managerA = User::factory()->create(['role' => 'CASE_MANAGER']);
        $managerB = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $managerB->id]);
        $agency = Agency::factory()->create();
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($managerA)
            ->getJson("/referrals/{$referral->id}/attachments/00000000-0000-0000-0000-000000000000/versions");

        $response->assertStatus(200);
    }

    public function test_reply_cannot_use_comment_from_another_referral(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $parentCase = CaseFile::factory()->create(['user_id' => $manager->id]);
        $foreignCase = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $parentCase->id]);
        $foreignReferral = Referral::factory()->create(['case_id' => $foreignCase->id]);
        $comment = ReferralComment::create([
            'refr_id' => $foreignReferral->id,
            'content' => 'Foreign comment',
            'visibility' => 'INTERNAL',
            'user_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->post(
            route('referrals.comments.reply', [$referral->id, $comment->id]),
            ['content' => 'Attempted reply'],
        );

        $response->assertNotFound();
        $this->assertDatabaseMissing('referral_comments', ['parent_id' => $comment->id]);
    }

    public function test_attachment_replacement_cannot_use_attachment_from_another_referral(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $parentCase = CaseFile::factory()->create(['user_id' => $manager->id]);
        $foreignCase = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $parentCase->id]);
        $foreignReferral = Referral::factory()->create(['case_id' => $foreignCase->id]);
        $attachment = ReferralAttachment::create([
            'referral_id' => $foreignReferral->id,
            'file_name' => 'foreign.txt',
            'file_path' => 'foreign/foreign.txt',
            'file_type' => 'text/plain',
            'size' => 10,
            'user_id' => $manager->id,
            'version_group_id' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($manager)->post(
            route('referrals.attachments.replace', [$referral->id, $attachment->id]),
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('referral_attachments', [
            'id' => $attachment->id,
            'is_archived' => false,
        ]);
    }

    public function test_attachment_version_history_does_not_return_another_referrals_group(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $parentCase = CaseFile::factory()->create(['user_id' => $manager->id]);
        $foreignCase = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $parentCase->id]);
        $foreignReferral = Referral::factory()->create(['case_id' => $foreignCase->id]);
        $groupId = (string) Str::uuid();
        ReferralAttachment::create([
            'referral_id' => $foreignReferral->id,
            'file_name' => 'foreign.txt',
            'file_path' => 'foreign/foreign.txt',
            'file_type' => 'text/plain',
            'size' => 10,
            'user_id' => $manager->id,
            'version_group_id' => $groupId,
        ]);

        $response = $this->actingAs($manager)->getJson(
            route('referrals.attachments.versions', [$referral->id, $groupId]),
        );

        $response->assertOk()->assertExactJson([]);
    }

    // ──────────────────────────────────────────────
    // CaseIssueController::quickStore()
    // ──────────────────────────────────────────────

    public function test_admin_can_create_case_issue(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->post('/case-issues/quick', [
            'name' => 'Test Issue '.fake()->unique()->word(),
        ]);

        $response->assertStatus(200);
    }

    public function test_case_manager_can_create_case_issue(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($manager)->post('/case-issues/quick', [
            'name' => 'Test Issue '.fake()->unique()->word(),
        ]);

        $response->assertStatus(200);
    }

    public function test_agency_cannot_create_case_issue(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $response = $this->actingAs($agencyUser)->post('/case-issues/quick', [
            'name' => 'Test Issue '.fake()->unique()->word(),
        ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────
    // AuditLogController::index()
    // ──────────────────────────────────────────────

    public function test_admin_sees_all_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'case_files',
            'entity_id' => $case->id,
            'user_id' => $manager->id,
            'timestamp' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/audit-logs');

        $response->assertStatus(200);
    }

    public function test_case_manager_sees_all_case_activity_logs(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, SetPostgresSession::class]);

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $ownCase = CaseFile::factory()->create(['user_id' => $manager->id]);
        $otherCase = CaseFile::factory()->create(['user_id' => $otherManager->id]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'case_files',
            'entity_id' => $ownCase->id,
            'user_id' => $manager->id,
            'timestamp' => now(),
        ]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'case_files',
            'entity_id' => $otherCase->id,
            'user_id' => $otherManager->id,
            'timestamp' => now(),
        ]);

        $data = $this->actingAs($manager)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.logs.data');

        $entityIds = collect($data)->pluck('entity_id')->all();

        // The manager sees activity on all cases, including other managers'.
        $this->assertContains($ownCase->id, $entityIds);
        $this->assertContains($otherCase->id, $entityIds);
    }

    public function test_case_manager_sees_milestone_activity_on_their_referrals(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, SetPostgresSession::class]);

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        AuditLog::truncate();
        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'milestone',
            'entity_id' => $milestone->id,
            'description' => 'MILESTONE ON MY CASE',
            'category' => 'data',
            'timestamp' => now(),
        ]);

        $data = $this->actingAs($manager)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.logs.data');

        $this->assertContains($milestone->id, collect($data)->pluck('entity_id')->all());
    }

    // ──────────────────────────────────────────────
    // StakeholderController::show()
    // ──────────────────────────────────────────────

    public function test_agency_cannot_view_own_stakeholder_middleware_blocks(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $response = $this->actingAs($agencyUser)->get("/stakeholders/{$agency->id}");

        // Route is gated by role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    public function test_agency_cannot_view_other_stakeholder(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $ownAgency->id]);

        $response = $this->actingAs($agencyUser)->get("/stakeholders/{$otherAgency->id}");

        // Route is gated by role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_stakeholder(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();

        $response = $this->actingAs($admin)->get("/stakeholders/{$agency->id}");

        $response->assertStatus(200);
    }

    public function test_case_manager_can_view_any_stakeholder(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agency = Agency::factory()->create();

        $response = $this->actingAs($manager)->get("/stakeholders/{$agency->id}");

        $response->assertStatus(200);
    }
}
