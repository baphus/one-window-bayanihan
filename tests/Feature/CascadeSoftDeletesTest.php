<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralComment;
use App\Models\User;
use App\Services\CaseService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CascadeSoftDeletes uses the deletion stamp to tell the children it trashed
 * apart from children that were trashed on their own: restore only wakes the
 * former, and a cascade never re-stamps the latter.
 */
class CascadeSoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // =========================================================================
    // Restoring: only the children this cascade trashed come back
    // =========================================================================

    public function test_restore_leaves_independently_deleted_referral_trashed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $cascadeReferral = Referral::factory()->create(['case_id' => $case->id]);
        $independentReferral = Referral::factory()->create(['case_id' => $case->id]);

        // Three weeks before the case was trashed, a case manager removed one
        // referral on its own — its own deletion intent, its own deleted_by.
        Carbon::setTestNow(Carbon::now()->subWeeks(3));
        $this->actingAs($caseManager);
        app(ReferralService::class)->deleteReferral($independentReferral);
        Carbon::setTestNow();

        $independentStamp = Referral::onlyTrashed()->findOrFail($independentReferral->id)->deleted_at;

        // Now the case itself goes to trash; only the live referral is cascaded.
        $this->actingAs($admin);
        app(CaseService::class)->deleteArchivedCase($case, 'Duplicate record — resolved elsewhere', $admin->id);

        $this->assertTrue($cascadeReferral->fresh()->trashed());
        $this->assertTrue($independentReferral->fresh()->trashed());

        app(CaseService::class)->restoreTrashedCase($case->id, $admin);

        // The cascaded referral comes back.
        $this->assertFalse($case->fresh()->trashed());
        $cascadeReferral = $cascadeReferral->fresh();
        $this->assertFalse($cascadeReferral->trashed());
        $this->assertFalse($cascadeReferral->is_deleted);
        $this->assertNull($cascadeReferral->deleted_by);

        // The independently deleted referral stays trashed, with its own stamp
        // and its own deleted_by — no silent resurrection.
        $independentReferral = $independentReferral->fresh();
        $this->assertTrue($independentReferral->trashed());
        $this->assertTrue($independentReferral->is_deleted);
        $this->assertEquals($caseManager->id, $independentReferral->deleted_by);
        $this->assertEquals(
            $independentStamp->toDateTimeString(),
            $independentReferral->deleted_at->toDateTimeString()
        );
    }

    public function test_restore_leaves_independently_deleted_document_trashed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $cascadeDocument = CaseDocument::factory()->create(['case_id' => $case->id]);
        $independentDocument = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Same three columns CaseDocumentController::destroy() writes.
        Carbon::setTestNow(Carbon::now()->subWeeks(3));
        $this->actingAs($caseManager);
        $independentDocument->delete();
        Carbon::setTestNow();

        $independentStamp = CaseDocument::onlyTrashed()->findOrFail($independentDocument->id)->deleted_at;

        $this->actingAs($admin);
        app(CaseService::class)->deleteArchivedCase($case, 'Document retention sweep on the case', $admin->id);
        app(CaseService::class)->restoreTrashedCase($case->id, $admin);

        $this->assertFalse($cascadeDocument->fresh()->trashed());

        $independentDocument = $independentDocument->fresh();
        $this->assertTrue($independentDocument->trashed());
        $this->assertTrue($independentDocument->is_deleted);
        $this->assertEquals($caseManager->id, $independentDocument->deleted_by);
        $this->assertEquals(
            $independentStamp->toDateTimeString(),
            $independentDocument->deleted_at->toDateTimeString()
        );
    }

    public function test_restore_of_referral_wakes_only_its_cascaded_comments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $cascadeComment = $this->comment($referral, $admin);
        $independentComment = $this->comment($referral, $admin);

        Carbon::setTestNow(Carbon::now()->subMonths(2));
        $independentComment->delete();
        Carbon::setTestNow();

        $independentStamp = ReferralComment::onlyTrashed()->findOrFail($independentComment->id)->deleted_at;

        $referral->delete();

        $this->assertTrue($cascadeComment->fresh()->trashed());
        $this->assertTrue($independentComment->fresh()->trashed());

        $referral->fresh()->restore();

        $this->assertFalse($referral->fresh()->trashed());

        $cascadeComment = $cascadeComment->fresh();
        $this->assertFalse($cascadeComment->trashed());
        $this->assertFalse($cascadeComment->is_deleted);

        $independentComment = $independentComment->fresh();
        $this->assertTrue($independentComment->trashed());
        $this->assertTrue($independentComment->is_deleted);
        $this->assertEquals(
            $independentStamp->toDateTimeString(),
            $independentComment->deleted_at->toDateTimeString()
        );
    }

    // =========================================================================
    // Deleting: an already-trashed child keeps its own stamp
    // =========================================================================

    public function test_cascade_delete_does_not_overwrite_an_already_trashed_child(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);
        $comment = $this->comment($referral, $admin);
        $independentDocument = CaseDocument::factory()->create(['case_id' => $case->id]);

        // The document was deleted on its own a week before the case went to
        // trash, by a different user.
        Carbon::setTestNow(Carbon::now()->subWeek());
        $this->actingAs($caseManager);
        $independentDocument->delete();
        Carbon::setTestNow();

        $independentStamp = CaseDocument::onlyTrashed()->findOrFail($independentDocument->id)->deleted_at;

        $this->actingAs($admin);
        app(CaseService::class)->deleteArchivedCase($case, 'Case retention sweep', $admin->id);

        // Live children carry the cascade stamp.
        $this->assertTrue($referral->fresh()->trashed());
        $this->assertTrue($comment->fresh()->trashed());
        $this->assertTrue($document->fresh()->trashed());

        // The already-trashed document keeps its original deleted_at and
        // deleted_by — the cascade must not overwrite them.
        $independentDocument = $independentDocument->fresh();
        $this->assertTrue($independentDocument->trashed());
        $this->assertTrue($independentDocument->is_deleted);
        $this->assertEquals($caseManager->id, $independentDocument->deleted_by);
        $this->assertEquals(
            $independentStamp->toDateTimeString(),
            $independentDocument->deleted_at->toDateTimeString()
        );
    }

    // =========================================================================
    // Happy path: cascade trash then cascade restore round-trips
    // =========================================================================

    public function test_cascade_trash_then_restore_returns_every_cascaded_child_to_live(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $admin->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $comment = $this->comment($referral, $admin);
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->actingAs($admin);
        $service = app(CaseService::class);

        $service->deleteArchivedCase($case, 'Archived duplicate — purged', $admin->id);

        $case = $case->fresh();
        $this->assertTrue($case->trashed());
        $this->assertTrue($referral->fresh()->trashed());
        $this->assertTrue($comment->fresh()->trashed());
        $this->assertTrue($document->fresh()->trashed());

        $service->restoreTrashedCase($case->id, $admin);

        $this->assertFalse($case->fresh()->trashed());
        $this->assertFalse($referral->fresh()->trashed());
        $this->assertFalse($comment->fresh()->trashed());
        $this->assertFalse($document->fresh()->trashed());

        // Restore clears the flag bookkeeping the same way it always has.
        $referral = $referral->fresh();
        $this->assertFalse($referral->is_deleted);
        $this->assertNull($referral->deleted_by);
    }

    private function comment(Referral $referral, User $author): ReferralComment
    {
        return ReferralComment::create([
            'refr_id' => $referral->id,
            'content' => 'Comment on the referral',
            'visibility' => 'all',
            'user_id' => $author->id,
        ]);
    }
}
