<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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

    /**
     * Published client for the select picker: linked account (clients.user_id
     * is unique — one account per client) plus a live OPEN case.
     */
    private function makePublishedClient(array $attributes = []): Client
    {
        $client = Client::factory()->create(array_merge(
            ['is_deleted' => false, 'user_id' => User::factory()->create()->id],
            $attributes,
        ));
        CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'OPEN',
        ]);

        return $client;
    }

    public function test_search_returns_all_clients_without_query(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->makePublishedClient();
        $this->makePublishedClient();
        $this->makePublishedClient();

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(3, 'data');
    }

    public function test_search_filters_by_first_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $juan = $this->makePublishedClient(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $this->makePublishedClient(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $response = $this->actingAs($user)->getJson('/api/clients?q=juan');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', $juan->first_name);
    }

    public function test_search_filters_by_last_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->makePublishedClient(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $maria = $this->makePublishedClient(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $response = $this->actingAs($user)->getJson('/api/clients?q=cruz');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            // 'cruz' matches Juan Dela Cruz, not Maria Santos.
            ->assertJsonPath('data.0.last_name', 'Dela Cruz');
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'is_deleted' => false]);
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson('/api/clients?q=xxxxx');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_excludes_soft_deleted_clients(): void
    {
        Client::factory()->create(['first_name' => 'Juan', 'is_deleted' => true]);
        $this->makePublishedClient(['first_name' => 'Maria']);
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Maria');
    }

    public function test_search_response_structure(): void
    {
        $this->makePublishedClient([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'MALE',
        ]);

        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'middle_name',
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
        $client = $this->makePublishedClient();
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

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
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson('/api/clients/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_show_includes_related_models_when_present(): void
    {
        $client = $this->makePublishedClient();

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

        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson("/api/clients/{$client->id}");

        $response->assertOk()
            // Stored region names resolve to PSGC codes; region_name keeps the
            // stored text. 'Region X' only fell through as-is before the
            // resolver learned the "Region X" prefix of "Region X (Northern
            // Mindanao)".
            ->assertJsonPath('data.addresses.0.region', '1000000000')
            ->assertJsonPath('data.addresses.0.region_name', 'Region X')
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
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $client = $this->makePublishedClient();
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
        for ($i = 0; $i < 25; $i++) {
            $this->makePublishedClient();
        }
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(20, 'data');
    }

    public function test_search_orders_by_created_at_desc(): void
    {
        $older = $this->makePublishedClient([
            'first_name' => 'Older',
            'created_at' => now()->subDay(),
        ]);
        $newer = $this->makePublishedClient([
            'first_name' => 'Newer',
            'created_at' => now(),
        ]);

        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Newer', $data[0]['first_name']);
        $this->assertEquals('Older', $data[1]['first_name']);
    }
}
