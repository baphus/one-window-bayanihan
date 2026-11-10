<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryFilterDeletionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    public function test_invalid_duplicate_and_oversized_category_filters_are_rejected_on_case_and_referral_lists_and_exports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $tooMany = array_map(static fn () => (string) Str::uuid(), range(1, 51));
        $ids = array_map(static fn () => (string) Str::uuid(), range(1, 50));
        $cases = [
            ['key' => 'category_id', 'error' => 'category_ids.0', 'value' => 'not-a-uuid'],
            ['key' => 'category_ids', 'error' => 'category_ids.0', 'value' => [$ids[0], $ids[0]]],
            ['key' => 'category_ids', 'error' => 'category_ids', 'value' => $tooMany],
            ['key' => 'category_ids', 'error' => 'category_ids', 'value' => $ids, 'scalar' => (string) Str::uuid()],
        ];

        foreach ($cases as $case) {
            foreach (['cases.index', 'cases.export-excel', 'cases.export-count', 'referrals.index', 'referrals.export-excel', 'referrals.export-count'] as $routeName) {
                $query = [$case['key'] => $case['value']];
                if (isset($case['scalar'])) {
                    $query['category_id'] = $case['scalar'];
                }

                $this->actingAsWithMfa($admin)->get(route($routeName, $query))
                    ->assertStatus(302)->assertSessionHasErrors($case['error']);
            }
        }
    }

    public function test_scalar_and_array_category_filters_use_referral_classification(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::factory()->create();
        $first = CaseCategory::factory()->create();
        $second = CaseCategory::factory()->create();
        $firstCase = CaseFile::factory()->create(['status' => 'OPEN']);
        $secondCase = CaseFile::factory()->create(['status' => 'OPEN']);
        $firstReferral = Referral::factory()->create(['case_id' => $firstCase->id, 'agcy_id' => $agency->id, 'category_id' => $first->id]);
        $secondReferral = Referral::factory()->create(['case_id' => $secondCase->id, 'agcy_id' => $agency->id, 'category_id' => $second->id]);

        foreach ([[$first->id, 1, $firstCase->id, $firstReferral->id], [[$first->id, $second->id], 2, null, null]] as [$filterValue, $count, $caseId, $referralId]) {
            $filter = is_array($filterValue) ? ['category_ids' => $filterValue] : ['category_id' => $filterValue];
            $this->actingAsWithMfa($admin)->get(route('cases.index', $filter))
                ->assertInertia(fn ($page) => $page->has('cases.data', $count));
            $response = $this->actingAsWithMfa($admin)->get(route('referrals.index', $filter));
            $response->assertInertia(fn ($page) => $page->has('referrals.data', $count));
            if ($caseId !== null) {
                $this->assertSame($caseId, $response->viewData('page')['props']['referrals']['data'][0]['case_id']);
                $this->assertSame($referralId, $response->viewData('page')['props']['referrals']['data'][0]['id']);
            }
        }
    }

    public function test_used_referral_category_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $category = CaseCategory::factory()->create();
        Referral::factory()->create(['case_id' => CaseFile::factory()->create()->id, 'category_id' => $category->id, 'agcy_id' => Agency::factory()->create()->id]);

        $this->actingAsWithMfa($admin)->delete(route('admin.case-categories.destroy', $category))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('case_categories', ['id' => $category->id, 'is_deleted' => false]);
    }

    public function test_unused_category_is_soft_deleted_and_excluded_from_default_queries(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $category = CaseCategory::factory()->create(['is_active' => true]);

        $this->actingAsWithMfa($admin)->delete(route('admin.case-categories.destroy', $category))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('case_categories', ['id' => $category->id, 'is_deleted' => true, 'is_active' => false, 'deleted_by' => $admin->id]);
        $this->assertNotNull($category->fresh()->deleted_at);
        $this->assertFalse(CaseCategory::query()->whereKey($category->id)->exists());
    }
}
