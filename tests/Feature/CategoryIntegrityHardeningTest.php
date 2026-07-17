<?php

namespace Tests\Feature;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_category_input_shapes_are_rejected_by_requests_and_service(): void
    {
        $category = CaseCategory::factory()->create();
        $data = ['category_id' => $category->id, 'category_ids' => [$category->id]];

        foreach ([StoreCaseRequest::class, UpdateCaseRequest::class, UpdateDraftRequest::class] as $requestClass) {
            $request = $requestClass::create('/', 'POST', $data);
            $validator = Validator::make($request->all(), $request->rules());
            $method = new \ReflectionMethod($request, 'withValidator');
            $method->setAccessible(true);
            $method->invoke($request, $validator);
            $this->assertTrue($validator->fails(), $requestClass);
        }

        $user = User::factory()->create();
        $case = app(CaseService::class)->createCase(['client_type' => 'OFW'], $user->id);
        $this->expectException(ValidationException::class);
        app(CaseService::class)->updateDraft($case->id, $data, $user->id);
    }

    public function test_omitted_partial_update_preserves_assignments(): void
    {
        $first = CaseCategory::factory()->create();
        $second = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => $first->id]);
        $this->link($case, [$first->id, $second->id]);

        app(CaseService::class)->updateCase($case->id, ['client_type' => 'OFW'], $case->user_id);

        $this->assertSame([$first->id, $second->id], $case->fresh()->categories()->pluck('case_categories.id')->all());
        $this->assertSame($first->id, $case->fresh()->category_id);
    }

    public function test_published_case_cannot_be_cleared_but_drafts_may_be_empty(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => $category->id]);
        $this->link($case, [$category->id]);

        foreach ([['category_ids' => []], ['category_id' => null]] as $input) {
            try {
                app(CaseService::class)->updateCase($case->id, $input, $user->id);
                $this->fail('Expected category clearing to fail.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $draft = app(CaseService::class)->createCase(['client_type' => 'OFW', 'category_ids' => [$category->id]], $user->id);
        app(CaseService::class)->updateDraft($draft->id, ['category_ids' => []], $user->id);
        $this->assertDatabaseMissing('case_category', ['case_id' => $draft->id]);
        $this->assertNull($draft->fresh()->category_id);
    }

    public function test_legacy_scalar_input_remains_primary_and_is_linked(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = app(CaseService::class)->createCase(['client_type' => 'OFW', 'category_id' => $category->id], $user->id);

        $this->assertSame($category->id, $case->category_id);
        $this->assertSame([$category->id], $case->categories()->pluck('case_categories.id')->all());
    }

    public function test_primary_retains_then_replaces_deterministically_and_always_belongs_to_pivot(): void
    {
        $user = User::factory()->create();
        $retained = CaseCategory::factory()->create(['name' => 'Zed', 'sort_order' => 99]);
        $replacement = CaseCategory::factory()->create(['name' => 'Alpha', 'sort_order' => 2]);
        $tie = CaseCategory::factory()->create(['name' => 'Beta', 'sort_order' => 2]);
        $case = app(CaseService::class)->createCase([
            'client_type' => 'OFW',
            'category_id' => $retained->id,
        ], $user->id);

        app(CaseService::class)->updateDraft($case->id, [
            'category_ids' => [$retained->id, $replacement->id],
        ], $user->id);
        app(CaseService::class)->updateDraft($case->id, [
            'category_ids' => [$replacement->id, $retained->id, $tie->id],
        ], $user->id);
        $this->assertSame($retained->id, $case->fresh()->category_id);

        app(CaseService::class)->updateDraft($case->id, [
            'category_ids' => [$tie->id, $replacement->id],
        ], $user->id);
        $fresh = $case->fresh();
        $this->assertSame($replacement->id, $fresh->category_id);
        $this->assertContains($fresh->category_id, $fresh->categories->pluck('id')->all());
    }

    public function test_publish_rejects_categoryless_and_inactive_assignments(): void
    {
        $user = User::factory()->create();
        $service = app(CaseService::class);
        $categoryless = $service->createCase(['client_type' => 'OFW'], $user->id);
        try {
            $service->publishDraft($categoryless->id, $user->id);
            $this->fail('Expected categoryless publish to fail.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $category = CaseCategory::factory()->create();
        $draft = $service->createCase(['client_type' => 'OFW', 'category_id' => $category->id], $user->id);
        $category->update(['is_active' => false]);
        try {
            $service->publishDraft($draft->id, $user->id);
            $this->fail('Expected inactive-category publish to fail.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_undeployed_pivot_migration_backfills_scalar_only_cases(): void
    {
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['category_id' => $category->id]);
        Schema::drop('case_category');

        $migration = require database_path('migrations/2026_07_17_000001_create_case_category_pivot_table.php');
        $migration->up();

        $this->assertDatabaseHas('case_category', ['case_id' => $case->id, 'case_category_id' => $category->id]);
    }

    public function test_pivot_uniqueness_is_enforced(): void
    {
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['category_id' => $category->id]);
        $this->link($case, [$category->id]);

        $this->expectException(QueryException::class);
        DB::table('case_category')->insert([
            'id' => (string) Str::uuid(),
            'case_id' => $case->id,
            'case_category_id' => $category->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_second_postgresql_connection_cannot_update_a_case_while_its_lock_is_held(): void
    {
        $defaultConnection = config('database.default');
        $lockConnection = 'phase1_lock_contention';
        config(["database.connections.{$lockConnection}" => config("database.connections.{$defaultConnection}")]);
        DB::purge($lockConnection);
        DB::setDefaultConnection($lockConnection);

        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'category_id' => $category->id]);
        $this->link($case, [$category->id]);

        DB::setDefaultConnection($defaultConnection);
        $holder = DB::connection($defaultConnection);
        $contender = DB::connection($lockConnection);
        $holder->beginTransaction();
        DB::setDefaultConnection($lockConnection);

        try {
            $holder->select('SELECT id FROM cases WHERE id = ? FOR UPDATE', [$case->id]);
            $contender->statement("SET lock_timeout = '250ms'");

            $thrown = null;
            try {
                app(CaseService::class)->updateCase($case->id, [
                    'client_type' => 'OFW',
                    'category_ids' => [$category->id],
                ], $user->id);
            } catch (QueryException $exception) {
                $thrown = $exception;
            }

            $this->assertNotNull($thrown);
            $this->assertSame('55P03', $thrown->getCode());
        } finally {
            DB::setDefaultConnection($defaultConnection);
            $holder->rollBack();
            $contender->table('cases')->where('id', $case->id)->delete();
            $contender->table('case_categories')->where('id', $category->id)->delete();
            $contender->table('users')->where('id', $user->id)->delete();
            DB::purge($lockConnection);
        }
    }

    public function test_deleting_a_case_cascades_its_category_assignments(): void
    {
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create(['category_id' => $category->id]);
        $this->link($case, [$category->id]);

        $case->forceDelete();

        $this->assertDatabaseMissing('case_category', ['case_id' => $case->id]);
    }

    private function link(CaseFile $case, array $categoryIds): void
    {
        foreach ($categoryIds as $categoryId) {
            $case->categories()->attach($categoryId);
        }
    }
}
