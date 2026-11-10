<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use App\Services\DashboardService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryAnalyticsAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_analytics_count_distinct_cases_per_referral_category(): void
    {
        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_cases_by_category');

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $primary = CaseCategory::factory()->create(['name' => 'Primary']);
        $secondary = CaseCategory::factory()->create(['name' => 'Secondary']);
        $excluded = CaseCategory::factory()->create(['name' => 'Excluded']);
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'user_id' => $manager->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $primary->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => Agency::factory()->create()->id, 'category_id' => $primary->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $secondary->id]);
        $draft = CaseFile::factory()->create(['status' => 'DRAFT', 'user_id' => $manager->id]);
        Referral::factory()->create(['case_id' => $draft->id, 'agcy_id' => $agency->id, 'category_id' => $excluded->id]);

        $stats = collect(app(CaseService::class)->getCaseStats()['category_breakdown'])->pluck('count', 'name');
        $this->assertSame(1, (int) $stats['Primary']);
        $this->assertSame(1, (int) $stats['Secondary']);
        $this->assertArrayNotHasKey('Excluded', $stats->all());

        $dashboard = collect(app(DashboardService::class)->getCaseManagerData($manager)['casesByCategory'])->pluck('count', 'name');
        $this->assertSame(1, (int) $dashboard['Primary']);
        $this->assertSame(1, (int) $dashboard['Secondary']);

        $report = collect(app(ReportsService::class)->categoryDistribution($manager->id, UserRole::CASE_MANAGER->value))->pluck('count', 'name');
        $this->assertSame(1, (int) $report['Primary']);
        $this->assertSame(1, (int) $report['Secondary']);
    }

    public function test_agency_analytics_include_only_its_referral_categories(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $category = CaseCategory::factory()->create(['name' => 'Agency only']);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $category->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $otherAgency->id, 'category_id' => null]);

        $rows = app(ReportsService::class)->categoryDistribution(null, UserRole::AGENCY->value, $agency->id);

        $this->assertSame(['Agency only'], collect($rows)->pluck('name')->all());
        $this->assertSame(1, $rows[0]['count']);
    }

    public function test_approval_audits_status_change_to_open(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->pendingReview()->create(['assigned_case_manager_id' => $manager->id]);
        $documentIds = [];
        foreach (['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'] as $slot) {
            $documentIds[] = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => $slot, 'referral_id' => null])->id;
        }

        $approved = app(CaseService::class)->approveCase($case->id, $manager->id, [
            'confirmed_assessments' => ['LEGAL_ASSISTANCE'],
            'reviewed_document_ids' => $documentIds,
        ]);

        $this->assertSame('OPEN', $approved->status);
        $audit = AuditLog::where('module', 'case')->where('entity_id', $case->id)->where('action', 'APPROVE')->orderBy('chain_seq')->get()->last();
        $this->assertNotNull($audit);
        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $audit->old_value['status']);
        $this->assertSame('OPEN', $audit->new_value['status']);
    }
}
