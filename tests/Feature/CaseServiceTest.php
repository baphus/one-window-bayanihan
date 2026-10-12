<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_tracker_number_format(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'generateTrackerNumber');
        $reflection->setAccessible(true);
        $number = $reflection->invoke($service);
        // Crockford base32, 10 characters. See CaseNumberGenerator for why the
        // ambiguous glyphs I, L, O and U are excluded.
        $this->assertMatchesRegularExpression('/^OWBAP-[0-9A-HJKMNP-TV-Z]{10}$/', $number);
    }

    public function test_generate_tracker_number_unique(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'generateTrackerNumber');
        $reflection->setAccessible(true);
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $reflection->invoke($service);
        }
        $this->assertCount(10, array_unique($numbers));
    }

    public function test_create_case_resolves_address_codes_to_names(): void
    {
        $service = app(CaseService::class);
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $result = $service->createCase([
            'client' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'selected_client_id' => $client->id,
            'address' => ['region' => '0700000000', 'province' => '0702200000', 'city_municipality' => '', 'barangay' => '', 'street' => '123 Main'],
        ], $user->id);

        $address = $client->addresses()->first();
        $this->assertEquals('Region VII (Central Visayas)', $address->region);
        $this->assertEquals('Cebu', $address->province);
    }
}
