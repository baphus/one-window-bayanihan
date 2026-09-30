<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'client_id' => Client::factory()->create()->id,
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
    public function export_payload_contains_the_new_screen_sections(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
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
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
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
    public function overdue_labels_name_the_windowed_definition(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
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
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agency = Agency::factory()->create();
        $this->seedCase($manager, $agency,
            ['created_at' => '2026-02-10 08:00:00', 'updated_at' => '2026-02-10 08:00:00'],
            ['created_at' => '2026-02-11 08:00:00', 'updated_at' => '2026-02-11 08:00:00']);
        $this->seedCase($manager, $agency,
            ['created_at' => '2026-03-05 08:00:00', 'updated_at' => '2026-03-05 08:00:00'],
            ['created_at' => '2026-03-06 08:00:00', 'updated_at' => '2026-03-06 08:00:00']);

        $payload = $this->exports->buildPdfPayloadFromCriteria($this->criteriaFor($manager));

        $casesOverTime = $this->reports->getCasesOverTime(
            $manager->id, 'CASE_MANAGER', '2026-01-01', '2026-12-31', 'case_created_at'
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
            $manager->id, 'CASE_MANAGER', '2026-01-01', '2026-12-31', 'referral_created_at'
        );
        $this->assertSame($referralTrends['labels'], $referralPayload['referralTrends']['labels']);
        $this->assertSame(
            array_map('intval', $referralTrends['datasets'][0]['data']),
            array_map('intval', $referralPayload['referralTrends']['data'])
        );
    }
}
