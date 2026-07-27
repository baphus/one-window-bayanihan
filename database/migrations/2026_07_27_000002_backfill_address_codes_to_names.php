<?php

use App\Services\AddressNameResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs address columns still holding raw PSGC codes.
 *
 * 2026_06_21_112449_convert_address_codes_to_names already did this once, by
 * joining against the philippine_addresses table. That table was then removed by
 * 2026_07_08_000001_drop_philippine_addresses_table, and lookups moved to
 * AddressNameResolver, which reads resource_path('js/data/philippine-addresses.ts').
 *
 * The Dockerfile then deleted all of resources/js during image cleanup, so inside
 * every container AddressNameResolver silently returned its input — its fallback
 * is `nameByCode()[$value] ?? $value`, indistinguishable from a successful
 * lookup. publishDraft() calls resolveAddressNames() before writing
 * client_addresses, so cases created from a container persisted codes:
 *
 *   region = 0700000000, province = 0702200000, city_municipality = 0730600000
 *
 * The Dockerfile is fixed, so new cases resolve correctly. This repairs the rows
 * written while it was broken. Covers next_of_kin as well as client_addresses,
 * since both carry the same address columns.
 *
 * Deliberately fails rather than silently doing nothing if the resolver cannot
 * read its lookup table. A no-op migration reporting success is precisely how the
 * original defect stayed hidden.
 */
return new class extends Migration
{
    /** Columns holding PSGC values, in both tables. */
    private const COLUMNS = ['region', 'province', 'city_municipality', 'barangay'];

    private const TABLES = ['client_addresses', 'next_of_kin'];

    public function up(): void
    {
        $resolver = app(AddressNameResolver::class);

        // Guard: prove the lookup table is readable before touching data. Region
        // 07 (Central Visayas) is stable reference data.
        $probe = $resolver->resolve('0700000000');
        if ($probe === '0700000000' || $probe === '') {
            throw new RuntimeException(
                'AddressNameResolver cannot resolve PSGC codes — '
                .resource_path('js/data/philippine-addresses.ts')
                .' is missing or unreadable. Refusing to run: this migration would '
                .'report success while changing nothing. Ensure the image retains '
                .'resources/js/data.'
            );
        }

        $repaired = 0;
        $unresolved = [];

        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                // Only rows that still look like a PSGC code. Names are left alone,
                // which makes this safe to re-run.
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
        }

        echo "  Address backfill: repaired {$repaired} value(s).\n";

        if ($unresolved !== []) {
            $unique = array_values(array_unique($unresolved));
            echo '  Left unchanged (code not in the lookup table): '
                .implode(', ', array_slice($unique, 0, 10))
                .(count($unique) > 10 ? ' and '.(count($unique) - 10).' more' : '')
                ."\n";
        }
    }

    /**
     * Not reversible. The original codes are not retained, and reversing would
     * mean re-deriving them from names — ambiguous, since barangay names repeat
     * across municipalities. Restoring codes is not a desirable end state anyway.
     */
    public function down(): void
    {
        // Intentionally empty.
    }
};
