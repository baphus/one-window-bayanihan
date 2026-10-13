<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Thin service-layer tests for ClientProfileService: user linkage, null user,
 * address sync behavior.
 */
class ClientProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClientProfileService::class);
    }

    public function test_create_client_links_user(): void
    {
        $user = User::factory()->create();
        $client = $this->service->createClient([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'juan@example.test',
        ], $user->id, 'OFW');

        $this->assertEquals($user->id, $client->user_id);
        $this->assertEquals('OFW', $client->type);
        $this->assertEquals('Juan', $client->first_name);
        $this->assertEquals('juan@example.test', $client->email);
    }

    public function test_create_client_without_user_has_null_user_id(): void
    {
        $client = $this->service->createClient([
            'first_name' => 'Walk-in',
            'last_name' => 'Client',
        ], null, 'NEXT_OF_KIN');

        $this->assertNull($client->user_id);
        $this->assertEquals('NEXT_OF_KIN', $client->type);
    }

    public function test_sync_address_replaces_existing(): void
    {
        $client = Client::factory()->create();

        $this->service->syncAddress($client, [
            'region' => 'Region VII (Central Visayas)',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
            'street' => 'First Street',
        ]);
        $this->assertEquals('First Street', $client->addresses()->first()->street);

        $this->service->syncAddress($client, [
            'region' => 'National Capital Region',
            'province' => 'Metro Manila',
            'city_municipality' => 'Manila',
            'barangay' => 'Barangay 1',
            'street' => 'Second Street',
        ]);

        $this->assertCount(1, $client->addresses()->get());
        $this->assertEquals('Second Street', $client->addresses()->first()->street);
        $this->assertEquals('National Capital Region', $client->addresses()->first()->region);
    }
}
