<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $otherManager;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->agency = Agency::factory()->create();
    }

    private function payloadFor(User $user, array $query = []): array
    {
        $request = Request::create('/reports/export-pdf', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return app(ReportsExportService::class)->buildPdfPayload($request);
    }

    private function makeCaseWithReferral(User $manager, ?string $province = null): CaseFile
    {
        $client = Client::factory()->create();
        if ($province) {
            ClientAddress::create([
                'client_id' => $client->id,
                'region' => 'Region VII',
                'province' => $province,
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
            ]);
        }
        $case = CaseFile::factory()->create(['user_id' => $manager->id, 'status' => 'OPEN', 'client_id' => $client->id]);
        Referral::factory()->pending()->create(['case_id' => $case->id, 'agcy_id' => $this->agency->id]);

        return $case;
    }

    #[Test]
    public function export_kpis_match_the_on_screen_report_and_case_manager_sees_all(): void
    {
        $this->makeCaseWithReferral($this->manager);
        $this->makeCaseWithReferral($this->manager);
        $this->makeCaseWithReferral($this->otherManager);

        $report = app(ReportsService::class)->getAll(userId: $this->manager->id, role: 'CASE_MANAGER');
        $payload = $this->payloadFor($this->manager);

        // Export figures are the exact same computed dataset as the screen.
        $this->assertSame($report['kpis']['totalReferrals'], $payload['kpis']['totalReferrals']);
        $this->assertSame($report['kpis']['totalCases'], $payload['kpis']['totalCases']);
        // Only this manager's two referrals — not the other manager's.
        $this->assertSame(3, $payload['kpis']['totalReferrals']);
    }

    #[Test]
    public function export_honors_the_province_filter(): void
    {
        $this->makeCaseWithReferral($this->manager, 'Cebu');
        $this->makeCaseWithReferral($this->manager, 'Bohol');

        $unfiltered = $this->payloadFor($this->manager);
        $filtered = $this->payloadFor($this->manager, ['province' => 'Cebu']);

        $this->assertSame(2, $unfiltered['kpis']['totalCases']);
        $this->assertSame(1, $filtered['kpis']['totalCases']);
    }

    #[Test]
    public function export_metadata_records_the_full_filter_set(): void
    {
        $this->makeCaseWithReferral($this->manager, 'Cebu');

        $payload = $this->payloadFor($this->manager, ['province' => 'Cebu', 'date_scope' => 'referral_created_at']);
        $filters = $payload['metadata']['filters'];

        $this->assertSame('Cebu', $filters['province']);
        $this->assertSame('referral_created_at', $filters['date_scope']);
        $this->assertArrayHasKey('from', $filters);
        $this->assertArrayHasKey('to', $filters);
    }

    #[Test]
    public function pdf_export_endpoint_returns_a_download(): void
    {
        $this->makeCaseWithReferral($this->manager);

        $response = $this->actingAs($this->manager)->get(route('reports.export-pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    #[Test]
    public function public_report_pdf_and_xlsx_endpoints_include_all_data_for_case_managers(): void
    {
        $other = User::factory()->create(['role' => 'CASE_MANAGER']);
        $mine = $this->makeCaseWithReferral($this->manager);
        $theirs = $this->makeCaseWithReferral($other);

        $minePdf = $this->actingAs($this->manager)->get(route('reports.export-pdf'));
        $otherPdf = $this->actingAs($other)->get(route('reports.export-pdf'));
        $minePdf->assertOk();
        $otherPdf->assertOk();
        $this->assertStringContainsString('application/pdf', $minePdf->headers->get('content-type'));
        $this->assertStringContainsString('application/pdf', $otherPdf->headers->get('content-type'));

        $mineWorkbook = $this->reportXlsxFor($this->manager);
        $otherWorkbook = $this->reportXlsxFor($other);
        $mineDetails = json_encode($mineWorkbook->getSheetByName('Referral Details')->toArray());
        $otherDetails = json_encode($otherWorkbook->getSheetByName('Referral Details')->toArray());

        $this->assertStringContainsString($mine->case_number, $mineDetails);
        $this->assertStringContainsString($theirs->case_number, $mineDetails);
        $this->assertStringContainsString($theirs->case_number, $otherDetails);
        $this->assertStringContainsString($mine->case_number, $otherDetails);
    }

    #[Test]
    public function all_client_case_and_referral_workbooks_have_decrypted_employment_fields_and_unscoped_case_manager(): void
    {
        $other = User::factory()->create(['role' => 'CASE_MANAGER']);
        $owned = $this->makeCaseWithReferral($this->manager);
        $otherCase = $this->makeCaseWithReferral($other);

        foreach ([[$owned, 'Canada', 'Welder'], [$otherCase, 'Japan', 'Engineer']] as [$case, $country, $position]) {
            ClientEmployment::create([
                'client_id' => $case->client_id,
                'country' => $country,
                'position' => $position,
                'last_country' => $country,
                'last_position' => $position,
            ]);
        }

        foreach (['cases.export-excel', 'clients.export-excel', 'referrals.export-excel'] as $endpoint) {
            $ownedWorkbook = $this->xlsxFor($this->manager, $endpoint);
            $otherWorkbook = $this->xlsxFor($other, $endpoint);

            foreach ([$ownedWorkbook, $otherWorkbook] as $workbook) {
                $sheet = $workbook->getActiveSheet();
                $headers = array_map('strval', $sheet->rangeToArray('A1:ZZ1')[0]);
                $this->assertContains('Previous Country', $headers, $endpoint);
                $this->assertContains('Work Position', $headers, $endpoint);
            }

            $ownedRows = $ownedWorkbook->getActiveSheet()->toArray();
            $otherRows = $otherWorkbook->getActiveSheet()->toArray();
            $this->assertStringContainsString('Canada', json_encode($ownedRows), $endpoint);
            $this->assertStringContainsString('Welder', json_encode($ownedRows), $endpoint);
            $this->assertStringContainsString('Japan', json_encode($ownedRows), $endpoint);
            $this->assertStringContainsString('Japan', json_encode($otherRows), $endpoint);
            $this->assertStringContainsString('Engineer', json_encode($otherRows), $endpoint);
            $this->assertStringContainsString('Canada', json_encode($otherRows), $endpoint);
        }
    }

    #[Test]
    public function admin_full_data_export_includes_a_decrypted_client_employments_workbook(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $client = Client::factory()->create();
        ClientEmployment::create([
            'client_id' => $client->id,
            'country' => 'Canada',
            'position' => 'Welder',
            'last_country' => 'Canada',
            'last_position' => 'Welder',
        ]);

        $workbook = $this->xlsxFor($admin, 'admin.data-export.export');
        $sheet = $workbook->getSheetByName('Client_employments');
        $this->assertNotNull($sheet);
        $rows = $sheet->toArray();
        $headers = array_map('strval', $rows[0]);

        $this->assertContains('Position', $headers);
        $this->assertContains('Last Position', $headers);
        $this->assertContains('Country', $headers);
        $this->assertContains('Last Country', $headers);
        $this->assertStringContainsString('Welder', json_encode($rows));
        $this->assertStringContainsString('Canada', json_encode($rows));
        $this->assertStringNotContainsString('eyJ', json_encode($rows));
    }

    private function xlsxFor(User $user, string $endpoint)
    {
        $response = $this->actingAs($user)->get(route($endpoint));
        $response->assertOk();

        $file = tempnam(sys_get_temp_dir(), 'report-workbook-');
        ob_start();
        $response->sendContent();
        file_put_contents($file, ob_get_clean());
        $workbook = IOFactory::load($file);
        @unlink($file);

        return $workbook;
    }

    private function reportXlsxFor(User $user)
    {
        return $this->xlsxFor($user, 'reports.export-excel');
    }
}
