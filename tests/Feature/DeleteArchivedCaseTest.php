<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralComment;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteArchivedCaseTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 8.1: CaseService::deleteArchivedCase tests
    // =========================================================================

    public function test_delete_archived_case_soft_deletes_and_cascades(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'COMPLETED']);
        $comment = ReferralComment::create([
            'refr_id' => $referral->id,
            'content' => 'Test comment',
            'visibility' => 'all',
            'user_id' => $user->id,
        ]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Duplicate record — resolved elsewhere', $user->id);

        // Case is soft-deleted
        $this->assertSoftDeleted('cases', ['id' => $case->id]);
        $case->refresh();
        $this->assertTrue($case->trashed());
        $this->assertTrue($case->is_deleted);
        $this->assertEquals('Duplicate record — resolved elsewhere', $case->deletion_reason);
        $this->assertEquals($user->id, $case->deleted_by);

        // Referral is cascade soft-deleted
        $referral->refresh();
        $this->assertTrue($referral->trashed());
        $this->assertTrue($referral->is_deleted);

        // Comment is cascade soft-deleted (via referral cascade)
        $comment->refresh();
        $this->assertTrue($comment->trashed());
        $this->assertTrue($comment->is_deleted);

        // Document is cascade soft-deleted
        $document->refresh();
        $this->assertTrue($document->trashed());
        $this->assertTrue($document->is_deleted);
    }

    public function test_delete_archived_case_creates_audit_log(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Test deletion reason text', $user->id);

        $log = AuditLog::where('entity_id', $case->id)
            ->where('action', AuditAction::DELETE->value)
            ->where('module', 'case')
            ->orderBy('chain_seq', 'desc')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Test deletion reason text', $log->description);
        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_delete_rejects_non_archived_case(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'user_id' => $user->id]);

        $this->actingAs($user);

        $this->expectException(HttpException::class);

        app(CaseService::class)->deleteArchivedCase($case, 'Trying to delete an open case should fail', $user->id);
    }

    public function test_delete_requires_reason_min_10_chars(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from(route('cases.show', $case))
            ->delete(route('cases.delete-archived', $case), [
                'deletion_reason' => 'short',
            ]);

        $response->assertSessionHasErrors('deletion_reason');
    }

    public function test_delete_requires_reason_field(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from(route('cases.show', $case))
            ->delete(route('cases.delete-archived', $case), []);

        $response->assertSessionHasErrors('deletion_reason');
    }

    public function test_agency_user_cannot_delete_archived_case(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $cm->id]);

        $response = $this->actingAs($agencyUser)
            ->delete(route('cases.delete-archived', $case), [
                'deletion_reason' => 'Agency trying to delete — should be denied',
            ]);

        // Route is behind role:CASE_MANAGER,ADMIN middleware
        $response->assertStatus(403);
    }

    // =========================================================================
    // 8.2: CaseService::restoreTrashedCase tests
    // =========================================================================

    public function test_restore_trashed_case_restores_to_archived(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'COMPLETED']);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Deleting for restore test', $user->id);

        // Verify deleted
        $this->assertTrue($case->fresh()->trashed());

        // Restore
        $service->restoreTrashedCase($case->fresh(), $user->id);

        // Case restored
        $case->refresh();
        $this->assertFalse($case->trashed());
        $this->assertFalse($case->is_deleted);
        $this->assertEquals('ARCHIVED', $case->status);
        $this->assertNull($case->deleted_by);

        // Referral cascade restored
        $referral->refresh();
        $this->assertFalse($referral->trashed());
        $this->assertFalse($referral->is_deleted);

        // Document cascade restored
        $document->refresh();
        $this->assertFalse($document->trashed());
        $this->assertFalse($document->is_deleted);
    }

    public function test_restore_creates_audit_log(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Delete before restore test', $user->id);
        $service->restoreTrashedCase($case->fresh(), $user->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RESTORE->value,
            'module' => 'case',
            'entity_id' => $case->id,
            'user_id' => $user->id,
        ]);
    }

    // =========================================================================
    // 8.3: CaseService::getTrashedCases tests
    // =========================================================================

    public function test_get_trashed_cases_returns_only_soft_deleted(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $activeCase = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);
        $deletedCase = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($deletedCase, 'Deleted for getTrashed test', $user->id);

        $trashed = $service->getTrashedCases([], $user);

        $this->assertEquals(1, $trashed->total());
        $this->assertEquals($deletedCase->id, $trashed->items()[0]->id);
    }

    public function test_get_trashed_cases_filters_by_case_manager_ownership(): void
    {
        $cm1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $cm2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        $case1 = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $cm1->id]);
        $case2 = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $cm2->id]);

        $this->actingAs($cm1);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case1, 'CM1 deleting own case', $cm1->id);

        $this->actingAs($cm2);
        $service->deleteArchivedCase($case2, 'CM2 deleting own case', $cm2->id);

        // CM1 should only see their case
        $trashed = $service->getTrashedCases([], $cm1);
        $this->assertEquals(1, $trashed->total());
        $this->assertEquals($case1->id, $trashed->items()[0]->id);
    }

    public function test_admin_sees_all_trashed_cases(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $case1 = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $cm->id]);
        $case2 = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);

        $this->actingAs($admin);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case1, 'Admin deleting CM case', $admin->id);
        $service->deleteArchivedCase($case2, 'Admin deleting own case', $admin->id);

        $trashed = $service->getTrashedCases([], $admin);
        $this->assertEquals(2, $trashed->total());
    }

    // =========================================================================
    // 8.4: PurgeTrashedCases command tests
    // =========================================================================

    public function test_purge_command_force_deletes_old_cases(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Deleting for purge test', $user->id);

        // Backdate the deleted_at to be older than retention period
        CaseFile::withTrashed()->where('id', $case->id)->update([
            'deleted_at' => now()->subDays(100),
        ]);

        $this->artisan('cases:purge-trashed', ['--days' => 90])
            ->expectsOutputToContain('Purged 1 case(s)')
            ->assertExitCode(0);

        // Case is permanently gone
        $this->assertNull(CaseFile::withTrashed()->find($case->id));
    }

    public function test_purge_command_skips_recent_cases(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Recently deleted case', $user->id);

        // Recent: deleted just now (within retention)
        $this->artisan('cases:purge-trashed', ['--days' => 90])
            ->expectsOutputToContain('No cases to purge')
            ->assertExitCode(0);

        // Case still exists in trashed state
        $this->assertNotNull(CaseFile::withTrashed()->find($case->id));
    }

    public function test_purge_command_respects_custom_days_option(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        $service = app(CaseService::class);
        $service->deleteArchivedCase($case, 'Purge custom days test', $user->id);

        // Backdate to 50 days ago
        CaseFile::withTrashed()->where('id', $case->id)->update([
            'deleted_at' => now()->subDays(50),
        ]);

        // With default 90 days: not purged
        $this->artisan('cases:purge-trashed', ['--days' => 90])
            ->expectsOutputToContain('No cases to purge')
            ->assertExitCode(0);

        // With 30 days: purged
        $this->artisan('cases:purge-trashed', ['--days' => 30])
            ->expectsOutputToContain('Purged 1 case(s)')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Controller integration tests
    // =========================================================================

    public function test_trash_index_renders_for_case_manager(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('cases.trash'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Case/Trash')
                ->has('cases')
            );
    }

    public function test_restore_endpoint_restores_case(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $this->actingAs($user);
        app(CaseService::class)->deleteArchivedCase($case, 'Deleting for endpoint restore test', $user->id);

        $response = $this->actingAs($user)->post(route('cases.restore', $case));

        $response->assertRedirect(route('cases.trash'));
        $response->assertSessionHas('success');

        $case->refresh();
        $this->assertFalse($case->trashed());
        $this->assertEquals('ARCHIVED', $case->status);
    }

    public function test_delete_endpoint_moves_to_trash(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->delete(route('cases.delete-archived', $case), [
                'deletion_reason' => 'This is a valid deletion reason for the test',
            ]);

        $response->assertRedirect(route('cases.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('cases', ['id' => $case->id]);
    }
}
