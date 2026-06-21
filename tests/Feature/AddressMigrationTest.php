<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AddressMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a minimal FK-compliant record chain:
     *   user → client → case
     * Returns the client UUID.
     *
     * Note: clients.case_id was dropped by migration 2026_06_02_000002
     * and replaced with cases.client_id.
     */
    private function createMinimalClient(): string
    {
        $user = User::factory()->create();

        $clientId = (string) Str::uuid();
        DB::table('clients')->insert([
            'id' => $clientId,
            'first_name' => 'Test',
            'last_name' => 'Client',
        ]);

        DB::table('cases')->insert([
            'id' => (string) Str::uuid(),
            'case_number' => 'CASE-'.Str::random(8),
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-'.Str::random(8),
            'status' => 'OPEN',
            'user_id' => $user->id,
            'client_id' => $clientId,
        ]);

        return $clientId;
    }

    private function seedPhilippineAddresses(): void
    {
        DB::table('philippine_addresses')->insert([
            ['code' => 'R01', 'name' => 'Test Region',   'type' => 'region',   'parent_code' => null],
            ['code' => 'P01', 'name' => 'Test Province', 'type' => 'province', 'parent_code' => 'R01'],
            ['code' => 'C01', 'name' => 'Test City',     'type' => 'city',     'parent_code' => 'P01'],
            ['code' => 'B01', 'name' => 'Test Barangay', 'type' => 'barangay', 'parent_code' => 'C01'],
        ]);
    }

    /**
     * Insert a row into client_addresses with PSGC codes and return its UUID.
     */
    private function insertAddressWithCodes(string $clientId): string
    {
        $id = (string) Str::uuid();

        DB::table('client_addresses')->insert([
            'id' => $id,
            'client_id' => $clientId,
            'region' => 'R01',
            'province' => 'P01',
            'city_municipality' => 'C01',
            'barangay' => 'B01',
        ]);

        return $id;
    }

    /**
     * Insert a row into next_of_kin with PSGC codes and return its UUID.
     */
    private function insertNextOfKinWithCodes(string $clientId): string
    {
        $id = (string) Str::uuid();

        DB::table('next_of_kin')->insert([
            'id' => $id,
            'client_id' => $clientId,
            'region' => 'R01',
            'province' => 'P01',
            'city_municipality' => 'C01',
            'barangay' => 'B01',
        ]);

        return $id;
    }

    // ---------------------------------------------------------------
    //  Tests
    // ---------------------------------------------------------------

    public function test_migration_converts_codes_to_names(): void
    {
        $this->seedPhilippineAddresses();
        $clientId = $this->createMinimalClient();
        $caId = $this->insertAddressWithCodes($clientId);
        $nokId = $this->insertNextOfKinWithCodes($clientId);

        // Load and run the migration's up() method.
        $migration = require database_path('migrations/2026_06_21_112449_convert_address_codes_to_names.php');
        $migration->up();

        // --- client_addresses ---
        $ca = DB::table('client_addresses')->where('id', $caId)->first();
        $this->assertNotNull($ca);
        $this->assertSame('Test Region', $ca->region);
        $this->assertSame('Test Province', $ca->province);
        $this->assertSame('Test City', $ca->city_municipality);
        $this->assertSame('Test Barangay', $ca->barangay);

        // --- next_of_kin ---
        $nok = DB::table('next_of_kin')->where('id', $nokId)->first();
        $this->assertNotNull($nok);
        $this->assertSame('Test Region', $nok->region);
        $this->assertSame('Test Province', $nok->province);
        $this->assertSame('Test City', $nok->city_municipality);
        $this->assertSame('Test Barangay', $nok->barangay);
    }

    public function test_migration_is_idempotent(): void
    {
        $this->seedPhilippineAddresses();
        $clientId = $this->createMinimalClient();
        $caId = $this->insertAddressWithCodes($clientId);
        $nokId = $this->insertNextOfKinWithCodes($clientId);

        $migration = require database_path('migrations/2026_06_21_112449_convert_address_codes_to_names.php');

        // Run twice — second pass must not throw or corrupt data.
        $migration->up();
        $migration->up();   // idempotent call

        // Data must still be correct after two passes.
        $ca = DB::table('client_addresses')->where('id', $caId)->first();
        $this->assertSame('Test Region', $ca->region);

        $nok = DB::table('next_of_kin')->where('id', $nokId)->first();
        $this->assertSame('Test Region', $nok->region);
    }

    public function test_migration_rollback_restores_codes(): void
    {
        $this->seedPhilippineAddresses();
        $clientId = $this->createMinimalClient();
        $caId = $this->insertAddressWithCodes($clientId);
        $nokId = $this->insertNextOfKinWithCodes($clientId);

        $migration = require database_path('migrations/2026_06_21_112449_convert_address_codes_to_names.php');

        // Up: codes → names
        $migration->up();

        // Confirm names applied
        $this->assertSame('Test Region', DB::table('client_addresses')->where('id', $caId)->value('region'));

        // Down: names → codes
        $migration->down();

        // --- client_addresses ---
        $ca = DB::table('client_addresses')->where('id', $caId)->first();
        $this->assertSame('R01', $ca->region);
        $this->assertSame('P01', $ca->province);
        $this->assertSame('C01', $ca->city_municipality);
        $this->assertSame('B01', $ca->barangay);

        // --- next_of_kin ---
        $nok = DB::table('next_of_kin')->where('id', $nokId)->first();
        $this->assertSame('R01', $nok->region);
        $this->assertSame('P01', $nok->province);
        $this->assertSame('C01', $nok->city_municipality);
        $this->assertSame('B01', $nok->barangay);
    }
}
