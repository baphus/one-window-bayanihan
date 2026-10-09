<?php

namespace Tests\Feature\Database;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Migration-hygiene guard (fix plan Task 4.1 / finding 8).
 *
 * New migrations must use UUID primary keys, keep seed/reference data out of
 * migration files (it belongs in `database/seeders/`, see ReferenceDataSeeder),
 * and declare foreign keys on uuid-typed columns via `foreignUuid()->constrained()`.
 * Historical exceptions are allowlisted below; the allowlists must not grow.
 */
class MigrationConventionTest extends TestCase
{
    /**
     * Migrations grandfathered with `DB::table()->insert()` calls.
     * core_reference seeded statuses/settings before seeders owned them (do not
     * move retroactively); the two counter files are one-off operational
     * backfills, not reference seeds.
     */
    private const SEED_ALLOWLIST = [
        '2026_06_01_000001_create_core_reference_tables.php',
        '2026_07_27_000001_create_case_number_counters_table.php',
        '2026_07_28_000001_switch_case_number_counters_to_monthly_periods.php',
    ];

    /**
     * Migrations grandfathered with non-UUID integer primary keys (framework
     * tables plus the since-dropped philippine_addresses lookup).
     */
    private const INTEGER_PK_ALLOWLIST = [
        '0001_01_01_000000_create_framework_tables.php',
        '2026_06_01_000001_create_core_reference_tables.php',
        '2026_07_08_000001_drop_philippine_addresses_table.php',
    ];

    /**
     * @return array<string> Absolute paths of all migration files.
     */
    private function migrationFiles(): array
    {
        return glob(database_path('migrations/*.php')) ?: [];
    }

    /**
     * Return the up() side of a migration (everything before `function down`)
     * so rollbacks that faithfully recreate legacy tables are not flagged.
     */
    private function upSection(string $contents): string
    {
        $pos = strpos($contents, 'function down');

        return $pos === false ? $contents : substr($contents, 0, $pos);
    }

    #[Test]
    public function test_migrations_have_no_seed_data(): void
    {
        $violations = [];

        foreach ($this->migrationFiles() as $path) {
            if (in_array(basename($path), self::SEED_ALLOWLIST, true)) {
                continue;
            }

            if (preg_match('/->insert\s*\(|::insert\s*\(|->updateOrInsert\s*\(/', (string) file_get_contents($path))) {
                $violations[] = basename($path);
            }
        }

        $this->assertEmpty($violations, 'Seed data belongs in database/seeders/, not in migrations: '.implode(', ', $violations));
    }

    #[Test]
    public function test_new_tables_use_uuid_primary_keys(): void
    {
        $violations = [];

        foreach ($this->migrationFiles() as $path) {
            $file = basename($path);
            $contents = (string) file_get_contents($path);

            if (! in_array($file, self::INTEGER_PK_ALLOWLIST, true)
                && preg_match('/\$table->(id\s*\(|increments\s*\(|bigIncrements\s*\()/', $contents)) {
                $violations[] = $file.' uses an integer primary key instead of uuid';
            }

            $up = $this->upSection($contents);

            if (str_contains($up, 'Schema::create') && ! str_contains($up, '->primary(')) {
                $violations[] = $file.' creates a table without an explicit primary key';
            }
        }

        $this->assertEmpty($violations, 'New tables must use UUID primary keys: '.implode(', ', $violations));
    }

    #[Test]
    public function test_foreign_keys_use_uuid_style(): void
    {
        // Canonical style for new foreign keys; locks the convention in code.
        $stub = <<<'PHP'
            Schema::create('example', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('agency_id')->constrained('agencies')->restrictOnDelete();
            });
            PHP;

        $this->assertStringContainsString('foreignUuid', $stub);
        $this->assertStringContainsString('constrained', $stub);

        $files = $this->migrationFiles();

        // Every ->foreign('col') must target a uuid-declared column.
        $declared = [];
        foreach ($files as $path) {
            preg_match_all("/uuid\(\s*['\"]([a-z_]+)/", (string) file_get_contents($path), $m);
            foreach ($m[1] as $column) {
                $declared[$column] = true;
            }
        }

        $untyped = [];
        foreach ($files as $path) {
            preg_match_all("/->foreign\(\s*['\"]([a-z_]+)/", (string) file_get_contents($path), $m);
            foreach ($m[1] as $column) {
                if (! isset($declared[$column])) {
                    $untyped[] = basename($path).':'.$column;
                }
            }
        }

        $this->assertEmpty($untyped, 'Foreign keys must reference uuid-typed columns: '.implode(', ', array_unique($untyped)));

        // No integer-typed *_id columns in migrations.
        $integerFk = [];
        foreach ($files as $path) {
            if (preg_match("/\\\$table->\w*Integer\(\s*['\"][a-z_]*_id/", (string) file_get_contents($path))) {
                $integerFk[] = basename($path);
            }
        }

        $this->assertEmpty($integerFk, 'Foreign-key columns must be uuid, not integer: '.implode(', ', $integerFk));
    }
}
