<?php

namespace Tests\Feature\Export;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The export dialog shows a live "Approximately N rows will be exported"
 * preview fetched from these endpoints. Each must mirror the filter handling
 * of its matching exportExcel route, so the count always equals the download.
 */
class PageExportCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    #[Test]
    public function count_endpoints_require_authentication(): void
    {
        foreach ([
            'referrals.export-count',
            'cases.export-count',
            'clients.export-count',
        ] as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertStatus(302);
            $response->assertRedirectContains('login');
        }
    }

    // -------------------------------------------------------------------------
    // Referrals
    // -------------------------------------------------------------------------

    #[Test]
    public function referrals_count_respects_status_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'status' => 'PENDING']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'status' => 'PROCESSING']);

        $response = $this->actingAs($user)->getJson(route('referrals.export-count', ['status' => 'PENDING']));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    #[Test]
    public function referrals_count_respects_date_range(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create();
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(60),
        ]);
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($user)->getJson(route('referrals.export-count', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    #[Test]
    public function agency_sees_only_its_own_referral_count(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $ownAgency->id]);
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $ownAgency->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $otherAgency->id]);

        $response = $this->actingAs($user)->getJson(route('referrals.export-count'));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    // -------------------------------------------------------------------------
    // Cases
    // -------------------------------------------------------------------------

    #[Test]
    public function cases_count_respects_status_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        CaseFile::factory()->create(['status' => 'OPEN']);
        CaseFile::factory()->create(['status' => 'CLOSED']);

        $response = $this->actingAs($user)->getJson(route('cases.export-count', ['status' => 'OPEN']));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    #[Test]
    public function cases_count_respects_next_of_kin_client_type_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        CaseFile::factory()->create(['client_type' => CaseFile::CLIENT_TYPE_OFW]);
        CaseFile::factory()->create(['client_type' => CaseFile::CLIENT_TYPE_NEXT_OF_KIN]);

        $response = $this->actingAs($user)->getJson(route('cases.export-count', [
            'client_type' => CaseFile::CLIENT_TYPE_NEXT_OF_KIN,
        ]));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    // -------------------------------------------------------------------------
    // Clients
    // -------------------------------------------------------------------------

    #[Test]
    public function clients_count_respects_search_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        Client::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $response = $this->actingAs($user)->getJson(route('clients.export-count', ['search' => 'Maria']));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    #[Test]
    public function clients_count_respects_sex_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        Client::factory()->create(['sex' => 'MALE']);
        Client::factory()->create(['sex' => 'FEMALE']);

        $response = $this->actingAs($user)->getJson(route('clients.export-count', ['sex' => 'MALE']));

        $response->assertOk()->assertJson(['count' => 1]);
    }

    #[Test]
    public function clients_count_respects_next_of_kin_client_type_filter(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $ofw = Client::factory()->create();
        $nextOfKin = Client::factory()->create();
        CaseFile::factory()->create([
            'client_id' => $ofw->id,
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
        ]);
        CaseFile::factory()->create([
            'client_id' => $nextOfKin->id,
            'client_type' => CaseFile::CLIENT_TYPE_NEXT_OF_KIN,
        ]);

        $response = $this->actingAs($user)->getJson(route('clients.export-count', [
            'client_type' => CaseFile::CLIENT_TYPE_NEXT_OF_KIN,
        ]));

        $response->assertOk()->assertJson(['count' => 1]);
    }
}
