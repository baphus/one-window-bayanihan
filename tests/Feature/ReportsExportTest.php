<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
    public function export_kpis_match_the_on_screen_report_and_are_role_scoped(): void
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
        $this->assertSame(2, $payload['kpis']['totalReferrals']);
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
}
