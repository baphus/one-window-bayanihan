<?php

namespace Tests\Feature\Client;

use App\Enums\UserRole;
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
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();

        $ofwOpen = Client::factory()->create(['type' => 'OFW']);
        CaseFile::factory()->create([
            'client_id' => $ofwOpen->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'PWD',
            'nok_vulnerability_indicator' => null,
        ]);

        $nokOpen = Client::factory()->nextOfKin()->create();
        $nokCase = CaseFile::factory()->create([
            'client_id' => $nokOpen->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'Senior Citizen',
            'nok_vulnerability_indicator' => null,
        ]);

        $ofwClosed = Client::factory()->create(['type' => 'OFW']);
        CaseFile::factory()->create([
            'client_id' => $ofwClosed->id,
            'user_id' => $manager->id,
            'status' => 'CLOSED',
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
        ]);

        $ofwArchived = Client::factory()->create(['type' => 'OFW']);
        CaseFile::factory()->create([
            'client_id' => $ofwArchived->id,
            'user_id' => $manager->id,
            'status' => 'ARCHIVED',
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
        ]);

        // Pending-review clients remain visible; case status is an explicit
        // filter rather than an implicit directory exclusion.
        $intakeOnly = Client::factory()->create(['type' => 'OFW']);
        CaseFile::factory()->pendingReview()->create([
            'client_id' => $intakeOnly->id,
            'user_id' => null,
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
        ]);

        // Soft-deleted client: excluded everywhere.
        $deleted = Client::factory()->create();
        $deletedCase = CaseFile::factory()->create([
            'client_id' => $deleted->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
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

        $this->assertSame(5, $stats['total_clients']);
        $this->assertSame(4, $stats['ofw_clients']);
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

    public function test_client_index_exposes_client_type_on_each_case_file(): void
    {
        [$admin] = $this->seedDirectory();

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        // Client type is derived from the client record even when the account
        // and case have different owners.
        $ofw = Client::factory()->create([
            'type' => 'OFW',
            'user_id' => User::factory()->create(['role' => UserRole::OFW->value])->id,
        ]);
        CaseFile::factory()->create([
            'client_id' => $ofw->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);

        $nok = Client::factory()->nextOfKin()->create([
            'user_id' => User::factory()->create(['role' => UserRole::OFW->value])->id,
        ]);
        CaseFile::factory()->create([
            'client_id' => $nok->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($admin)->get(route('clients.index'));
        $response->assertOk();

        // Client/Index.jsx reads row.case_file.client_type for the type and
        // vulnerability columns; cases.client_type is gone, so the payload has
        // to carry the value derived from clients.type.
        $rows = collect($response->viewData('page')['props']['clients']['data'])->keyBy('id');

        $this->assertSame('OFW', $rows[$ofw->id]['case_file']['client_type']);
        $this->assertSame('NEXT_OF_KIN', $rows[$nok->id]['case_file']['client_type']);
    }

    public function test_agency_stats_scoped_to_own_referrals(): void
    {
        [$admin, $agencyA, $agencyB] = $this->seedDirectory();

        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agencyA->id]);
        $stats = app(CaseService::class)->getClientDirectoryStats($agencyUser);

        $this->assertSame(2, $stats['total_clients']);
        $this->assertSame(1, $stats['ofw_clients']);
        $this->assertSame(1, $stats['nok_clients']);
        $this->assertSame(1, $stats['vulnerability_counts']['PWD']);
        $this->assertSame(1, $stats['vulnerability_counts']['Senior Citizen']);
        $this->assertSame(2, $stats['clients_with_open_cases']);
        $this->assertSame(2, $stats['total_referrals']);

        $otherAgencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agencyB->id]);
        $otherStats = app(CaseService::class)->getClientDirectoryStats($otherAgencyUser);

        $this->assertSame(0, $otherStats['total_clients']);
        $this->assertSame(0, $otherStats['total_referrals']);

        $orphanAgencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);
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
