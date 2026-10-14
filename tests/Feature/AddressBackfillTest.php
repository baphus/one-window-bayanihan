<?php

namespace Tests\Feature;

use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the PSGC code backfill and, importantly, the resolver it depends on.
 *
 * PhilippineAddressService::resolve() had no test coverage, which is how it
 * went unnoticed that the Dockerfile deleted the lookup table it reads,
 * leaving it silently returning its input inside every container.
 */
class AddressBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_maps_known_psgc_codes_to_names(): void
    {
        $resolver = app(PhilippineAddressService::class);

        // Region VII is stable reference data. If this fails, the lookup table is
        // missing — the exact condition that produced raw codes in the database.
        $this->assertSame('Region VII (Central Visayas)', $resolver->resolve('0700000000'));
    }

    public function test_resolver_returns_the_input_for_an_unknown_code(): void
    {
        // Documents the fallback that made the original failure invisible: an
        // unresolvable code comes back unchanged, indistinguishable from success.
        $resolver = app(PhilippineAddressService::class);

        $this->assertSame('9999999999', $resolver->resolve('9999999999'));
    }

    public function test_backfill_replaces_codes_with_names_and_leaves_names_alone(): void
    {
        $codeClientId = (string) Str::uuid();
        $correctClientId = (string) Str::uuid();
        DB::table('clients')->insert([
            [
                'id' => $codeClientId,
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $correctClientId,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // One row written while the resolver was broken, one already correct.
        // One live address per client (enforced by
        // client_addresses_one_live_per_client), so each row belongs to its own client.
        DB::table('client_addresses')->insert([
            [
                'id' => (string) Str::uuid(),
                'client_id' => $codeClientId,
                'region' => '0700000000',
                'city_municipality' => '0730600000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'client_id' => $correctClientId,
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

    private function runBackfill(): void
    {
        $resolver = app(PhilippineAddressService::class);

        // Guard: prove the lookup table is readable before touching data. Region
        // 07 (Central Visayas) is stable reference data.
        $probe = $resolver->resolve('0700000000');
        if ($probe === '0700000000' || $probe === '') {
            throw new RuntimeException(
                'AddressNameResolver cannot resolve PSGC codes — '
                .resource_path('js/data/philippine-addresses.json')
                .' (or its .ts fallback) is missing or unreadable. Refusing to run: this migration would '
                .'report success while changing nothing. Ensure the image retains '
                .'resources/js/data.'
            );
        }

        $repaired = 0;
        $unresolved = [];

        $columns = ['region', 'province', 'city_municipality', 'barangay'];
        $table = 'client_addresses';

        foreach ($columns as $column) {
            $rows = DB::table($table)
                ->select('id', $column)
                ->whereNotNull($column)
                ->whereRaw("{$column} ~ '^[0-9]{9,10}$'")
                ->get();

            foreach ($rows as $row) {
                $code = (string) $row->{$column};
                $name = $resolver->resolve($code);

                // resolve() returns the input when a code is unknown. Leave the
                // value alone and record it rather than writing the code back.
                if ($name === $code || $name === '') {
                    $unresolved[] = "{$table}.{$column}={$code}";

                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([$column => $name]);
                $repaired++;
            }
        }

        // Live-ish diagnostics (mirror the migration's output) so the test
        // behaviour is consistent with the original migration up().
        if ($repaired > 0) {
            echo "  Address backfill: repaired {$repaired} value(s).\n";
        }

        if ($unresolved !== []) {
            $unique = array_values(array_unique($unresolved));
            echo '  Left unchanged (code not in the lookup table): '
                .implode(', ', array_slice($unique, 0, 10))
                .(count($unique) > 10 ? ' and '.(count($unique) - 10).' more' : '')
                ."\n";
        }
    }
}
