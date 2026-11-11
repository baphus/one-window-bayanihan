<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ClientUpdateMail;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** CM approve/reject against PENDING_REVIEW filings; OFW gets 403. */
class CaseReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    private function createPendingCase(User $filer): CaseFile
    {
        return CaseFile::factory()->withIntake()->pendingReview()->create([
            'user_id' => $filer->id,
            'ofw_user_id' => $filer->id,
            'email' => 'filer@example.test',
            'return_reason' => 'OTHERS',
        ]);
    }

    private function reviewPayload(CaseFile $case): array
    {
        $ids = [];
        foreach (['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'] as $slot) {
            $ids[] = CaseDocument::factory()->create([
                'case_id' => $case->id, 'category' => $slot, 'referral_id' => null,
            ])->id;
        }

        return ['confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'reviewed_document_ids' => $ids];
    }

    public function test_case_manager_pending_review_gets_suggestions_and_active_agencies(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->createPendingCase($filer);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create(['is_active' => true, 'is_deleted' => false]);
        $inactive = Agency::factory()->create(['is_active' => false, 'is_deleted' => false]);
        $deleted = Agency::factory()->create(['is_active' => true, 'is_deleted' => true]);
        $category = CaseCategory::factory()->create();
        $category->suggestedAgencies()->attach([$agency->id, $inactive->id, $deleted->id]);

        $response = $this->actingAsWithMfa($cm)->get(route('cases.review-pending'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Case/ReviewPending')
            ->where('categories.0.id', $category->id)
            ->where('categories.0.suggested_agency_ids', [$agency->id])
            ->where('agencies', [[
                'id' => $agency->id,
                'name' => $agency->name,
                'short' => $agency->short,
            ]])
        );
    }

    public function test_cm_approve_sets_open_and_reviewed_fields(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($cm)
            ->postJson(route('cases.review-approve', $case), $this->reviewPayload($case))
            ->assertRedirect();

        $case->refresh();
        $this->assertSame('OPEN', $case->status);
        $this->assertSame($cm->id, $case->reviewed_by);
        $this->assertSame($cm->id, $case->assigned_case_manager_id);
        $this->assertNotNull($case->reviewed_at);
    }

    public function test_cm_approve_records_event_and_audit(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($cm)
            ->postJson(route('cases.review-approve', $case), $this->reviewPayload($case))
            ->assertRedirect();

        $this->assertDatabaseHas('case_events', ['case_id' => $case->id, 'type' => 'case_opened']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $case->id, 'action' => 'APPROVE', 'module' => 'case']);
    }

    public function test_cm_reject_records_reason_and_notifies_case_email(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($cm)
            ->postJson(route('cases.review-reject', $case), ['reason' => 'Incomplete employment history.'])
            ->assertRedirect();

        $case->refresh();
        $this->assertFalse($case->trashed());
        $this->assertSame('REJECTED', $case->status);
        $this->assertSame('Incomplete employment history.', $case->deletion_reason);
        $this->assertNull($case->deleted_by);
        $this->assertDatabaseHas('case_notifications', [
            'case_id' => $case->id,
            'client_email' => 'filer@example.test',
            'type' => 'case_rejected',
            'message' => 'Your assistance request was not approved. The review team will contact you with next steps.',
        ]);
        Mail::assertQueued(ClientUpdateMail::class, fn (ClientUpdateMail $mail): bool => $mail->case->id === $case->id && ! str_contains($mail->message, 'Incomplete employment history.')
        );
    }

    public function test_ofw_cannot_approve_pending_case(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);

        $this->actingAsWithMfa($filer)->postJson(route('cases.review-approve', $case), $this->reviewPayload($case))
            ->assertForbidden();
    }

    public function test_ofw_cannot_reject_pending_case(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);

        $this->actingAsWithMfa($filer)
            ->postJson(route('cases.review-reject', $case), ['reason' => 'Wrong.'])
            ->assertForbidden();
    }
}
