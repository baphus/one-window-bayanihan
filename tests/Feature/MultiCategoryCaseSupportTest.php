<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MultiCategoryCaseSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_intake_excludes_case_wide_category_fields(): void
    {
        $this->assertSame(['prohibited'], (new StoreCaseRequest)->rules()['category_id']);
        $this->assertSame(['prohibited'], (new StoreCaseRequest)->rules()['category_ids']);
        $this->assertNotContains('category_id', UpdateCaseRequest::EDITABLE_FIELDS);
    }

    public function test_case_list_category_filter_reads_case_categories(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $matching = CaseFile::factory()->create(['status' => 'OPEN']);
        CaseFile::factory()->create(['status' => 'OPEN']);
        $category = CaseCategory::factory()->create();
        $matching->categories()->attach($category->id);

        $this->actingAsWithMfa($admin);
        $results = app(CaseService::class)->getCases(['category_ids' => [$category->id]], 'created_at', 'asc');
        $this->assertSame([$matching->id], $results->getCollection()->pluck('id')->all());
    }

    public function test_case_export_filters_on_case_category_without_duplicate_cases(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = CaseCategory::factory()->create(['name' => 'Target']);
        $sibling = CaseCategory::factory()->create(['name' => 'Sibling']);
        $unrelated = CaseCategory::factory()->create();
        $matching = CaseFile::factory()->create(['status' => 'OPEN']);
        $other = CaseFile::factory()->create(['status' => 'OPEN']);
        $matching->categories()->attach([$target->id, $sibling->id]);
        $other->categories()->attach($unrelated->id);

        $queries = app(DataExportQueries::class);
        $rows = $queries->getCasesExport($admin, ['category_ids' => [$target->id]]);
        $this->assertCount(1, $rows);
        $this->assertSame($matching->case_number, $rows->first()->case_number);
        $this->assertSame(1, $queries->countCasesExport($admin, ['category_ids' => [$target->id]]));
    }

    public function test_referral_category_filters_classify_each_referral_independently(): void
    {
        $service = app(ReferralService::class);
        $agency = Agency::factory()->create();
        $target = CaseCategory::factory()->create();
        $otherCategory = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $matching = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $target->id]);
        $sibling = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => Agency::factory()->create()->id, 'category_id' => $otherCategory->id]);

        $results = $service->getReferrals(['category_ids' => [$target->id]]);
        $this->assertSame([$matching->id], $results->getCollection()->pluck('id')->all());
        $this->assertDatabaseHas('referrals', ['id' => $sibling->id, 'category_id' => $otherCategory->id]);
    }

    public function test_correction_validation_requires_reason_and_prohibits_classification_changes(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['assigned_case_manager_id' => $manager->id]);
        $request = new UpdateCaseRequest;
        $request->setUserResolver(fn () => $manager);
        $matchedRoute = Route::getRoutes()->match(Request::create('/cases/'.$case->id, 'PATCH'));
        $request->setRouteResolver(fn () => $matchedRoute);
        $request->merge(['first_name' => 'Updated', 'category_id' => 'forged']);
        $rules = $request->rules();

        $this->assertContains('required', $rules['correction_reason']);
        $this->assertArrayNotHasKey('category_id', $rules);
    }

    public function test_category_admin_usage_counts_referrals_not_cases(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $category = CaseCategory::factory()->create();
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'is_deleted' => true]);
        Referral::factory()->create(['case_id' => $case->id, 'category_id' => $category->id, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($admin)->get(route('admin.case-categories.index'))
            ->assertInertia(fn ($page) => $page->where('categories.0.id', $category->id)->where('categories.0.case_files_count', 0));
    }
}
