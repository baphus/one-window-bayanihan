<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use App\Services\DashboardService;
use App\Services\ReportsService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryAnalyticsAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_stats_dashboard_and_reports_use_distinct_pivot_assignments(): void
    {
        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_cases_by_category');
        Cache::forget('dashboard:cm_counts');

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $primary = CaseCategory::factory()->create(['name' => 'Primary']);
        $secondary = CaseCategory::factory()->create(['name' => 'Secondary']);
        $excluded = CaseCategory::factory()->create(['name' => 'Excluded']);

        $first = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $user->id,
            'category_id' => $primary->id,
        ]);
        $this->link($first, [$primary->id, $secondary->id]);

        $second = CaseFile::factory()->create([
            'status' => 'CLOSED',
            'user_id' => $user->id,
            'category_id' => $primary->id,
        ]);
        $this->link($second, [$primary->id]);

        foreach ([
            ['status' => 'DRAFT', 'is_deleted' => false],
            ['status' => 'ARCHIVED', 'is_deleted' => false],
            ['status' => 'OPEN', 'is_deleted' => true],
        ] as $attributes) {
            $excludedCase = CaseFile::factory()->create(array_merge($attributes, [
                'user_id' => $user->id,
                'category_id' => $excluded->id,
            ]));
            $this->link($excludedCase, [$excluded->id]);
        }

        $stats = app(CaseService::class)->getCaseStats();
        $statsCounts = collect($stats['category_breakdown'])->pluck('count', 'name')->all();
        $this->assertSame(2, $statsCounts['Primary']);
        $this->assertSame(1, $statsCounts['Secondary']);
        $this->assertArrayNotHasKey('Excluded', $statsCounts);

        $dashboard = app(DashboardService::class)->getCaseManagerData($user);
        $dashboardCounts = collect($dashboard['casesByCategory'])->pluck('count', 'name')->all();
        $this->assertSame(2, $dashboardCounts['Primary']);
        $this->assertSame(1, $dashboardCounts['Secondary']);
        $this->assertArrayNotHasKey('Excluded', $dashboardCounts);

        $reportCounts = collect(app(ReportsService::class)->categoryDistribution($user->id, 'CASE_MANAGER'))
            ->pluck('count', 'name')->all();
        $this->assertSame(2, $reportCounts['Primary']);
        $this->assertSame(1, $reportCounts['Secondary']);
        $this->assertArrayNotHasKey('Excluded', $reportCounts);
    }

    public function test_category_audits_capture_sorted_complete_sets_and_skip_reorders_or_drafts(): void
    {
        $user = User::factory()->create();
        $first = CaseCategory::factory()->create(['name' => 'Publication first']);
        $second = CaseCategory::factory()->create(['name' => 'Publication second']);
        $third = CaseCategory::factory()->create(['name' => 'Publication third']);

        $draft = app(CaseService::class)->createCase([
            'client_type' => 'OFW',
            'category_ids' => [$second->id, $first->id],
        ], $user->id);
        $this->assertCount(0, $this->categoryAudits($draft->id));

        $beforeDraftSave = $this->categoryAudits($draft->id)->count();
        app(CaseService::class)->updateDraft($draft->id, [
            'category_ids' => [$third->id, $first->id],
        ], $user->id);
        $this->assertSame($beforeDraftSave, $this->categoryAudits($draft->id)->count());

        $published = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $user->id,
            'category_id' => $first->id,
        ]);
        $this->link($published, [$first->id]);

        $beforeMutation = $this->categoryAudits($published->id)->count();
        app(CaseService::class)->updateCase($published->id, [
            'client_type' => 'OFW',
            'category_ids' => [$second->id, $first->id],
        ], $user->id);
        $audits = $this->categoryAudits($published->id);
        $this->assertSame($beforeMutation + 1, $audits->count());
        $mutation = $audits->last();
        $this->assertSame(sortIds([$first->id]), $mutation->old_value['category_ids']);
        $this->assertSame(sortIds([$first->id, $second->id]), $mutation->new_value['category_ids']);

        app(CaseService::class)->updateCase($published->id, [
            'client_type' => 'OFW',
            'category_ids' => [$first->id, $second->id],
        ], $user->id);
        app(CaseService::class)->updateCase($published->id, [
            'client_type' => 'OFW',
            'category_ids' => [$second->id, $first->id],
        ], $user->id);
        $this->assertSame($beforeMutation + 1, $this->categoryAudits($published->id)->count());
    }

    public function test_publication_audits_final_categories_after_unaudited_draft_changes(): void
    {
        $user = User::factory()->create();
        $first = CaseCategory::factory()->create(['name' => 'Publish regression first']);
        $second = CaseCategory::factory()->create(['name' => 'Publish regression second']);
        $third = CaseCategory::factory()->create(['name' => 'Publish regression third']);
        $service = app(CaseService::class);

        $draft = $service->createCase([
            'client_type' => 'OFW',
            'category_ids' => [$first->id],
            'client' => [
                'first_name' => 'Publish',
                'last_name' => 'Audit',
                'date_of_birth' => '1990-01-01',
                'sex' => 'Male',
                'email' => 'publish@example.test',
                'contact_number' => '+639171234567',
            ],
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
            ],
            'consent' => true,
        ], $user->id);

        $this->assertCount(0, $this->categoryAudits($draft->id));
        $service->updateDraft($draft->id, [
            'category_ids' => [$third->id, $second->id],
        ], $user->id);
        $this->assertCount(0, $this->categoryAudits($draft->id));

        $published = $service->publishDraft($draft->id, $user->id);
        $publicationAudit = $this->categoryAudits($published->id)->last();

        $this->assertNotNull($publicationAudit);
        $this->assertSame('PUBLISH', $publicationAudit->action);
        $this->assertSame(sortIds([$second->id, $third->id]), $publicationAudit->old_value['category_ids']);
        $this->assertSame(sortIds([$second->id, $third->id]), $publicationAudit->new_value['category_ids']);
    }

    public function test_published_category_sync_invalidates_observable_analytics_and_tracking_caches(): void
    {
        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_cases_by_category');
        Cache::forget('dashboard:cm_counts');

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $primary = CaseCategory::factory()->create(['name' => 'Cache primary']);
        $secondary = CaseCategory::factory()->create(['name' => 'Cache secondary']);
        $case = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $user->id,
            'category_id' => $primary->id,
            'summary' => 'Stable summary',
            'vulnerability_indicator' => 'None',
            'nok_vulnerability_indicator' => null,
            'case_issue_id' => null,
        ]);
        $this->link($case, [$primary->id]);

        app(CaseService::class)->getCaseStats();
        app(DashboardService::class)->getCaseManagerData($user);
        $reports = app(ReportsService::class);
        $reports->categoryDistribution($user->id, 'CASE_MANAGER');
        $tracking = app(TrackingService::class);
        $tracking->buildTrackingData($case->fresh());
        $this->assertTrue(Cache::has('stats:cases'));
        $this->assertTrue(Cache::has('dashboard:cm_cases_by_category'));
        $this->assertTrue(Cache::has('tracking:data:'.$case->id));

        $beforeAdd = $case->fresh();
        $unchanged = [
            'status' => $beforeAdd->status,
            'client_type' => $beforeAdd->client_type,
            'vulnerability_indicator' => $beforeAdd->vulnerability_indicator,
            'nok_vulnerability_indicator' => $beforeAdd->nok_vulnerability_indicator,
            'summary' => $beforeAdd->summary,
            'case_issue_id' => $beforeAdd->case_issue_id,
        ];
        app(CaseService::class)->updateCase($case->id, array_merge($unchanged, [
            'category_ids' => [$primary->id, $secondary->id],
        ]), $user->id);

        $afterAdd = $case->fresh();
        $this->assertSame($beforeAdd->category_id, $afterAdd->category_id);
        $this->assertSame($beforeAdd->updated_at?->toISOString(), $afterAdd->updated_at?->toISOString());
        $this->assertFalse(Cache::has('stats:cases'));
        $this->assertFalse(Cache::has('dashboard:cm_cases_by_category'));
        $this->assertFalse(Cache::has('tracking:data:'.$case->id));

        $statsCounts = collect(app(CaseService::class)->getCaseStats()['category_breakdown'])
            ->pluck('count', 'name')->all();
        $this->assertSame(1, $statsCounts['Cache primary']);
        $this->assertSame(1, $statsCounts['Cache secondary']);
        $dashboardCounts = collect(app(DashboardService::class)->getCaseManagerData($user)['casesByCategory'])
            ->pluck('count', 'name')->all();
        $this->assertSame(1, $dashboardCounts['Cache primary']);
        $this->assertSame(1, $dashboardCounts['Cache secondary']);
        $reportCounts = collect($reports->categoryDistribution($user->id, 'CASE_MANAGER'))
            ->pluck('count', 'name')->all();
        $this->assertSame(1, $reportCounts['Cache primary']);
        $this->assertSame(1, $reportCounts['Cache secondary']);
        $this->assertSame(['Cache primary', 'Cache secondary'], collect($tracking->buildTrackingData($case->fresh())['trackedCase']['categories'])->pluck('name')->all());

        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_cases_by_category');
        app(CaseService::class)->getCaseStats();
        app(DashboardService::class)->getCaseManagerData($user);
        $tracking->buildTrackingData($case->fresh());
        $beforeRemove = $case->fresh();
        $originalUpdatedAt = $beforeRemove->updated_at?->toISOString();
        app(CaseService::class)->updateCase($case->id, array_merge($unchanged, [
            'category_ids' => [$primary->id],
        ]), $user->id);
        $afterRemove = $case->fresh();
        $this->assertSame($beforeRemove->category_id, $afterRemove->category_id);
        $this->assertSame($originalUpdatedAt, $afterRemove->updated_at?->toISOString());
        $this->assertFalse(Cache::has('stats:cases'));
        $this->assertFalse(Cache::has('dashboard:cm_cases_by_category'));
        $this->assertFalse(Cache::has('tracking:data:'.$case->id));

    }

    private function categoryAudits(string $caseId)
    {
        return AuditLog::where('module', 'case')
            ->where('entity_id', $caseId)
            ->whereIn('action', ['CREATE', 'UPDATE', 'PUBLISH'])
            ->whereNotNull('new_value')
            ->orderBy('chain_seq')
            ->get()
            ->filter(fn (AuditLog $audit) => array_key_exists('category_ids', $audit->new_value ?? []))
            ->values();
    }

    private function link(CaseFile $case, array $categoryIds): void
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

function sortIds(array $ids): array
{
    sort($ids, SORT_STRING);

    return $ids;
}
