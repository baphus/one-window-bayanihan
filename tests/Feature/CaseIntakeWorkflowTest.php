<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use App\Notifications\CaseUpdated;
use App\Services\CaseService;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaseIntakeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $data = CaseFile::factory()->withIntake()->make()->only(CaseFile::INTAKE_FIELDS);

        return array_merge($data, [
            'client_type' => 'OFW', 'summary' => 'Request for legal assistance', 'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS', 'consent' => true, 'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'employment_employer_address' => 'Private employer address',
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ]);
    }

    public function test_fresh_owner_creates_independent_cases_without_a_client_and_cannot_forge_ownership(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $other = User::factory()->create();
        $data = $this->payload();
        $data['ofw_user_id'] = $other->id;
        $first = app(CaseService::class)->createCase($data, $owner->id);
        $data['first_name'] = 'Independent';
        $second = app(CaseService::class)->createCase($data, $owner->id);
        $this->assertSame($owner->id, $first->ofw_user_id);
        $this->assertNull($first->client_id);
        $this->assertNull($first->assigned_case_manager_id);
        $this->assertSame('Juan', $first->fresh()->first_name);
        $this->assertSame('Independent', $second->first_name);
        $this->assertCount(4, $first->documents);
        $this->assertNotSame('Private employer address', $first->getRawOriginal('employment_employer_address'));
    }

    public function test_required_documents_cannot_be_waived_for_public_filing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $data = $this->payload();
        unset($data['documents']['arrival_stamp']);
        $this->expectException(ValidationException::class);
        app(CaseService::class)->createCase($data, $owner->id);
    }

    public function test_approval_assigns_manager_preserves_proposal_and_requires_reviewed_documents(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['return_reason' => 'OTHERS']);
        $ids = [];
        foreach (['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'] as $slot) {
            $ids[] = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => $slot, 'referral_id' => null])->id;
        }
        $approved = app(CaseService::class)->approveCase($case->id, $manager->id, [
            'confirmed_assessments' => ['OTHER'], 'confirmed_assessment_other' => 'Staff confirmed explanation',
            'reviewed_document_ids' => $ids, 'staff_summary' => 'Private staff narrative',
            'category_ids' => [CaseCategory::factory()->create()->id],
        ]);
        $this->assertSame('OPEN', $approved->status);
        $this->assertSame($manager->id, $approved->assigned_case_manager_id);
        $this->assertSame(['LEGAL_ASSISTANCE'], $approved->proposed_assessments);
        $this->assertSame(1, $approved->categories()->count());
    }

    public function test_assigned_manager_correction_has_one_redacted_audit_and_private_reason(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['assigned_case_manager_id' => $manager->id]);
        $before = AuditLog::where('entity_id', $case->id)->count();
        app(CaseService::class)->updateCase($case->id, ['first_name' => 'Sensitive correction', 'correction_reason' => 'Private correction reason'], $manager->id);
        $this->assertSame($before + 1, AuditLog::where('entity_id', $case->id)->count());
        $audit = AuditLog::where('entity_id', $case->id)->latest('chain_seq')->first();
        $this->assertStringNotContainsString('Sensitive correction', json_encode($audit));
        $this->assertStringNotContainsString('Private correction reason', json_encode($audit));
        $this->assertSame('Private correction reason', $case->fresh()->last_correction_reason);
    }

    public function test_assisted_filing_without_documents_opens_case_with_for_compliance_requirements(): void
    {
        Storage::fake('object-storage');
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $data = $this->payload();
        $data['documents'] = [];
        $data['complied_onsite_slots'] = ['arrival_stamp'];
        $case = app(CaseService::class)->createCase($data, $manager->id);

        $this->assertNull($case->ofw_user_id);
        $this->assertSame($manager->id, $case->assigned_case_manager_id);
        $this->assertSame('OPEN', $case->status);
        $this->assertSame('STAFF_ATTESTED', $case->consent_method);
        $this->assertNull($case->consent_evidence);
        $this->assertSame([
            'passport_front_page' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
            'arrival_stamp' => IntakeDocumentService::REQUIREMENT_COMPLIED_ONSITE,
            'sworn_affidavit' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
            'employment_evidence' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
        ], $case->document_requirements);
    }

    public function test_unassigned_manager_cannot_correct_facts(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create();
        $this->expectException(HttpException::class);
        app(CaseService::class)->updateCase($case->id, ['first_name' => 'Forbidden', 'correction_reason' => 'Attempt'], $manager->id);
    }

    public function test_requested_replacement_keeps_case_and_intake_and_invalidates_review(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['ofw_user_id' => $owner->id, 'requested_document_slots' => ['arrival_stamp'], 'reviewed_document_ids' => ['old-review'], 'document_requirements' => ['arrival_stamp' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE]]);
        $old = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'arrival_stamp', 'referral_id' => null]);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), [
            'documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')], 'first_name' => 'Forged rewrite',
        ])->assertRedirect();
        $this->assertSame('Juan', $case->fresh()->first_name);
        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $case->fresh()->status);
        $this->assertSame(IntakeDocumentService::REQUIREMENT_RECEIVED, $case->fresh()->document_requirements['arrival_stamp']);
        $this->assertNull($case->fresh()->requested_document_slots);
        $this->assertNull($case->fresh()->reviewed_document_ids);
        $this->assertSoftDeleted($old);
        $this->assertSame(1, $case->documents()->where('category', 'arrival_stamp')->count());
    }

    public function test_resolving_all_requested_slots_clears_pending_request_indicator(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create([
            'ofw_user_id' => $owner->id, 'requested_document_slots' => ['arrival_stamp'],
            'document_requirements' => ['arrival_stamp' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE],
            'document_request_reason' => 'Unreadable', 'document_requested_by' => User::factory()->create()->id,
            'document_requested_at' => now(), 'reviewed_document_ids' => ['stale'],
        ]);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), [
            'documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')],
        ])->assertRedirect();
        $case->refresh();
        $this->assertNull($case->requested_document_slots);
        $this->assertNull($case->document_request_reason);
        $this->assertNull($case->document_requested_by);
        $this->assertNull($case->document_requested_at);
        $this->assertNull($case->reviewed_document_ids);
    }

    public function test_replacement_rejects_other_owner_and_unrequested_slots(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['ofw_user_id' => $owner->id, 'requested_document_slots' => ['arrival_stamp'], 'document_requirements' => ['arrival_stamp' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE]]);
        $other = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAsWithMfa($other)->post(route('ofw.intake.documents.replace', $case), ['documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')]])->assertForbidden();
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), ['documents' => ['oec' => UploadedFile::fake()->image('oec.jpg')]])->assertSessionHasErrors('documents.oec');
        $this->assertSame(['arrival_stamp'], $case->fresh()->requested_document_slots);
    }

    public function test_ofw_upload_marks_outstanding_requirement_received_and_notifies_manager(): void
    {
        Storage::fake('object-storage');
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->open()->create([
            'ofw_user_id' => $owner->id, 'assigned_case_manager_id' => $manager->id,
            'document_requirements' => [
                'passport_front_page' => IntakeDocumentService::REQUIREMENT_RECEIVED,
                'arrival_stamp' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
            ],
        ]);
        CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'passport_front_page', 'referral_id' => null]);

        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), [
            'documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')],
        ])->assertRedirect();

        $case->refresh();
        $this->assertSame('OPEN', $case->status);
        $this->assertSame(IntakeDocumentService::REQUIREMENT_RECEIVED, $case->document_requirements['arrival_stamp']);
        $this->assertSame(IntakeDocumentService::REQUIREMENT_RECEIVED, $case->document_requirements['passport_front_page']);
        Notification::assertSentTo($manager, CaseUpdated::class);
    }

    public function test_closed_case_offers_no_requirement_upload(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->open()->create([
            'ofw_user_id' => $owner->id,
            'status' => 'CLOSED',
            'document_requirements' => ['arrival_stamp' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE],
        ]);

        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), [
            'documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')],
        ])->assertForbidden();

        $this->assertSame(IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE, $case->fresh()->document_requirements['arrival_stamp']);
    }

    public function test_requirement_sync_downgrades_a_received_slot_when_its_document_is_removed(): void
    {
        $case = CaseFile::factory()->withIntake()->open()->create([
            'document_requirements' => [
                'passport_front_page' => IntakeDocumentService::REQUIREMENT_RECEIVED,
                'arrival_stamp' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
            ],
        ]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'passport_front_page', 'referral_id' => null]);
        $service = app(IntakeDocumentService::class);

        $service->syncRequirements($case->fresh());
        $this->assertSame(IntakeDocumentService::REQUIREMENT_RECEIVED, $case->fresh()->document_requirements['passport_front_page']);
        $this->assertSame(IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE, $case->fresh()->document_requirements['arrival_stamp']);

        $document->delete();
        $service->syncRequirements($case->fresh());
        $this->assertSame(IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE, $case->fresh()->document_requirements['passport_front_page']);
    }
}
