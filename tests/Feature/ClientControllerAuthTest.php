<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, SetPostgresSession::class]);
    }

    private function mfaUser(string $role, array $attributes = []): User
    {
        return User::factory()->mfaEnabled()->create([
            'role' => $role,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            ...$attributes,
        ]);
    }

    public function test_case_manager_can_view_own_client(): void
    {
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $client = Client::factory()->create(['user_id' => User::factory()->create()->id]);
        CaseFile::factory()->create(['user_id' => $manager->id, 'client_id' => $client->id, 'status' => 'OPEN']);

        $this->actingAsWithMfa($manager)->get(route('clients.show', $client))->assertOk();
    }

    public function test_case_manager_can_view_any_client(): void
    {
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $otherManager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $client = Client::factory()->create(['user_id' => User::factory()->create()->id]);
        CaseFile::factory()->create(['user_id' => $otherManager->id, 'client_id' => $client->id, 'status' => 'OPEN']);

        $this->actingAsWithMfa($manager)->get(route('clients.show', $client))->assertOk();
    }

    public function test_admin_can_view_any_client(): void
    {
        $admin = $this->mfaUser(UserRole::ADMIN->value);
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $client = Client::factory()->create(['user_id' => User::factory()->create()->id]);
        CaseFile::factory()->create(['user_id' => $manager->id, 'client_id' => $client->id, 'status' => 'OPEN']);

        $this->actingAsWithMfa($admin)->get(route('clients.show', $client))->assertOk();
    }

    public function test_admin_can_view_client_without_case_file(): void
    {
        $admin = $this->mfaUser(UserRole::ADMIN->value);
        $client = Client::factory()->create();

        $this->actingAsWithMfa($admin)->get(route('clients.show', $client))->assertOk();
    }

    public function test_case_manager_can_view_client_without_case_file(): void
    {
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $client = Client::factory()->create(['user_id' => $manager->id]);

        $this->actingAsWithMfa($manager)->get(route('clients.show', $client))->assertOk();
    }

    public function test_agency_can_view_client_with_referral_to_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = $this->mfaUser(UserRole::AGENCY->value, ['agcy_id' => $agency->id]);
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['user_id' => $manager->id, 'client_id' => $client->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->actingAsWithMfa($agencyUser)->get(route('clients.show', $client))->assertOk();
    }

    public function test_agency_gets_404_for_client_without_referral_to_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = $this->mfaUser(UserRole::AGENCY->value, ['agcy_id' => $agency->id]);
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['user_id' => $manager->id, 'client_id' => $client->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $otherAgency->id]);

        $this->actingAsWithMfa($agencyUser)->get(route('clients.show', $client))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $client = Client::factory()->create();

        $this->get(route('clients.show', $client))->assertRedirect(route('login'));
    }

    public function test_case_manager_sees_all_clients_in_index(): void
    {
        $manager = $this->mfaUser(UserRole::CASE_MANAGER->value);
        $first = Client::factory()->create(['first_name' => 'Ava']);
        $second = Client::factory()->create(['first_name' => 'Bea']);
        CaseFile::factory()->create(['user_id' => $manager->id, 'client_id' => $first->id, 'status' => 'OPEN']);
        CaseFile::factory()->create(['user_id' => $manager->id, 'client_id' => $second->id, 'status' => 'OPEN']);

        $this->actingAsWithMfa($manager)->get(route('clients.index'))->assertOk();
    }
}
