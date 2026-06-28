<?php

namespace Tests\Feature;

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
        // HandleInertiaRequests requires Vite manifest in tests — disable it
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    // ─── show() tests ───────────────────────────────────────────────────────

    public function test_case_manager_can_view_own_client(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();
        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('clients.show', $client));

        $response->assertStatus(200);
    }

    public function test_case_manager_gets_404_for_another_users_client(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();
        CaseFile::factory()->create([
            'user_id' => $otherManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('clients.show', $client));

        $response->assertStatus(404);
    }

    public function test_admin_can_view_any_client(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();
        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('clients.show', $client));

        $response->assertStatus(200);
    }

    public function test_admin_can_view_client_without_case_file(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('clients.show', $client));

        $response->assertStatus(200);
    }

    public function test_agency_can_view_client_with_referral_to_their_agency(): void
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->get(route('clients.show', $client));

        $response->assertStatus(200);
    }

    public function test_agency_gets_404_for_client_without_referral_to_their_agency(): void
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $otherAgency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Other Agency',
            'short' => 'OA',
            'slug' => 'other-agency',
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $otherAgency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->get(route('clients.show', $client));

        $response->assertStatus(404);
    }

    public function test_guest_redirected_to_login(): void
    {
        $client = Client::factory()->create();

        $response = $this->get(route('clients.show', $client));

        // Route is protected by auth middleware, guest redirected to login
        $response->assertStatus(302)->assertRedirect(route('login'));
    }

    // ─── index() scoping tests ──────────────────────────────────────────────

    public function test_case_manager_sees_only_own_clients_in_index(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $ownClient = Client::factory()->create();
        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'client_id' => $ownClient->id,
            'status' => 'OPEN',
        ]);

        $otherClient = Client::factory()->create();
        CaseFile::factory()->create([
            'user_id' => $otherManager->id,
            'client_id' => $otherClient->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Client/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $ownClient->id)
        );
    }

    public function test_admin_sees_all_clients_in_index(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $manager1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        Client::factory()->create(); // No case file — admin should see it too
        $client2 = Client::factory()->create();
        CaseFile::factory()->create(['user_id' => $manager1->id, 'client_id' => $client2->id, 'status' => 'OPEN']);
        $client3 = Client::factory()->create();
        CaseFile::factory()->create(['user_id' => $manager2->id, 'client_id' => $client3->id, 'status' => 'OPEN']);

        $response = $this->actingAs($admin)
            ->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Client/Index')
            ->has('clients.data', 3)
        );
    }
}
