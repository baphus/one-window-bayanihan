<?php

namespace Tests\Feature;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiCategoryCaseSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_scalar_category_is_stored_and_linked(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();

        $case = app(CaseService::class)->createCase([
            'client_type' => 'OFW',
            'category_id' => $category->id,
            'client' => ['first_name' => 'Legacy', 'last_name' => 'Category'],
        ], $user->id);

        $this->assertSame($category->id, $case->category_id);
        $this->assertSame([$category->id], $case->categories()->pluck('case_categories.id')->all());
    }

    public function test_category_ids_are_persisted_and_update_syncs_the_pivot(): void
    {
        $user = User::factory()->create();
        $first = CaseCategory::factory()->create();
        $second = CaseCategory::factory()->create();
        $third = CaseCategory::factory()->create();

        $case = app(CaseService::class)->createCase([
            'client_type' => 'OFW',
            'category_ids' => [$first->id, $second->id],
            'client' => ['first_name' => 'Multi', 'last_name' => 'Category'],
        ], $user->id);

        $this->assertSame($first->id, $case->category_id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $case->categories()->pluck('case_categories.id')->all());

        app(CaseService::class)->updateDraft($case->id, [
            'category_ids' => [$second->id, $third->id],
        ], $user->id);

        $this->assertSame($second->id, $case->fresh()->category_id);
        $this->assertEqualsCanonicalizing([$second->id, $third->id], $case->fresh()->categories()->pluck('case_categories.id')->all());
        $this->assertDatabaseMissing('case_category', ['case_id' => $case->id, 'case_category_id' => $first->id]);
    }

    public function test_draft_can_start_without_categories_but_publish_requires_one(): void
    {
        $user = User::factory()->create();
        $case = app(CaseService::class)->createCase([
            'client_type' => 'OFW',
            'client' => ['first_name' => 'Draft', 'last_name' => 'Case'],
        ], $user->id);

        $this->assertSame('DRAFT', $case->status);
        try {
            app(CaseService::class)->publishDraft($case->id, $user->id);
            $this->fail('Publishing a draft without a category should fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Category', $exception->errors()['draft'][0]);
        }
    }

    public function test_category_validation_rejects_duplicates_invalid_and_inactive_ids(): void
    {
        $active = CaseCategory::factory()->create();
        $inactive = CaseCategory::factory()->create(['is_active' => false]);

        foreach ([
            [$active->id, $active->id],
            [$active->id, 'not-a-uuid'],
            [$active->id, $inactive->id],
        ] as $ids) {
            $validator = Validator::make(['category_ids' => $ids], (new StoreCaseRequest)->rules());
            $this->assertTrue($validator->fails(), 'Expected category_ids validation to fail.');
        }

        $validator = Validator::make(['category_ids' => [$active->id, $inactive->id]], (new UpdateCaseRequest)->rules());
        $this->assertTrue($validator->fails());
    }

    public function test_multiple_categories_are_supported_by_case_filtering(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $matching = CaseFile::factory()->create(['status' => 'OPEN']);
        $other = CaseFile::factory()->create(['status' => 'OPEN']);
        $target = CaseCategory::factory()->create();
        $unrelated = CaseCategory::factory()->create();
        $now = now();
        DB::table('case_category')->insert([
            ['id' => (string) Str::uuid(), 'case_id' => $matching->id, 'case_category_id' => $target->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) Str::uuid(), 'case_id' => $matching->id, 'case_category_id' => $unrelated->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) Str::uuid(), 'case_id' => $other->id, 'case_category_id' => $unrelated->id, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->actingAs($admin);
        $results = app(CaseService::class)->getCases(['category_ids' => [$target->id]], 'created_at', 'asc');

        $this->assertSame([$matching->id], $results->getCollection()->pluck('id')->all());
    }

    public function test_export_reports_all_categories_and_matches_pivot_category_filters(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $first = CaseCategory::factory()->create(['name' => 'First category']);
        $second = CaseCategory::factory()->create(['name' => 'Second category']);
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => $first->id]);
        $now = now();
        DB::table('case_category')->insert([
            ['id' => (string) Str::uuid(), 'case_id' => $case->id, 'case_category_id' => $first->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) Str::uuid(), 'case_id' => $case->id, 'case_category_id' => $second->id, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $export = app(DataExportQueries::class);
        $rows = $export->getCasesExport($admin, ['category_ids' => [$second->id]]);

        $this->assertCount(1, $rows);
        $this->assertSame('First category, Second category', $rows->first()->categories);
        $this->assertSame(1, $export->countCasesExport($admin, ['category_ids' => [$second->id]]));
    }

    public function test_partial_normal_update_preserves_existing_category_assignments(): void
    {
        $user = User::factory()->create();
        $first = CaseCategory::factory()->create();
        $second = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => $first->id]);
        $this->linkCategories($case, [$first->id, $second->id]);

        app(CaseService::class)->updateCase($case->id, [
            'status' => 'OPEN',
            'client_type' => 'OFW',
            'summary' => 'Updated without category input',
        ], $user->id);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $case->fresh()->categories()->pluck('case_categories.id')->all(),
        );
    }

    public function test_deleting_categorized_draft_removes_pivot_without_orphan(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'DRAFT', 'user_id' => $user->id]);
        $this->linkCategories($case, [$category->id]);

        app(CaseService::class)->deleteDraft($case->id, $user->id);

        $this->assertDatabaseMissing('cases', ['id' => $case->id]);
        $this->assertDatabaseMissing('case_category', ['case_id' => $case->id]);
    }

    public function test_admin_category_usage_counts_secondary_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => null]);
        $this->linkCategories($case, [$category->id]);

        $response = $this->actingAs($admin)->delete(route('admin.case-categories.destroy', $category->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('case_categories', ['id' => $category->id, 'is_active' => true]);
    }

    public function test_admin_category_usage_excludes_deleted_cases(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => null, 'is_deleted' => true]);
        $this->linkCategories($case, [$category->id]);

        $response = $this->actingAs($admin)->get(route('admin.case-categories.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('categories.0.id', $category->id)
            ->where('categories.0.case_files_count', 0)
        );
    }

    public function test_referral_service_category_ids_use_any_semantics_for_secondary_and_legacy_categories(): void
    {
        $service = app(ReferralService::class);
        $agency = Agency::factory()->create();
        $target = CaseCategory::factory()->create();
        $secondary = CaseCategory::factory()->create();
        $unrelated = CaseCategory::factory()->create();

        $legacyCase = CaseFile::factory()->create(['category_id' => $target->id]);
        $secondaryCase = CaseFile::factory()->create(['category_id' => null]);
        $unrelatedCase = CaseFile::factory()->create(['category_id' => $unrelated->id]);
        $this->linkCategories($secondaryCase, [$secondary->id]);

        $legacyReferral = Referral::factory()->create(['case_id' => $legacyCase->id, 'agcy_id' => $agency->id]);
        $secondaryReferral = Referral::factory()->create(['case_id' => $secondaryCase->id, 'agcy_id' => $agency->id]);
        Referral::factory()->create(['case_id' => $unrelatedCase->id, 'agcy_id' => $agency->id]);

        $results = $service->getReferrals(['category_ids' => [$target->id, $secondary->id]]);

        $this->assertEqualsCanonicalizing(
            [$legacyReferral->id, $secondaryReferral->id],
            $results->getCollection()->pluck('id')->all(),
        );
    }

    public function test_client_controller_category_ids_filter_matches_secondary_and_legacy_categories(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $target = CaseCategory::factory()->create();
        $secondary = CaseCategory::factory()->create();
        $unrelated = CaseCategory::factory()->create();
        $legacyClient = Client::factory()->create();
        $secondaryClient = Client::factory()->create();
        $unrelatedClient = Client::factory()->create();

        CaseFile::factory()->create(['client_id' => $legacyClient->id, 'category_id' => $target->id]);
        $secondaryCase = CaseFile::factory()->create(['client_id' => $secondaryClient->id, 'category_id' => null]);
        $this->linkCategories($secondaryCase, [$secondary->id]);
        CaseFile::factory()->create(['client_id' => $unrelatedClient->id, 'category_id' => $unrelated->id]);

        $response = $this->actingAs($admin)->get(route('clients.index', [
            'category_ids' => [$target->id, $secondary->id],
            'per_page' => 100,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->has('clients.data', 2)
            ->where('clients.data.0.id', fn ($id) => in_array($id, [$legacyClient->id, $secondaryClient->id], true))
            ->where('clients.data.1.id', fn ($id) => in_array($id, [$legacyClient->id, $secondaryClient->id], true))
        );
    }

    public function test_data_exports_category_ids_filter_cases_referrals_and_clients_without_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();
        $target = CaseCategory::factory()->create(['name' => 'Target']);
        $secondary = CaseCategory::factory()->create(['name' => 'Secondary']);
        $unrelated = CaseCategory::factory()->create();
        $legacyClient = Client::factory()->create();
        $secondaryClient = Client::factory()->create();
        $unrelatedClient = Client::factory()->create();

        $legacyCase = CaseFile::factory()->create([
            'client_id' => $legacyClient->id,
            'category_id' => $target->id,
        ]);
        $secondaryCase = CaseFile::factory()->create([
            'client_id' => $secondaryClient->id,
            'category_id' => null,
        ]);
        $this->linkCategories($secondaryCase, [$target->id, $secondary->id]);
        $unrelatedCase = CaseFile::factory()->create([
            'client_id' => $unrelatedClient->id,
            'category_id' => $unrelated->id,
        ]);
        $legacyReferral = Referral::factory()->create(['case_id' => $legacyCase->id, 'agcy_id' => $agency->id]);
        $secondaryReferral = Referral::factory()->create(['case_id' => $secondaryCase->id, 'agcy_id' => $agency->id]);
        Referral::factory()->create(['case_id' => $unrelatedCase->id, 'agcy_id' => $agency->id]);

        $queries = app(DataExportQueries::class);
        $filters = ['category_ids' => [$target->id, $secondary->id]];
        $cases = $queries->getCasesExport($admin, $filters);
        $referrals = $queries->getReferralsExport($admin, $filters);
        $clients = $queries->getClientsExport($admin, $filters);

        $this->assertCount(2, $cases);
        $this->assertSame(2, $queries->countCasesExport($admin, $filters));
        $this->assertSame('Secondary, Target', $cases->firstWhere('case_number', $secondaryCase->case_number)->categories);
        $this->assertEqualsCanonicalizing([$legacyReferral->case_id, $secondaryReferral->case_id], $referrals->pluck('case_number')->map(
            fn ($number) => CaseFile::where('case_number', $number)->value('id'),
        )->all());
        $this->assertCount(2, $referrals);
        $this->assertSame(2, $queries->countReferralsExport($admin, $filters));
        $this->assertCount(2, $clients);
        $this->assertSame(2, $queries->countClientsExport($admin, $filters));
    }

    private function linkCategories(CaseFile $case, array $categoryIds): void
    {
        $now = now();
        DB::table('case_category')->insert(array_map(
            fn (string $categoryId) => [
                'id' => (string) Str::uuid(),
                'case_id' => $case->id,
                'case_category_id' => $categoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $categoryIds,
        ));
    }
}
