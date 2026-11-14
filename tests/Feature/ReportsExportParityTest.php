<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Screen/export parity for the rebuilt Reports page.
 *
 * The PDF must carry exactly the numbers the Cases + Referrals tabs show,
 * computed on a case-filed basis with the same Period/Agency/Place filters.
 */
class ReportsExportParityTest extends TestCase
{
    use RefreshDatabase;

    private ReportsExportService $exports;

    private ReportsService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exports = app(ReportsExportService::class);
        $this->reports = app(ReportsService::class);
    }

    private function criteriaFor(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'agency_id' => null,
            'user_agcy_id' => $user->agcy_id,
            'from' => '2026-01-01',
            'to' => '2026-12-31',
            'dateScope' => 'case_created_at',
            'province' => null,
            'city' => null,
        ], $overrides);
    }

    private function seedCase(User $owner, Agency $agency, array $caseAttributes = [], array $referralAttributes = []): CaseFile
    {
        $case = CaseFile::factory()->create(array_merge([
            'user_id' => $owner->id,
            'status' => 'OPEN',
        ], $caseAttributes));
        Referral::factory()->create(array_merge([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'status' => 'PENDING',
        ], $referralAttributes));

        return $case;
    }

    #[Test]
    public function geo_detail_exports_ignore_deleted_and_flagged_addresses(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $live = $this->seedCase($manager, $agency);
        $deleted = $this->seedCase($manager, $agency);
        $flagged = $this->seedCase($manager, $agency);
        $live->update(['address_province' => 'Cebu', 'address_city_municipality' => 'Cebu City']);
        $deleted->update(['address_province' => 'Cebu', 'address_city_municipality' => 'Cebu City']);
        $deleted->delete();
        $flagged->forceFill(['address_province' => 'Cebu', 'address_city_municipality' => 'Cebu City', 'is_deleted' => true])->saveQuietly();

        $sheets = $this->exports->buildExcelSheetsFromCriteria($this->criteriaFor($manager, ['province' => 'Cebu']));

        $cases = collect($sheets)->firstWhere('title', 'Case Details');
        $referrals = collect($sheets)->firstWhere('title', 'Referral Details');
        $this->assertSame([$live->id], collect($cases['rows'])->pluck('case_id')->all());
        $this->assertSame([$live->id], collect($referrals['rows'])->pluck('case_id')->all());
    }

    #[Test]
    public function export_payload_contains_the_new_screen_sections(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $this->seedCase($manager, $agency);

        $payload = $this->exports->buildPdfPayloadFromCriteria($this->criteriaFor($manager));

        foreach ([
            'rejectionReasonDistribution', 'closedCasesOverTime', 'caseSourceDistribution',
            'reopenedStats', 'caseEventActorDistribution', 'agencyFirstResponse',
            'clientRequestTypeDistribution', 'avgReferralCompletion', 'geographicMapData',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Export payload is missing the {$key} section");
        }
    }

    #[Test]
    public function scorecard_active_equals_total_minus_completed_per_agency(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $case = $this->seedCase($manager, $agency);
        Referral::factory()->completed()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        Referral::factory()->pending()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $payload = $this->exports->buildPdfPayloadFromCriteria($this->criteriaFor($manager));

        $this->assertNotEmpty($payload['agencyScorecard']);
        foreach ($payload['agencyScorecard'] as $row) {
            $this->assertSame(
                max(0, (int) $row['total'] - (int) $row['completed']),
                (int) $row['active'],
                "Agency {$row['agency']} active must equal total minus completed"
            );
        }
    }

    #[Test]
    public function export_average_completion_matches_filtered_report_kpis(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        foreach ([
            ['case' => '2026-03-10 08:00:00', 'days' => 4, 'province' => 'Cebu'],
            ['case' => '2026-03-11 08:00:00', 'days' => 30, 'province' => 'Cebu'],
            ['case' => '2025-12-10 08:00:00', 'days' => 60, 'province' => 'Cebu'],
            ['case' => '2026-03-12 08:00:00', 'days' => 90, 'province' => 'Bohol'],
        ] as $row) {
            $this->seedCase($manager, $agency,
                ['created_at' => $row['case'], 'updated_at' => $row['case'], 'address_province' => $row['province'], 'address_city_municipality' => 'Test City'],
                ['status' => 'COMPLETED', 'created_at' => $row['case'], 'updated_at' => Carbon::parse($row['case'])->addDays($row['days'])]);
        }

        $criteria = $this->criteriaFor($manager, ['from' => '2026-03-01', 'to' => '2026-03-31', 'province' => 'Cebu']);
        $screenKpis = $this->reports->getAll(
            userId: $manager->id, role: $manager->role, agencyId: $criteria['agency_id'],
            fromDate: $criteria['from'], toDate: $criteria['to'], dateScope: $criteria['dateScope'],
            province: $criteria['province'], city: $criteria['city'],
        )['kpis'];
        $payload = $this->exports->buildPdfPayloadFromCriteria($criteria);

        $this->assertSame(17.0, $screenKpis['avgCompletionDays']);
        $this->assertSame($screenKpis['avgCompletionDays'], $payload['avgReferralCompletion']);
    }

    #[Test]
    public function overdue_labels_name_the_windowed_definition(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $this->seedCase($manager, $agency, [], [
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);

        $criteria = $this->criteriaFor($manager);
        $sheets = $this->exports->buildExcelSheetsFromCriteria($criteria);

        $overdue = collect($sheets)->firstWhere('title', 'Overdue Referrals');
        $this->assertSame(
            'Overdue referrals (due within selected period)',
            $overdue['rows'][0]['metric']
        );

        $dictionary = collect($sheets)->firstWhere('title', 'Data Dictionary');
        $scope = collect($dictionary['rows'])->firstWhere(
            fn ($row) => $row['sheet'] === 'All sheets' && $row['column'] === 'Scope'
        );
        $this->assertStringContainsString('except Agency Workload', $scope['meaning']);

        $html = view('pdf.report', $this->exports->buildPdfPayloadFromCriteria($criteria))->render();
        $this->assertStringContainsString('Overdue referrals (due within selected period', $html);
        $this->assertStringContainsString('Rejection Reasons', $html);
        $this->assertStringContainsString('<th class="num">Active</th>', $html);
    }

    #[Test]
    public function trend_recompute_agrees_with_the_service_methods(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $this->seedCase($manager, $agency,
            ['created_at' => '2026-02-10 08:00:00', 'updated_at' => '2026-02-10 08:00:00'],
            ['created_at' => '2026-02-11 08:00:00', 'updated_at' => '2026-02-11 08:00:00']);
        $this->seedCase($manager, $agency,
            ['created_at' => '2026-03-05 08:00:00', 'updated_at' => '2026-03-05 08:00:00'],
            ['created_at' => '2026-03-06 08:00:00', 'updated_at' => '2026-03-06 08:00:00']);

        $payload = $this->exports->buildPdfPayloadFromCriteria($this->criteriaFor($manager));

        $casesOverTime = $this->reports->getCasesOverTime(
            $manager->id, UserRole::CASE_MANAGER->value, '2026-01-01', '2026-12-31', 'case_created_at'
        );
        $this->assertSame($casesOverTime['labels'], $payload['caseTrends']['labels']);
        $this->assertSame(
            array_map('intval', $casesOverTime['datasets'][0]['data']),
            array_map('intval', $payload['caseTrends']['data'])
        );

        $referralPayload = $this->exports->buildPdfPayloadFromCriteria(
            $this->criteriaFor($manager, ['dateScope' => 'referral_created_at'])
        );
        $referralTrends = $this->reports->getReferralTrends(
            $manager->id, UserRole::CASE_MANAGER->value, '2026-01-01', '2026-12-31', 'referral_created_at'
        );
        $this->assertSame($referralTrends['labels'], $referralPayload['referralTrends']['labels']);
        $this->assertSame(
            array_map('intval', $referralTrends['datasets'][0]['data']),
            array_map('intval', $referralPayload['referralTrends']['data'])
        );
    }
}
