<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
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

    public function test_invalid_duplicate_and_oversized_category_filters_are_rejected_on_all_list_and_export_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $tooMany = array_map(static fn () => (string) Str::uuid(), range(1, 51));
        $scalarPlusMaximumArray = array_map(static fn () => (string) Str::uuid(), range(1, 50));
        $scalarForMaximumArray = (string) Str::uuid();
        $duplicate = (string) Str::uuid();
        $cases = [
            ['key' => 'category_id', 'error' => 'category_ids.0', 'value' => 'not-a-uuid'],
            ['key' => 'category_ids', 'error' => 'category_ids.0', 'value' => [$duplicate, $duplicate]],
            ['key' => 'category_ids', 'error' => 'category_ids', 'value' => $tooMany],
            ['key' => 'category_ids', 'error' => 'category_ids', 'value' => $scalarPlusMaximumArray, 'scalar' => $scalarForMaximumArray],
        ];

        foreach ($cases as $case) {
            foreach ([
                'cases.index', 'cases.export-excel',
                'clients.index', 'clients.export-excel',
                'referrals.index', 'referrals.export-excel',
            ] as $routeName) {
                $query = [$case['key'] => $case['value']];
                if (isset($case['scalar'])) {
                    $query['category_id'] = $case['scalar'];
                }

                $response = $this->actingAs($admin)->get(route($routeName, $query));

                $response->assertStatus(302)->assertSessionHasErrors($case['error']);
            }
        }
    }

    public function test_scalar_and_array_category_filters_preserve_any_semantics(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();
        $first = CaseCategory::factory()->create();
        $second = CaseCategory::factory()->create();
        $firstCase = CaseFile::factory()->create(['category_id' => $first->id]);
        $secondCase = CaseFile::factory()->create(['category_id' => $second->id]);
        $firstClient = Client::factory()->create();
        $secondClient = Client::factory()->create();
        $firstCase->update(['client_id' => $firstClient->id]);
        $secondCase->update(['client_id' => $secondClient->id]);
        Referral::factory()->create(['case_id' => $firstCase->id, 'agcy_id' => $agency->id]);
        Referral::factory()->create(['case_id' => $secondCase->id, 'agcy_id' => $agency->id]);

        foreach ([
            ['category_id' => $first->id, 'count' => 1],
            ['category_ids' => [$first->id, $second->id], 'count' => 2],
        ] as $filter) {
            $this->actingAs($admin)->get(route('cases.index', $filter))
                ->assertInertia(fn ($page) => $page->has('cases.data', $filter['count']));
            $this->actingAs($admin)->get(route('clients.index', $filter))
                ->assertInertia(fn ($page) => $page->has('clients.data', $filter['count']));
            $this->actingAs($admin)->get(route('referrals.index', $filter))
                ->assertInertia(fn ($page) => $page->has('referrals.data', $filter['count']));
        }
    }

    public function test_used_category_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['category_id' => $category->id]);
        $case->categories()->attach($category->id);

        $response = $this->actingAs($admin)->delete(route('admin.case-categories.destroy', $category));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('case_categories', [
            'id' => $category->id,
            'is_deleted' => false,
        ]);
    }

    public function test_unused_category_is_soft_deleted_and_excluded_from_default_queries(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = CaseCategory::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->delete(route('admin.case-categories.destroy', $category));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('case_categories', [
            'id' => $category->id,
            'is_deleted' => true,
            'is_active' => false,
            'deleted_by' => $admin->id,
        ]);
        $this->assertNotNull($category->fresh()->deleted_at);
        $this->assertFalse(CaseCategory::query()->whereKey($category->id)->exists());
    }
}
