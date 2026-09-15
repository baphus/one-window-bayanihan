<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Template for future reference-data seeds (fix plan Task 4.1 / finding 8).
 *
 * Reference rows (statuses, categories, settings) must live in seeders — never
 * in migrations — so deploys stay replayable and environments stay in sync.
 * Add new reference rows here, or in a dedicated seeder called from here, using
 * idempotent updateOrInsert() keyed on the natural key, then run
 * `php artisan db:seed --class=ReferenceDataSeeder`.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Example (kept as documentation; updateOrInsert is safe to re-run):
        // DB::table('system_settings')->updateOrInsert(
        //     ['key' => 'example_key'],
        //     ['value' => 'example_value', 'updated_at' => now(), 'created_at' => now()],
        // );
    }
}
