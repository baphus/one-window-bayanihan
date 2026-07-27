<?php

namespace Tests\Feature;

use App\Services\AddressNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the PSGC code backfill and, importantly, the resolver it depends on.
 *
 * AddressNameResolver had no test coverage, which is how it went unnoticed that
 * the Dockerfile deleted the lookup table it reads, leaving it silently returning
 * its input inside every container.
 */
class AddressBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_maps_known_psgc_codes_to_names(): void
    {
        $resolver = app(AddressNameResolver::class);

        // Region VII is stable reference data. If this fails, the lookup table is
        // missing — the exact condition that produced raw codes in the database.
        $this->assertSame('Region VII (Central Visayas)', $resolver->resolve('0700000000'));
    }

    public function test_resolver_returns_the_input_for_an_unknown_code(): void
    {
        // Documents the fallback that made the original failure invisible: an
        // unresolvable code comes back unchanged, indistinguishable from success.
        $resolver = app(AddressNameResolver::class);

        $this->assertSame('9999999999', $resolver->resolve('9999999999'));
    }

    public function test_backfill_replaces_codes_with_names_and_leaves_names_alone(): void
    {
        $clientId = (string) Str::uuid();
        DB::table('clients')->insert([
            'id' => $clientId,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // One row written while the resolver was broken, one already correct.
        DB::table('client_addresses')->insert([
            [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'region' => '0700000000',
                'city_municipality' => '0730600000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'region' => 'Region VII (Central Visayas)',
                'city_municipality' => 'City of Cebu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->runBackfill();

        // Neither row should still contain a bare code.
        foreach (DB::table('client_addresses')->pluck('region')->all() as $region) {
            $this->assertDoesNotMatchRegularExpression(
                '/^[0-9]{9,10}$/',
                (string) $region,
                'A PSGC code survived the backfill.'
            );
        }

        // The already-correct row is untouched, so the backfill is safe to re-run.
        $this->assertSame(2, DB::table('client_addresses')
            ->where('region', 'Region VII (Central Visayas)')
            ->count());
    }

    public function test_backfill_is_idempotent(): void
    {
        $clientId = (string) Str::uuid();
        DB::table('clients')->insert([
            'id' => $clientId,
            'first_name' => 'Rosa',
            'last_name' => 'Santos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_addresses')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $clientId,
            'region' => '0700000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runBackfill();
        $afterFirst = DB::table('client_addresses')->value('region');

        $this->runBackfill();
        $afterSecond = DB::table('client_addresses')->value('region');

        $this->assertSame($afterFirst, $afterSecond, 'Re-running the backfill changed the value again.');
    }

    /**
     * Invokes the migration's up() directly so the behaviour is tested rather
     * than assumed from having already run during RefreshDatabase.
     */
    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_07_27_000002_backfill_address_codes_to_names.php');
        ob_start();
        $migration->up();
        ob_end_clean();
    }
}
