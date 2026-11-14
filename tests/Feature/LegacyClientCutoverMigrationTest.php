<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyClientCutoverMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutover_migration_guards_all_legacy_data_before_dropping_and_preserves_referral_categories(): void
    {
        $migration = File::get(database_path('migrations/2026_11_10_070000_drop_legacy_client_case_schema.php'));
        $guardEnd = strpos($migration, 'Schema::table(\'cases\'');

        $this->assertNotFalse($guardEnd);
        $this->assertLessThan($guardEnd, strpos($migration, 'DB::table($table)->exists()'));
        $this->assertLessThan($guardEnd, strpos($migration, 'DB::table($table)->whereNotNull($column)->exists()'));
        $this->assertStringContainsString("throw new RuntimeException('Legacy client cutover refused:", $migration);
        $this->assertStringContainsString("'case_category', 'client_addresses'", $migration);
        $dropForeignAt = strpos($migration, "dropForeign(['client_id'])");
        $dropPolicyTablesAt = strpos($migration, "foreach (['case_category', 'client_addresses'");
        $dropColumnsAt = strpos($migration, "dropColumn(['client_id', 'category_id', 'case_issue_id'])");
        $this->assertLessThan($dropPolicyTablesAt, $dropForeignAt);
        $this->assertLessThan($dropColumnsAt, $dropPolicyTablesAt);
        foreach (['case_category', 'client_addresses', 'client_employments', 'next_of_kin', 'clients', 'case_issues'] as $table) {
            $this->assertStringContainsString("'{$table}'", $migration);
        }
        foreach (['client_id', 'category_id', 'case_issue_id'] as $column) {
            $this->assertStringContainsString("dropForeign(['{$column}'])", $migration);
        }
        $this->assertStringContainsString("dropForeign(['client_id'])", $migration);
        $this->assertStringNotContainsString("Schema::dropIfExists('case_categories')", $migration);
        $this->assertStringNotContainsString("Schema::dropIfExists('case_category_agency')", $migration);
        $this->assertStringNotContainsString("dropConstrainedForeignId('category_id')", $migration);
    }

    public function test_migrated_schema_drops_legacy_tables_and_links_but_keeps_referral_category_data(): void
    {
        foreach (['case_category', 'client_addresses', 'client_employments', 'next_of_kin', 'clients', 'case_issues'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Legacy table {$table} remains.");
        }

        foreach ([['cases', 'client_id'], ['cases', 'category_id'], ['cases', 'case_issue_id'], ['users', 'client_id']] as [$table, $column]) {
            $this->assertFalse(Schema::hasColumn($table, $column), "Legacy column {$table}.{$column} remains.");
        }

        $this->assertTrue(Schema::hasTable('case_categories'));
        $this->assertTrue(Schema::hasTable('case_category_agency'));
        $this->assertTrue(Schema::hasColumn('referrals', 'category_id'));
    }

    public function test_cutover_migration_is_explicitly_irreversible(): void
    {
        $migration = File::get(database_path('migrations/2026_11_10_070000_drop_legacy_client_case_schema.php'));

        $this->assertStringContainsString('public function down(): void', $migration);
        $this->assertStringContainsString('This migration is irreversible', $migration);
    }
}
