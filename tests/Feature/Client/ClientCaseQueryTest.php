<?php

namespace Tests\Feature\Client;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCaseQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    /**
     * Seed a deterministic directory: 2 OPEN (OFW/NOK) with referrals to
     * $agencyA, 1 CLOSED OFW, 1 ARCHIVED OFW, 1 unaccepted self-filed intake,
     * and 1 soft-deleted client. Returns [admin, agencyA, agencyB].
     *
     * @return array{0: User, 1: Agency, 2: Agency}
     */
    private function seedDirectory(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();

        $ofwOpen = Client::factory()->create();
        CaseFile::factory()->create([
            'client_id' => $ofwOpen->id,
            'user_id' => $manager->id,
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'PWD',
            'nok_vulnerability_indicator' => null,
        ]);

        $nokOpen = Client::factory()->create();
        $nokCase = CaseFile::factory()->create([
            'client_id' => $nokOpen->id,
            'user_id' => $manager->id,
            'client_type' => CaseFile::CLIENT_TYPE_NEXT_OF_KIN,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'Senior Citizen',
            'nok_vulnerability_indicator' => null,
        ]);

        $ofwClosed = Client::factory()->create();
        CaseFile::factory()->create([
            'client_id' => $ofwClosed->id,
            'user_id' => $manager->id,
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
            'status' => 'CLOSED',
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
        ]);

        $ofwArchived = Client::factory()->create();
        CaseFile::factory()->create([
            'client_id' => $ofwArchived->id,
            'user_id' => $manager->id,
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
            'status' => 'ARCHIVED',
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
        ]);

        // Unaccepted self-filed intake: hidden from the directory entirely.
        $intakeOnly = Client::factory()->create();
        CaseFile::factory()->create([
            'client_id' => $intakeOnly->id,
            'user_id' => null,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'status' => 'DRAFT',
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
        ]);

        // Soft-deleted client: excluded everywhere.
        $deleted = Client::factory()->create();
        $deletedCase = CaseFile::factory()->create([
            'client_id' => $deleted->id,
            'user_id' => $manager->id,
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
            'status' => 'OPEN',
        ]);
        $deleted->delete();
        $deletedCase->delete();

        Referral::factory()->create([
            'case_id' => $ofwOpen->caseFiles()->first()->id,
            'agcy_id' => $agencyA->id,
        ]);
        Referral::factory()->create([
            'case_id' => $nokCase->id,
            'agcy_id' => $agencyA->id,
        ]);

        return [$admin, $agencyA, $agencyB];
    }

    public function test_admin_stats_use_builder_with_identical_shape(): void
    {
        [$admin] = $this->seedDirectory();

        $stats = app(CaseService::class)->getClientDirectoryStats($admin);

        $this->assertSame(4, $stats['total_clients']);
        $this->assertSame(2, $stats['ofw_clients']);
        $this->assertSame(1, $stats['nok_clients']);
        $this->assertSame([
            'PWD' => 1,
            'Senior Citizen' => 1,
            'Solo Parent' => 0,
            'Indigenous Person' => 0,
        ], $stats['vulnerability_counts']);
        $this->assertSame(2, $stats['clients_with_open_cases']);
        $this->assertSame(2, $stats['total_referrals']);
    }

    public function test_client_index_serves_builder_stats(): void
    {
        [$admin] = $this->seedDirectory();

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk();
    }

    public function test_agency_stats_scoped_to_own_referrals(): void
    {
        [$admin, $agencyA, $agencyB] = $this->seedDirectory();

        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyA->id]);
        $stats = app(CaseService::class)->getClientDirectoryStats($agencyUser);

        $this->assertSame(2, $stats['total_clients']);
        $this->assertSame(1, $stats['ofw_clients']);
        $this->assertSame(1, $stats['nok_clients']);
        $this->assertSame(1, $stats['vulnerability_counts']['PWD']);
        $this->assertSame(1, $stats['vulnerability_counts']['Senior Citizen']);
        $this->assertSame(2, $stats['clients_with_open_cases']);
        $this->assertSame(2, $stats['total_referrals']);

        $otherAgencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyB->id]);
        $otherStats = app(CaseService::class)->getClientDirectoryStats($otherAgencyUser);

        $this->assertSame(0, $otherStats['total_clients']);
        $this->assertSame(0, $otherStats['total_referrals']);

        $orphanAgencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $this->assertSame([
            'total_clients' => 0,
            'ofw_clients' => 0,
            'nok_clients' => 0,
            'vulnerability_counts' => ['PWD' => 0, 'Senior Citizen' => 0, 'Solo Parent' => 0, 'Indigenous Person' => 0],
            'clients_with_open_cases' => 0,
            'total_referrals' => 0,
        ], app(CaseService::class)->getClientDirectoryStats($orphanAgencyUser));
    }

    public function test_client_controller_has_no_raw_sql_path(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));

        $this->assertStringNotContainsString('DB::selectOne', $source);
        $this->assertStringNotContainsString('DB::select(', $source);
        $this->assertStringNotContainsString('DB::raw', $source);
        $this->assertStringNotContainsString('$refSql', $source);
        $this->assertTrue(method_exists(CaseService::class, 'getClientDirectoryStats'));
        $this->assertTrue(method_exists(CaseFile::class, 'scopeLive'));
        $this->assertTrue(method_exists(CaseFile::class, 'scopeForAgency'));
    }
}
