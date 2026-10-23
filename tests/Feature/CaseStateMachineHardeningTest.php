<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $cm->id,
            'category_id' => $category->id,
        ]);
        $case->categories()->attach($category->id);

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
        $this->assertSame(
            [$category->id],
            $fresh->categories()->pluck('case_categories.id')->all()
        );
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::DELETE->value,
            'module' => 'case',
            'entity_id' => $case->id,
        ]);
    }

    // ── updateCase: partial payloads must not wipe untouched fields ──────────

    public function test_status_only_update_preserves_vulnerability_summary_and_issue(): void
    {
        $cm = $this->createCaseManager();
        $issue = CaseIssue::create(['name' => 'Wage Theft', 'is_active' => true]);
        $case = $this->createCase([
            'user_id' => $cm->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'Solo Parent',
            'nok_vulnerability_indicator' => 'Senior Citizen',
            'summary' => 'Original summary text.',
            'case_issue_id' => $issue->id,
        ]);

        $this->service->updateCase($case->id, ['status' => 'CLOSED'], $cm->id);

        $fresh = $case->fresh();
        $this->assertSame('CLOSED', $fresh->status);
        $this->assertSame('Solo Parent', $fresh->vulnerability_indicator);
        $this->assertSame('Senior Citizen', $fresh->nok_vulnerability_indicator);
        $this->assertSame('Original summary text.', $fresh->summary);
        $this->assertSame($issue->id, $fresh->case_issue_id);
    }

    public function test_explicit_null_in_a_partial_payload_still_clears_that_field(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase([
            'user_id' => $cm->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'Solo Parent',
            'summary' => 'Original summary text.',
        ]);

        $this->service->updateCase($case->id, ['summary' => null], $cm->id);

        $fresh = $case->fresh();
        $this->assertNull($fresh->summary);
        $this->assertSame('Solo Parent', $fresh->vulnerability_indicator);
    }

    // ── updateCase: cannot be used to jump the state machine ────────────────

    public function test_update_cannot_publish_a_pending_case(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase([
            'user_id' => $cm->id,
            'status' => CaseFile::STATUS_PENDING_REVIEW,
        ]);

        $this->assertTransitionRejected(
            $case,
            ['status' => 'OPEN'],
            $cm,
            'Pending cases must be published through the review queue.'
        );

        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $case->fresh()->status);
    }

    public function test_update_cannot_archive_a_case_that_is_not_closed(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase(['user_id' => $cm->id, 'status' => 'OPEN']);

        $this->assertTransitionRejected(
            $case,
            ['status' => 'ARCHIVED'],
            $cm,
            'Only closed cases can be archived.'
        );

        $fresh = $case->fresh();
        $this->assertSame('OPEN', $fresh->status);
        $this->assertNull($fresh->closed_at);
    }

    public function test_update_still_closes_and_reopens_an_open_case(): void
    {
        $cm = $this->createCaseManager();
        $case = $this->createCase(['user_id' => $cm->id, 'status' => 'OPEN']);

        $this->service->updateCase($case->id, ['status' => 'CLOSED'], $cm->id);
        $this->assertSame('CLOSED', $case->fresh()->status);

        $this->service->updateCase($case->id, ['status' => 'OPEN'], $cm->id);
        $fresh = $case->fresh();
        $this->assertSame('OPEN', $fresh->status);
        $this->assertNull($fresh->closed_at);
    }

    // ── restore: never reports a restore that did not happen ────────────────

    public function test_restoring_a_purged_case_fails_without_writing_an_audit_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $this->service->deleteArchivedCase($case, 'Duplicate filing — removed for purge test', $admin->id);
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

        $response = $this->actingAs($intruder)->post(route('cases.restore', $case));

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

        $response = $this->actingAs($owner)->post(route('cases.restore', $case));

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

        $response = $this->actingAs($admin)->post(route('cases.restore', $case));

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
        $this->service->deleteArchivedCase($case, 'Archived duplicate — moved to trash', $owner->id);

        return $case;
    }

    private function assertTransitionRejected(CaseFile $case, array $data, User $actor, string $expectedMessage): void
    {
        try {
            $this->service->updateCase($case->id, $data, $actor->id);
            $this->fail('Expected the status transition to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame($expectedMessage, $exception->getMessage());
        }
    }
}
