<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Requests\StoreCaseRequest;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function reviewDocuments(CaseFile $case): array
    {
        $ids = [];
        foreach (['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'] as $slot) {
            $ids[] = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => $slot, 'referral_id' => null])->id;
        }

        return $ids;
    }

    public function test_case_and_referral_category_filters_are_independent_and_case_input_is_prohibited(): void
    {
        $rules = (new StoreCaseRequest)->rules();
        $this->assertSame(['prohibited'], $rules['category_id']);
        $this->assertSame(['prohibited'], $rules['category_ids']);

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $target = CaseCategory::factory()->create();
        $other = CaseCategory::factory()->create();
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $case->categories()->attach($target->id);
        $matching = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $target->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => Agency::factory()->create()->id, 'category_id' => $other->id]);
        $this->actingAsWithMfa($manager);
        $this->assertSame([$case->id], app(CaseService::class)->getCases(['category_id' => $target->id])->getCollection()->pluck('id')->all());
        $this->assertSame([$matching->id], app(ReferralService::class)->getReferrals(['category_id' => $target->id])->getCollection()->pluck('id')->all());
    }

    public function test_partial_factual_correction_preserves_classification_and_requires_reason(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['status' => 'OPEN', 'assigned_case_manager_id' => $manager->id]);
        $category = CaseCategory::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'category_id' => $category->id, 'agcy_id' => Agency::factory()->create()->id]);

        $this->expectException(ValidationException::class);
        try {
            app(CaseService::class)->updateCase($case->id, ['first_name' => 'Changed'], $manager->id);
        } finally {
            $this->assertSame('Juan', $case->fresh()->first_name);
            $this->assertSame($category->id, $referral->fresh()->category_id);
        }
    }

    public function test_assigned_manager_correction_does_not_mutate_referral_classification(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['status' => 'OPEN', 'assigned_case_manager_id' => $manager->id]);
        $category = CaseCategory::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'category_id' => $category->id, 'agcy_id' => Agency::factory()->create()->id]);

        app(CaseService::class)->updateCase($case->id, ['first_name' => 'Corrected', 'correction_reason' => 'Verified transcription'], $manager->id);

        $this->assertSame('Corrected', $case->fresh()->first_name);
        $this->assertSame($category->id, $referral->fresh()->category_id);
    }

    public function test_approval_requires_confirmation_documents_and_a_category(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['return_reason' => 'OTHERS', 'assigned_case_manager_id' => $manager->id]);
        $ids = $this->reviewDocuments($case);
        $categories = [CaseCategory::factory()->create()->id];
        try {
            app(CaseService::class)->approveCase($case->id, $manager->id, ['confirmed_assessments' => [], 'reviewed_document_ids' => $ids, 'category_ids' => $categories]);
            $this->fail('Approval requires at least one confirmed assessment.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $case->fresh()->status);
        $this->assertSame('OPEN', app(CaseService::class)->approveCase($case->id, $manager->id, [
            'confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'reviewed_document_ids' => $ids, 'category_ids' => $categories,
        ])->status);
        $this->assertSame($categories, $case->fresh()->categories()->pluck('case_categories.id')->all());
    }

    public function test_referral_category_reference_is_validated_independently(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $agency = Agency::factory()->create();
        $this->expectException(ValidationException::class);
        app(ReferralService::class)->createReferral([
            'case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => fake()->uuid(), 'concern' => 'Request assistance',
        ], $manager->id);
    }
}
