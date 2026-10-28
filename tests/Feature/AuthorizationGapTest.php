<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseCategory;
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
    // CaseController::reviewApprove() / reviewReject()
    // ──────────────────────────────────────────────

    public function test_case_manager_can_approve_pending_filing(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->pendingReview()->create(['user_id' => $filer->id]);
        // approveCase revalidates published categories; attach an active one
        // so the fixture matches production behavior without weakening the gate.
        $case->categories()->attach(CaseCategory::factory()->create()->id);

        $response = $this->actingAs($manager)->post(route('cases.review-approve', $case));

        // Successful approve redirects to review-pending
        $response->assertRedirect();
        $this->assertSame('OPEN', $case->fresh()->status);
    }

    public function test_agency_without_referral_cannot_approve_pending_filing(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->pendingReview()->create(['user_id' => $filer->id]);

        $response = $this->actingAs($agencyUser)->post(route('cases.review-approve', $case));

        $response->assertStatus(403);
    }

    public function test_agency_cannot_approve_pending_filing_middleware_blocks(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->pendingReview()->create(['user_id' => $filer->id]);

        Referral::create([
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->post(route('cases.review-approve', $case));

        // Route is gated by role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────
    // ReferralController::getAttachmentVersions()
    // ──────────────────────────────────────────────

    public function test_agency_can_get_own_referral_attachment_versions(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
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
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agencyA->id]);
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
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
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
        $managerA = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $managerB = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
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
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
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
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
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
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
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
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->post('/case-issues/quick', [
            'name' => 'Test Issue '.fake()->unique()->word(),
        ]);

        $response->assertStatus(200);
    }

    public function test_case_manager_can_create_case_issue(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($manager)->post('/case-issues/quick', [
            'name' => 'Test Issue '.fake()->unique()->word(),
        ]);

        $response->assertStatus(200);
    }

    public function test_agency_cannot_create_case_issue(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);

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
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);

        $ownLog = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'case_files',
            'entity_id' => $case->id,
            'user_id' => $manager->id,
            'timestamp' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/audit-logs');

        $response->assertStatus(200);
    }

    public function test_case_manager_sees_milestone_activity_on_their_referrals(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, SetPostgresSession::class]);

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        AuditLog::truncate();
        $milestoneLog = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'milestone',
            'entity_id' => $milestone->id,
            'description' => 'MILESTONE ON MY CASE',
            'category' => 'data',
            'timestamp' => now(),
        ]);

        $data = $this->actingAs($manager)
            ->withHeader('X-Inertia', 'true')
            ->get('/activity')
            ->json('props.events.data');

        $this->assertContains('MILESTONE ON MY CASE', collect($data)->pluck('description')->all());
    }

    // ──────────────────────────────────────────────
    // StakeholderController::show()
    // ──────────────────────────────────────────────

    public function test_agency_cannot_view_own_stakeholder_middleware_blocks(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);

        $response = $this->actingAs($agencyUser)->get("/stakeholders/{$agency->id}");

        // Route is gated by role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    public function test_agency_cannot_view_other_stakeholder(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $ownAgency->id]);

        $response = $this->actingAs($agencyUser)->get("/stakeholders/{$otherAgency->id}");

        // Route is gated by role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_stakeholder(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::factory()->create();

        $response = $this->actingAs($admin)->get("/stakeholders/{$agency->id}");

        $response->assertStatus(200);
    }

    public function test_case_manager_can_view_any_stakeholder(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();

        $response = $this->actingAs($manager)->get("/stakeholders/{$agency->id}");

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────
    // CaseController::show() — case read access
    // ──────────────────────────────────────────────

    public function test_agency_without_referral_cannot_view_case(): void
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $agencyUser->id,
        ]);

        $response = $this->actingAs($agencyUser)->get("/cases/{$case->id}");

        $response->assertStatus(403);
    }

    public function test_agency_with_active_referral_can_view_case(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $manager->id,
        ]);

        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->get("/cases/{$case->id}");

        $response->assertStatus(200);
    }

    public function test_case_manager_can_view_any_case(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get("/cases/{$case->id}");

        $response->assertStatus(200);
    }

    public function test_admin_can_view_any_case(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $manager->id,
        ]);

        $response = $this->actingAs($admin)->get("/cases/{$case->id}");

        $response->assertStatus(200);
    }
}
