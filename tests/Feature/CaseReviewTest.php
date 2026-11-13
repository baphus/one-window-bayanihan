<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ClientUpdateMail;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
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

        $category = CaseCategory::factory()->create();

        return ['confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'reviewed_document_ids' => $ids, 'category_ids' => [$category->id]];
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
        $this->assertSame(1, $case->categories()->count());
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
            ->assertRedirect()
            ->assertSessionHas('success', 'Case declined.');

        $case->refresh();
        $this->assertFalse($case->trashed());
        $this->assertSame('REJECTED', $case->status);
        $this->assertSame('Incomplete employment history.', $case->deletion_reason);
        $this->assertNull($case->deleted_by);
        $message = "Your assistance request {$case->case_number} was declined. Incomplete employment history. Submit a new request at ".route('ofw.intake');
        $this->assertDatabaseHas('case_notifications', [
            'case_id' => $case->id,
            'client_email' => 'filer@example.test',
            'type' => 'case_rejected',
            'message' => $message,
        ]);
        Mail::assertQueued(ClientUpdateMail::class, fn (ClientUpdateMail $mail): bool => $mail->case->id === $case->id
            && str_contains($mail->message, 'declined')
            && str_contains($mail->message, 'Incomplete employment history.')
            && $mail->message === $message);
        Mail::assertQueued(ClientUpdateMail::class, 1);
    }

    public function test_decline_with_refile_slots_queues_safe_labels_reason_and_intake_url(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($cm)->postJson(route('cases.review-reject', $case), [
            'reason' => 'Please provide clearer evidence.',
            'slots' => ['passport_front_page', 'oec'],
        ])->assertRedirect();

        $message = "Your assistance request {$case->case_number} was declined. Missing requirements: Passport front page, Overseas Employment Certificate (OEC). Please provide clearer evidence. Submit a new request at ".route('ofw.intake');
        $this->assertDatabaseHas('case_notifications', ['case_id' => $case->id, 'message' => $message]);
        Mail::assertQueued(ClientUpdateMail::class, fn (ClientUpdateMail $mail): bool => $mail->message === $message);
        $this->assertStringNotContainsString('filename', strtolower($message));
        $case->refresh();
        $this->assertFalse($case->trashed());
        $this->assertSame('REJECTED', $case->status);
    }

    public function test_decline_rejects_invalid_and_duplicate_slots(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($cm)->postJson(route('cases.review-reject', $case), [
            'reason' => 'Incomplete.', 'slots' => ['not-a-slot'],
        ])->assertUnprocessable()->assertJsonValidationErrors('slots.0');
        $this->actingAsWithMfa($cm)->postJson(route('cases.review-reject', $case), [
            'reason' => 'Incomplete.', 'slots' => ['oec', 'oec'],
        ])->assertUnprocessable()->assertJsonValidationErrors('slots.1');

        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $case->fresh()->status);
    }

    public function test_ofw_cannot_approve_pending_case(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = $this->createPendingCase($filer);

        $this->actingAsWithMfa($filer)->postJson(route('cases.review-approve', $case), $this->reviewPayload($case))
            ->assertForbidden();
    }

    public function test_case_manager_can_update_case_categories_and_only_managers_receive_recommendations(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->create(['user_id' => $filer->id, 'ofw_user_id' => $filer->id]);
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create(['is_active' => true, 'is_deleted' => false]);
        $categories = CaseCategory::factory()->count(2)->create();
        $categories[0]->suggestedAgencies()->attach($agency);

        $this->actingAsWithMfa($cm)->putJson(route('cases.categories.update', $case), ['category_ids' => $categories->pluck('id')->all()])->assertRedirect();
        $this->assertSame(2, $case->categories()->count());

        $expectedCategories = $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values()->all();
        $this->actingAsWithMfa($cm)->get(route('cases.show', $case))->assertInertia(fn ($page) => $page
            ->where('case_categories', $expectedCategories)
            ->where('recommended_agencies.0.id', $agency->id));

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAsWithMfa($admin)->get(route('cases.show', $case))->assertInertia(fn ($page) => $page
            ->where('case_categories', $expectedCategories)
            ->where('recommended_agencies', null));
        $this->actingAsWithMfa($admin)->putJson(route('cases.categories.update', $case), ['category_ids' => [$categories[0]->id]])->assertForbidden();

        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'status' => 'PENDING']);
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($agencyUser)->get(route('cases.show', $case))->assertInertia(fn ($page) => $page
            ->where('case_categories', $expectedCategories)
            ->where('recommended_agencies', null));
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
