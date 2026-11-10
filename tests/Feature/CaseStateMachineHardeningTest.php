<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit regression coverage for case transition and trash integrity:
 *  - rejectCase only ever acts on a still-pending row (M-1);
 *  - updateCase treats the payload as a partial edit and cannot be used to
 *    jump the state machine (M-2);
 *  - restoring a case the purge command already removed fails instead of
 *    reporting a restore that never happened (M-3);
 *  - trash restore is ownership-scoped exactly like the trash listing (M-4).
 */
class CaseStateMachineHardeningTest extends TestCase
{
    use RefreshDatabase;

    private CaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = app(CaseService::class);
    }

    // ── reject: pending-only, and never destructive to a live case ───────────

    public function test_reject_on_an_open_case_fails_and_leaves_the_case_intact(): void
    {
        $cm = $this->createCaseManager();
        $case = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $cm->id,
        ]);

        try {
            $this->service->rejectCase($case->id, 'Filed under the wrong region.', $cm->id);
            $this->fail('Expected rejecting a case that is no longer PENDING_REVIEW to fail.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $fresh = $case->fresh();
        $this->assertFalse($fresh->trashed());
        $this->assertSame('OPEN', $fresh->status);
        $this->assertNull($fresh->deletion_reason);
        $this->assertNull($fresh->deleted_by);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::DELETE->value,
            'module' => 'case',
            'entity_id' => $case->id,
        ]);
    }

    // ── updateCase: partial payloads must not wipe untouched fields ──────────

    public function test_factual_correction_preserves_untouched_intake_fields(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase([
            'user_id' => $cm->id,
            'assigned_case_manager_id' => $cm->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'Solo Parent',
            'summary' => 'Original summary text.',
        ]);

        $this->service->updateCase($case->id, ['summary' => 'Corrected summary text.', 'correction_reason' => 'Verified case facts'], $cm->id);

        $fresh = $case->fresh();
        $this->assertSame('OPEN', $fresh->status);
        $this->assertSame('Solo Parent', $fresh->vulnerability_indicator);
        $this->assertSame('Corrected summary text.', $fresh->summary);
        $this->assertSame('Solo Parent', $fresh->vulnerability_indicator);
    }

    public function test_correction_without_reason_is_rejected_and_leaves_facts_unchanged(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase([
            'user_id' => $cm->id,
            'assigned_case_manager_id' => $cm->id,
            'status' => 'OPEN',
            'summary' => 'Original summary text.',
        ]);

        try {
            $this->service->updateCase($case->id, ['summary' => 'Changed summary'], $cm->id);
            $this->fail('Expected a correction reason to be required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('correction_reason', $exception->errors());
        }

        $this->assertSame('Original summary text.', $case->fresh()->summary);
    }

    public function test_unassigned_case_manager_cannot_correct_case_facts(): void
    {
        $owner = $this->createCaseManager();
        $intruder = $this->createCaseManager();
        $case = $this->createCase([
            'user_id' => $owner->id,
            'assigned_case_manager_id' => $owner->id,
            'summary' => 'Original summary text.',
        ]);

        try {
            $this->service->updateCase($case->id, [
                'summary' => 'Changed summary',
                'correction_reason' => 'Verified facts',
            ], $intruder->id);
            $this->fail('Expected an unassigned case manager correction to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('Original summary text.', $case->fresh()->summary);
    }

    // ── updateCase: cannot be used to jump the state machine ────────────────

    public function test_update_cannot_publish_a_pending_case(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase([
            'user_id' => $cm->id,
            'status' => CaseFile::STATUS_PENDING_REVIEW,
        ]);

        $this->assertTransitionRejected($case, ['status' => 'OPEN', 'correction_reason' => 'Attempted transition'], $cm);

        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $case->fresh()->status);
    }

    public function test_update_cannot_archive_a_case_that_is_not_closed(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase(['user_id' => $cm->id, 'status' => 'OPEN']);

        $this->assertTransitionRejected($case, ['status' => 'ARCHIVED', 'correction_reason' => 'Attempted transition'], $cm);

        $fresh = $case->fresh();
        $this->assertSame('OPEN', $fresh->status);
        $this->assertNull($fresh->closed_at);
    }

    public function test_update_still_closes_and_reopens_an_open_case(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase(['user_id' => $cm->id, 'status' => 'OPEN']);

        $this->service->toggleCaseStatus($case->id, $cm->id);
        $this->assertSame('CLOSED', $case->fresh()->status);

        $this->service->toggleCaseStatus($case->id, $cm->id);
        $fresh = $case->fresh();
        $this->assertSame('OPEN', $fresh->status);
        $this->assertNull($fresh->closed_at);
    }

    // ── restore: never reports a restore that did not happen ────────────────

    public function test_restoring_a_purged_case_fails_without_writing_an_audit_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $this->service->deleteArchivedCase($case, 'Duplicate filing — removed for purge test', $admin);
        $caseId = $case->id;

        // The purge command force-deletes the row out from under a caller that
        // already resolved it.
        CaseFile::onlyTrashed()->findOrFail($caseId)->forceDelete();
        $this->assertNull(CaseFile::withTrashed()->find($caseId));

        try {
            $this->service->restoreTrashedCase($caseId, $admin);
            $this->fail('Expected restoring a purged case to fail.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::RESTORE->value,
            'module' => 'case',
            'entity_id' => $caseId,
        ]);
    }

    // ── restore: ownership scoping ─────────────────────────────────────────

    public function test_case_manager_cannot_restore_another_case_managers_trashed_case(): void
    {
        $owner = $this->createCaseManager();
        $intruder = $this->createCaseManager();
        $case = $this->trashCase($owner);

        $response = $this->actingAsWithMfa($intruder)->post(route('cases.restore', $case));

        $response->assertNotFound();
        $this->assertTrue($case->fresh()->trashed());
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::RESTORE->value,
            'module' => 'case',
            'entity_id' => $case->id,
        ]);
    }

    public function test_owning_case_manager_can_restore_their_trashed_case(): void
    {
        $owner = $this->createCaseManager();
        $case = $this->trashCase($owner);

        $response = $this->actingAsWithMfa($owner)->post(route('cases.restore', $case));

        $response->assertRedirect(route('cases.trash'));
        $response->assertSessionHas('success');

        $fresh = $case->fresh();
        $this->assertFalse($fresh->trashed());
        $this->assertSame('ARCHIVED', $fresh->status);
    }

    public function test_admin_can_restore_any_case_managers_trashed_case(): void
    {
        $owner = $this->createCaseManager();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = $this->trashCase($owner);

        $response = $this->actingAsWithMfa($admin)->post(route('cases.restore', $case));

        $response->assertRedirect(route('cases.trash'));
        $response->assertSessionHas('success');

        $fresh = $case->fresh();
        $this->assertFalse($fresh->trashed());
        $this->assertSame('ARCHIVED', $fresh->status);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function createCaseManager(): User
    {
        return User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
    }

    private function createCase(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->create($attributes);
    }

    private function trashCase(User $owner): CaseFile
    {
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $owner->id]);
        $this->service->deleteArchivedCase($case, 'Archived duplicate — moved to trash', $owner);

        return $case;
    }

    private function assertTransitionRejected(CaseFile $case, array $data, User $actor): void
    {
        try {
            $this->service->updateCase($case->id, $data, $actor->id);
            $this->fail('Expected the status transition to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
