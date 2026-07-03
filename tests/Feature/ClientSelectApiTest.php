<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSelectApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_search_returns_all_clients_without_query(): void
    {
        Client::factory()->count(3)->create(['is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(3, 'data');
    }

    public function test_search_filters_by_first_name(): void
    {
        Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'is_deleted' => false]);
        Client::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients?q=juan');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Juan');
    }

    public function test_search_filters_by_last_name(): void
    {
        Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'is_deleted' => false]);
        Client::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients?q=cruz');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Dela Cruz');
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients?q=xxxxx');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_excludes_soft_deleted_clients(): void
    {
        Client::factory()->create(['first_name' => 'Juan', 'is_deleted' => true]);
        Client::factory()->create(['first_name' => 'Maria', 'is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Maria');
    }

    public function test_search_response_structure(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'MALE',
            'is_deleted' => false,
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'middle_initial',
                        'suffix',
                        'sex',
                        'date_of_birth',
                        'avatar_url',
                        'case_file',
                    ],
                ],
            ]);
    }

    public function test_show_returns_full_client_detail(): void
    {
        $client = Client::factory()->create(['is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson("/api/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'sex',
                    'date_of_birth',
                    'email',
                    'contact_number',
                    'avatar_url',
                    'addresses',
                    'employments',
                    'nextOfKin',
                    'case_file',
                ],
            ])
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.first_name', $client->first_name);
    }

    public function test_show_returns_404_for_nonexistent_client(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_show_includes_related_models_when_present(): void
    {
        $client = Client::factory()->create(['is_deleted' => false]);

        // Add related data
        $client->addresses()->create([
            'region' => 'Region X',
            'province' => 'Province A',
            'city_municipality' => 'City A',
            'barangay' => 'Barangay A',
            'street' => '123 Street',
        ]);

        $client->employments()->create([
            'employer_name' => 'ACME Corp',
            'position' => 'Worker',
            'last_country' => 'SAUDI ARABIA',
        ]);

        $client->nextOfKin()->create([
            'first_name' => 'Maria',
            'last_name' => 'Dela Cruz',
            'relationship' => 'SPOUSE',
            'phone_number' => '09170000000',
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson("/api/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('data.addresses.0.region', 'Region X')
            ->assertJsonPath('data.addresses.0.province', 'Province A')
            ->assertJsonPath('data.employments.0.employer_name', 'ACME Corp')
            ->assertJsonPath('data.employments.0.last_country', 'SAUDI ARABIA')
            ->assertJsonPath('data.nextOfKin.0.first_name', 'Maria')
            ->assertJsonPath('data.nextOfKin.0.relationship', 'SPOUSE');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/clients');

        $response->assertUnauthorized();
    }

    public function test_show_returns_case_file_when_present(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create(['is_deleted' => false]);
        $caseFile = CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('data.case_file.case_number', $caseFile->case_number);
    }

    public function test_search_limits_to_20_results(): void
    {
        Client::factory()->count(25)->create(['is_deleted' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(20, 'data');
    }

    public function test_search_orders_by_created_at_desc(): void
    {
        $older = Client::factory()->create([
            'first_name' => 'Older',
            'is_deleted' => false,
            'created_at' => now()->subDay(),
        ]);
        $newer = Client::factory()->create([
            'first_name' => 'Newer',
            'is_deleted' => false,
            'created_at' => now(),
        ]);

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Newer', $data[0]['first_name']);
        $this->assertEquals('Older', $data[1]['first_name']);
    }
}
