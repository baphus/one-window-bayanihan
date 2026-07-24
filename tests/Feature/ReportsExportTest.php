<?php

namespace Tests\Feature;

use App\Jobs\GenerateSystemReport;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\Referral;
use App\Models\User;
use App\Services\Export\DataExportQueries;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();

        $this->makeCaseWithReferral($this->manager);

        $response = $this->actingAs($this->manager)->get(route('reports.export-pdf'));

        $response->assertOk();
        $response->assertJson(['status' => 'pending']);
        $this->assertDatabaseHas('generated_documents', [
            'id' => $response->json('id'),
            'user_id' => $this->manager->id,
            'type' => 'system_report_pdf',
            'status' => 'pending',
        ]);

        Queue::assertPushed(GenerateSystemReport::class);
    }

    #[Test]
    public function public_report_pdf_and_xlsx_endpoints_include_all_data_for_case_managers(): void
    {
        $other = User::factory()->create(['role' => 'CASE_MANAGER']);
        $mine = $this->makeCaseWithReferral($this->manager);
        $theirs = $this->makeCaseWithReferral($other);

        // Test via the service layer directly since endpoints are now async
        $service = app(ReportsExportService::class);

        $mineRequest = Request::create('/reports/export-excel', 'GET');
        $mineRequest->setUserResolver(fn () => $this->manager);
        $mineCriteria = $service->extractCriteria($mineRequest);
        $mineSheets = $service->buildExcelSheetsFromCriteria($mineCriteria);

        $otherRequest = Request::create('/reports/export-excel', 'GET');
        $otherRequest->setUserResolver(fn () => $other);
        $otherCriteria = $service->extractCriteria($otherRequest);
        $otherSheets = $service->buildExcelSheetsFromCriteria($otherCriteria);

        // Both case managers see all data (unscoped)
        $mineRefSheet = collect($mineSheets)->firstWhere('title', 'Referral Details');
        $otherRefSheet = collect($otherSheets)->firstWhere('title', 'Referral Details');

        $mineJson = json_encode($mineRefSheet['rows']->toArray());
        $otherJson = json_encode($otherRefSheet['rows']->toArray());

        $this->assertStringContainsString($mine->case_number, $mineJson);
        $this->assertStringContainsString($theirs->case_number, $mineJson);
        $this->assertStringContainsString($theirs->case_number, $otherJson);
        $this->assertStringContainsString($mine->case_number, $otherJson);
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

        // Test via service layer directly since endpoints are now async
        $queries = new DataExportQueries;

        foreach (['getCasesExport', 'getClientsExport', 'getReferralsExport'] as $method) {
            $ownedData = $queries->$method($this->manager, []);
            $otherData = $queries->$method($other, []);

            $ownedJson = json_encode($ownedData->toArray());
            $otherJson = json_encode($otherData->toArray());

            $this->assertStringContainsString('Canada', $ownedJson, $method);
            $this->assertStringContainsString('Welder', $ownedJson, $method);
            $this->assertStringContainsString('Japan', $ownedJson, $method);
            $this->assertStringContainsString('Japan', $otherJson, $method);
            $this->assertStringContainsString('Engineer', $otherJson, $method);
            $this->assertStringContainsString('Canada', $otherJson, $method);
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

        // Test the export endpoint returns async response
        $response = $this->actingAs($admin)->get(route('admin.data-export.export'));
        $response->assertOk();
        $response->assertJson(['status' => 'pending']);

        // Verify employment data via the queries layer directly
        $queries = new DataExportQueries;
        $employments = $queries->getClientEmployments($admin);
        $json = json_encode($employments->toArray());

        $this->assertStringContainsString('Welder', $json);
        $this->assertStringContainsString('Canada', $json);
        $this->assertStringNotContainsString('eyJ', $json);
    }
}
