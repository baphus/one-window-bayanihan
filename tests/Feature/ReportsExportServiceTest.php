<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportsExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportsExportService::class);
    }

    #[Test]
    public function invalid_dates_return_redirect_response(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $request = $this->requestFor($user, ['from' => '2026-99-99', 'to' => '2026-12-31']);

        $result = $this->service->buildExcelSheets($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertNotEmpty($result->getSession()->get('error'));
    }

    #[Test]
    public function malformed_export_filter_is_rejected_by_the_report_export_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('reports.export-excel', [
            'from' => 'not-a-date',
            'to' => '2026-12-31',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function reports_export_endpoint_returns_the_expected_xlsx_workbook_headers(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('reports.export-excel', [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );

        $file = tempnam(sys_get_temp_dir(), 'reports-xlsx-');
        ob_start();
        $response->sendContent();
        file_put_contents($file, ob_get_clean());
        $workbook = IOFactory::load($file);

        $this->assertSame(['Report Info', 'Executive Summary'], [
            $workbook->getSheet(0)->getTitle(),
            $workbook->getSheet(1)->getTitle(),
        ]);
        // Employment fields belong to the case/client/referral and admin
        // workbooks, not the aggregate Reports workbook.
        $this->assertNotContains('Client_employments', $workbook->getSheetNames());
        $this->assertNotContains('Previous Country', $workbook->getSheetByName('Report Info')->toArray()[0]);
        $this->assertNotContains('Work Position', $workbook->getSheetByName('Report Info')->toArray()[0]);
        $this->assertSame('Metric', $workbook->getSheetByName('Report Info')->getCell('A1')->getValue());
        $this->assertSame('Value', $workbook->getSheetByName('Report Info')->getCell('B1')->getValue());
        $this->assertSame('Metric', $workbook->getSheetByName('Executive Summary')->getCell('A1')->getValue());
        $this->assertSame('Value', $workbook->getSheetByName('Executive Summary')->getCell('B1')->getValue());

        @unlink($file);
    }

    #[Test]
    public function exactly_two_year_date_range_is_allowed_but_longer_range_redirects(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);

        $allowed = $this->service->buildExcelSheets($this->requestFor($user, [
            'from' => '2024-02-29',
            'to' => '2026-02-28',
        ]));
        $this->assertIsArray($allowed);

        $rejected = $this->service->buildExcelSheets($this->requestFor($user, [
            'from' => '2024-02-29',
            'to' => '2026-03-02',
        ]));
        $this->assertInstanceOf(RedirectResponse::class, $rejected);
    }

    #[Test]
    public function case_manager_export_details_include_all_cases(): void
    {
        [$agency] = $this->seedAgencies();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $owned = $this->caseWithReferral($manager, $agency, 'CASE-OWNED-001');
        $other = $this->caseWithReferral($otherManager, $agency, 'CASE-OTHER-001');

        $sheets = $this->service->buildExcelSheets($this->requestFor($manager));
        $caseNumbers = $this->sheetRows($sheets, 'Referral Details')->pluck('case_number');

        $this->assertTrue($caseNumbers->contains($owned->case_number));
        $this->assertTrue($caseNumbers->contains($other->case_number));
    }

    #[Test]
    public function agency_export_details_are_scoped_to_agency_referrals(): void
    {
        [$agency, $otherAgency] = $this->seedAgencies();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $included = $this->caseWithReferral($manager, $agency, 'CASE-AGENCY-001');
        $excluded = $this->caseWithReferral($manager, $otherAgency, 'CASE-OTHER-AGENCY-001');

        $sheets = $this->service->buildExcelSheets($this->requestFor($agencyUser));
        $caseNumbers = $this->sheetRows($sheets, 'Referral Details')->pluck('case_number');

        $this->assertTrue($caseNumbers->contains($included->case_number));
        $this->assertFalse($caseNumbers->contains($excluded->case_number));
    }

    #[Test]
    public function agency_without_agency_id_exports_empty_details_and_summaries(): void
    {
        [$agency] = $this->seedAgencies();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->caseWithReferral($manager, $agency, 'CASE-HIDDEN-001');

        $sheets = $this->service->buildExcelSheets($this->requestFor($agencyUser));

        $this->assertCount(0, $this->sheetRows($sheets, 'Referral Details'));
        $this->assertCount(0, $this->sheetRows($sheets, 'Case Details'));
        $this->assertSame(0, (int) $this->reportInfo($sheets)['row_counts']['referral_details_matching']);
    }

    #[Test]
    public function detail_sheets_exclude_pii_and_ai_metadata(): void
    {
        [$agency] = $this->seedAgencies();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->caseWithReferral($manager, $agency, 'CASE-PII-001', [
            'summary' => 'PRIVATE CASE SUMMARY',
            'tracker_number' => 'SECRET-TRACKER',
        ], [
            'notes' => 'PRIVATE REFERRAL NOTES',
            'decision' => 'ACCEPT',
            'decision_comment' => 'PRIVATE COMMENT',
        ]);

        $sheets = $this->service->buildExcelSheets($this->requestFor($manager));
        $referralSheet = $this->sheet($sheets, 'Referral Details');
        $caseSheet = $this->sheet($sheets, 'Case Details');
        $serializedRows = json_encode([
            $this->sheetRows($sheets, 'Referral Details')->all(),
            $this->sheetRows($sheets, 'Case Details')->all(),
        ]);

        $this->assertNotContains('tracker_number', array_column($caseSheet['columnMap'], 'key'));
        $this->assertNotContains('summary', array_column($caseSheet['columnMap'], 'key'));
        $this->assertNotContains('notes', array_column($referralSheet['columnMap'], 'key'));
        $this->assertNotContains('decision_comment', array_column($referralSheet['columnMap'], 'key'));
        $this->assertStringNotContainsString('PRIVATE CASE SUMMARY', $serializedRows);
        $this->assertStringNotContainsString('SECRET-TRACKER', $serializedRows);
        $this->assertStringNotContainsString('PRIVATE REFERRAL NOTES', $serializedRows);
        $this->assertFalse($this->reportInfo($sheets)['ai_insights_included']);
    }

    #[Test]
    public function case_export_uses_secondary_pivot_categories_without_scalar_category(): void
    {
        [$agency] = $this->seedAgencies();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $secondary = CaseCategory::factory()->create(['name' => 'Secondary category']);
        $case = CaseFile::factory()->create([
            'case_number' => 'CASE-PIVOT-CATEGORY-001',
            'user_id' => $manager->id,
            'category_id' => null,
            'created_at' => CarbonImmutable::parse('2026-03-01 00:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-02 00:00:00'),
        ]);
        DB::table('case_category')->insert([
            'id' => (string) Str::uuid(),
            'case_id' => $case->id,
            'case_category_id' => $secondary->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'status' => 'PENDING',
            'required_services' => 'Assistance',
        ]);

        $sheets = $this->service->buildExcelSheets($this->requestFor($manager));
        $row = $this->sheetRows($sheets, 'Case Details')->firstWhere('case_number', $case->case_number);

        $this->assertNotNull($row);
        $this->assertSame($secondary->name, $row->category);
    }

    #[Test]
    public function pdf_top_referrals_are_limited_to_active_risk_ranked_rows(): void
    {
        [$agency] = $this->seedAgencies();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $oldPending = $this->caseWithReferral($manager, $agency, 'CASE-PENDING-OLD', [], [
            'status' => 'PENDING',
            'created_at' => CarbonImmutable::parse('2026-06-15 00:00:00'),
        ]);
        $newCompliance = $this->caseWithReferral($manager, $agency, 'CASE-COMPLIANCE-NEW', [], [
            'status' => 'FOR_COMPLIANCE',
            'created_at' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        ]);
        $this->caseWithReferral($manager, $agency, 'CASE-COMPLETED', [], ['status' => 'COMPLETED']);

        $payload = $this->service->buildPdfPayload($this->requestFor($manager, ['from' => '2025-01-01', 'to' => '2027-01-01']));
        $caseNumbers = collect($payload['topReferrals'])->pluck('case_number');

        $this->assertSame($newCompliance->case_number, $caseNumbers->first());
        $this->assertTrue($caseNumbers->contains($oldPending->case_number));
        $this->assertFalse($caseNumbers->contains('CASE-COMPLETED'));
        $this->assertLessThanOrEqual(10, count($payload['topReferrals']));
    }

    #[Test]
    public function excel_detail_cap_exports_only_configured_row_limit_with_warning(): void
    {
        config(['reports.export_row_cap' => 50]);

        [$agency] = $this->seedAgencies();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'case_number' => 'CASE-CAP-001',
            'user_id' => $manager->id,
            'created_at' => CarbonImmutable::parse('2026-01-01 00:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        ]);

        $newestService = null;
        $baseTime = CarbonImmutable::parse('2026-01-01 00:00:00');
        $total = 51;
        foreach (array_chunk(range(0, $total - 1), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $service = 'Cap Row '.$i;
                $newestService = $service;
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'required_services' => $service,
                    'status' => 'PENDING',
                    'case_id' => $case->id,
                    'agcy_id' => $agency->id,
                    'is_deleted' => false,
                    'created_at' => $baseTime->addSeconds($i),
                    'updated_at' => $baseTime->addSeconds($i),
                ];
            }
            DB::table('referrals')->insert($rows);
            unset($rows);
        }

        $sheets = $this->service->buildExcelSheets($this->requestFor($manager));
        $referrals = $this->sheetRows($sheets, 'Referral Details');
        $info = $this->reportInfo($sheets);

        $this->assertCount(50, $referrals);
        $this->assertSame($newestService, $referrals->first()->required_services);
        $this->assertSame(51, (int) $info['row_counts']['referral_details_matching']);
        $this->assertSame(50, (int) $info['row_counts']['referral_details_exported']);
        $this->assertNotEmpty($info['cap_warnings']);
    }

    #[Test]
    public function pdf_metadata_reports_matching_context_without_detail_export_counts(): void
    {
        [$agency] = $this->seedAgencies();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->caseWithReferral($manager, $agency, 'CASE-PDF-META-001');

        $payload = $this->service->buildPdfPayload($this->requestFor($manager));
        $rowCounts = $payload['metadata']['row_counts'];

        $this->assertArrayHasKey('referral_details_matching', $rowCounts);
        $this->assertArrayNotHasKey('referral_details_exported', $rowCounts);
        $this->assertArrayHasKey('pdf_top_referrals_limit', $rowCounts);
        $this->assertSame([], $payload['metadata']['cap_warnings']);
    }

    private function requestFor(User $user, array $query = []): Request
    {
        $query += ['from' => '2026-01-01', 'to' => '2026-12-31'];
        $request = Request::create('/reports/export-excel', 'GET', $query);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedAgencies(): array
    {
        return [
            Agency::factory()->create(['name' => 'Agency A']),
            Agency::factory()->create(['name' => 'Agency B']),
        ];
    }

    private function caseWithReferral(User $manager, Agency $agency, string $caseNumber, array $caseAttrs = [], array $referralAttrs = []): CaseFile
    {
        $category = CaseCategory::factory()->create(['name' => 'Welfare '.$caseNumber]);
        $case = CaseFile::factory()->create(array_merge([
            'case_number' => $caseNumber,
            'user_id' => $manager->id,
            'category_id' => $category->id,
            'created_at' => CarbonImmutable::parse('2026-03-01 00:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-02 00:00:00'),
        ], $caseAttrs));

        Referral::factory()->create(array_merge([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'status' => 'PENDING',
            'required_services' => 'Assistance',
            'created_at' => CarbonImmutable::parse('2026-03-03 00:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-03-05 00:00:00'),
        ], $referralAttrs));

        return $case;
    }

    private function sheet(array $sheets, string $title): array
    {
        $sheet = collect($sheets)->firstWhere('title', $title);
        $this->assertNotNull($sheet, "Sheet {$title} was not generated.");

        return $sheet;
    }

    private function sheetRows(array $sheets, string $title)
    {
        return collect($this->sheet($sheets, $title)['rows']);
    }

    private function reportInfo(array $sheets): array
    {
        return $this->sheetRows($sheets, 'Report Info')
            ->mapWithKeys(fn ($row) => [$row['metric'] => is_string($row['value']) && str_starts_with($row['value'], '{') ? json_decode($row['value'], true) : $row['value']])
            ->all();
    }
}
