<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        ]);
        $this->assertSame('OPEN', $approved->status);
        $this->assertSame($manager->id, $approved->assigned_case_manager_id);
        $this->assertSame(['LEGAL_ASSISTANCE'], $approved->proposed_assessments);
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

    public function test_assisted_missing_documents_remain_unapprovable_and_consent_is_not_online(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $data = $this->payload();
        unset($data['consent']);
        $data['documents'] = [];
        $data['consent_method'] = 'WRITTEN';
        $data['consent_evidence'] = 'Signed authority retained';
        $data['missing_document_reasons'] = array_fill_keys(['passport_front_page', 'arrival_stamp', 'employment_evidence', 'sworn_affidavit'], 'Applicant obtaining copy');
        $case = app(CaseService::class)->createCase($data, $manager->id);
        $this->assertNull($case->ofw_user_id);
        $this->assertSame($manager->id, $case->assigned_case_manager_id);
        $this->assertSame('WRITTEN', $case->consent_method);
        $this->expectException(ValidationException::class);
        app(CaseService::class)->approveCase($case->id, $manager->id, ['confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'reviewed_document_ids' => []]);
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
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['ofw_user_id' => $owner->id, 'requested_document_slots' => ['arrival_stamp'], 'reviewed_document_ids' => ['old-review']]);
        $old = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'arrival_stamp', 'referral_id' => null]);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), [
            'documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')], 'first_name' => 'Forged rewrite',
        ])->assertRedirect();
        $this->assertSame('Juan', $case->fresh()->first_name);
        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $case->fresh()->status);
        $this->assertNull($case->fresh()->requested_document_slots);
        $this->assertNull($case->fresh()->reviewed_document_ids);
        $this->assertSoftDeleted($old);
        $this->assertSame(1, $case->documents()->where('category', 'arrival_stamp')->count());
    }

    public function test_document_request_requires_assigned_manager_or_admin(): void
    {
        $assigned = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $unassigned = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['assigned_case_manager_id' => $assigned->id]);

        $this->actingAsWithMfa($unassigned)->post(route('cases.document-requests.store', $case), [
            'slots' => ['arrival_stamp'], 'reason' => 'Unreadable scan',
        ])->assertForbidden();
        $this->assertNull($case->fresh()->requested_document_slots);

        $this->actingAsWithMfa($assigned)->post(route('cases.document-requests.store', $case), [
            'slots' => ['arrival_stamp'], 'reason' => 'Unreadable scan',
        ])->assertRedirect();
        $this->actingAsWithMfa($admin)->post(route('cases.document-requests.store', $case), [
            'slots' => ['oec'], 'reason' => 'Also unreadable',
        ])->assertRedirect();

        $case->refresh();
        $this->assertSame(['arrival_stamp', 'oec'], $case->requested_document_slots);
        $this->assertSame('Also unreadable', $case->document_request_reason);
        $this->assertSame($admin->id, $case->document_requested_by);
    }

    public function test_resolving_all_requested_slots_clears_pending_request_indicator(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create([
            'ofw_user_id' => $owner->id, 'requested_document_slots' => ['arrival_stamp'],
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
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['ofw_user_id' => $owner->id, 'requested_document_slots' => ['arrival_stamp']]);
        $other = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAsWithMfa($other)->post(route('ofw.intake.documents.replace', $case), ['documents' => ['arrival_stamp' => UploadedFile::fake()->image('arrival.jpg')]])->assertForbidden();
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.documents.replace', $case), ['documents' => ['oec' => UploadedFile::fake()->image('oec.jpg')]])->assertSessionHasErrors('documents.oec');
        $this->assertSame(['arrival_stamp'], $case->fresh()->requested_document_slots);
    }
}
