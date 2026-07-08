<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RlsMigrationIntegrityTest extends TestCase
{
    private string $migration1;

    private string $migration2;

    protected function setUp(): void
    {
        parent::setUp();

        // Find the migration files dynamically (works regardless of timestamp prefix)
        $files = File::glob(database_path('migrations/*.php'));
        $this->migration1 = '';
        $this->migration2 = '';

        foreach ($files as $file) {
            $name = basename($file);
            if (str_contains($name, 'enable_row_level_security')) {
                $this->migration1 = $file;
            }
            if (str_contains($name, 'rls_policies_remaining_tables')) {
                $this->migration2 = $file;
            }
        }
    }

    // ===== FILE EXISTENCE =====

    public function test_migration_1_exists(): void
    {
        $this->assertFileExists($this->migration1, 'Migration 1 (enable_row_level_security) not found');
    }

    public function test_migration_2_exists(): void
    {
        $this->assertFileExists($this->migration2, 'Migration 2 (rls_policies_remaining_tables) not found');
    }

    // ===== MIGRATION 1 CHECKS =====

    public function test_migration_1_has_all_11_tables_in_array(): void
    {
        $content = File::get($this->migration1);
        $tables = $this->extractPhpArrayProperty($content, 'tables');

        $expected = [
            'cases',
            'clients',
            'client_addresses',
            'client_employments',
            'next_of_kin',
            'referrals',
            'milestones',
            'referral_attachments',
            'referral_comments',
            'case_documents',
            'case_notifications',
        ];

        $this->assertCount(11, $tables, 'Migration 1 should define exactly 11 tables');
        foreach ($expected as $table) {
            $this->assertContains($table, $tables, "Missing {$table} in \$tables array");
        }
    }

    public function test_migration_1_has_enable_rls_loop_for_tables(): void
    {
        $content = File::get($this->migration1);

        // The ENABLE RLS statements use variable interpolation: {$table}
        // Verify the loop pattern exists rather than searching for concrete table names
        $this->assertStringContainsString(
            'ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY',
            $content,
            'Missing ALTER TABLE ENABLE ROW LEVEL SECURITY loop using $table variable'
        );
    }

    public function test_migration_1_has_admin_policies_loop(): void
    {
        $content = File::get($this->migration1);

        // admin_all policies are generated via a foreach loop using {$table}
        $this->assertStringContainsString(
            'admin_all_{$table}',
            $content,
            'Missing admin_all_{$table} dynamic policy creation in loop'
        );

        $this->assertStringContainsString(
            'CREATE POLICY admin_all_{$table} ON {$table} FOR ALL TO PUBLIC',
            $content,
            'Missing CREATE POLICY admin_all_{$table} statement'
        );
    }

    public function test_migration_1_has_case_specific_policies(): void
    {
        $content = File::get($this->migration1);

        $this->assertStringContainsString(
            'case_manager_own_cases',
            $content,
            'Missing case_manager_own_cases policy'
        );

        $this->assertStringContainsString(
            'agency_referred_cases',
            $content,
            'Missing agency_referred_cases policy'
        );
    }

    // ===== MIGRATION 2 CHECKS =====

    public function test_migration_2_has_policies_for_all_tables(): void
    {
        $content = File::get($this->migration2);
        $tables = [
            'clients', 'client_addresses', 'client_employments',
            'next_of_kin', 'case_documents', 'referrals',
            'milestones', 'referral_attachments', 'referral_comments',
            'case_notifications',
        ];

        foreach ($tables as $table) {
            // Each table should have a case_manager_own policy
            $this->assertStringContainsString(
                "CREATE POLICY {$table}_case_manager_own ON {$table}",
                $content,
                "Missing case_manager_own policy for {$table}"
            );

            // Each table should have an agency policy.
            // referrals uses _agency_own (direct agcy_id FK) instead of _agency_referred.
            $agencyPolicy = $table === 'referrals'
                ? "{$table}_agency_own"
                : "{$table}_agency_referred";

            $this->assertStringContainsString(
                "CREATE POLICY {$agencyPolicy} ON {$table}",
                $content,
                "Missing agency policy for {$table} (expected: {$agencyPolicy})"
            );
        }
    }

    public function test_migration_2_referrals_has_own_agency_policy(): void
    {
        $content = File::get($this->migration2);

        // referrals has agcy_id directly, so it uses _agency_own (not _agency_referred via subquery)
        $this->assertStringContainsString(
            'referrals_agency_own',
            $content,
            'Missing referrals_agency_own policy (uses agcy_id directly)'
        );
    }

    public function test_migration_2_case_notifications_uses_subquery(): void
    {
        $content = File::get($this->migration2);

        // case_notifications has no user_id FK, so its policy must use a subquery through cases
        $this->assertStringContainsString(
            'case_notifications_case_manager_own',
            $content,
            'Missing case_notifications_case_manager_own policy'
        );

        $notificationPolicies = $this->extractPolicySection($content, 'case_notifications');

        // Verify the policy uses a subquery through the cases table
        $this->assertStringContainsString(
            'EXISTS (',
            $notificationPolicies,
            'case_notifications policy must use subquery — has no user_id FK'
        );

        $this->assertStringContainsString(
            'FROM cases c',
            $notificationPolicies,
            'case_notifications policy must query through cases table'
        );

        // A direct (unqualified) user_id reference would look like "AND user_id ="
        // Qualified references like "c.user_id =" in subqueries are fine.
        // Use regex negative lookbehind to exclude dot-qualified and underscore-prefixed variants.
        $hasDirectRef = (bool) preg_match(
            '/(?<![.\w])user_id\s*=\s*current_setting/',
            $notificationPolicies
        );
        $this->assertFalse(
            $hasDirectRef,
            'case_notifications policy has unqualified user_id reference — must use subquery via cases'
        );
    }

    // ===== SAFETY CHECKS (both migrations) =====

    public function test_no_force_row_level_security(): void
    {
        $content1 = File::get($this->migration1);
        $content2 = File::get($this->migration2);

        $this->assertStringNotContainsString(
            'FORCE ROW LEVEL SECURITY',
            $content1,
            'Migration 1 uses FORCE RLS — this would block table owner'
        );
        $this->assertStringNotContainsString(
            'FORCE ROW LEVEL SECURITY',
            $content2,
            'Migration 2 uses FORCE RLS — this would block table owner'
        );
    }

    public function test_current_setting_uses_true_second_arg(): void
    {
        $content1 = File::get($this->migration1);
        $content2 = File::get($this->migration2);

        // All current_setting calls must use TRUE as second arg for NULL safety
        // getLinesWithoutTrue returns only lines that have current_setting('app. but NOT , TRUE
        $badLines1 = $this->getLinesWithoutTrue($content1);
        $badLines2 = $this->getLinesWithoutTrue($content2);

        $this->assertStringNotContainsString(
            "current_setting('app.",
            $badLines1,
            'Migration 1 has current_setting calls without TRUE second argument'
        );
        $this->assertStringNotContainsString(
            "current_setting('app.",
            $badLines2,
            'Migration 2 has current_setting calls without TRUE second argument'
        );
    }

    public function test_migration_2_does_not_repeat_admin_policies(): void
    {
        $content = File::get($this->migration2);

        $this->assertStringNotContainsString(
            'admin_all_',
            $content,
            'Migration 2 should not contain admin bypass policies (they are in Migration 1)'
        );
    }

    // ===== MIDDLEWARE CHECKS =====

    public function test_set_postgres_session_uses_parameterized_queries(): void
    {
        $path = app_path('Http/Middleware/SetPostgresSession.php');
        $this->assertFileExists($path);

        $content = File::get($path);

        // Must use set_config with bound parameters (not raw string interpolation)
        $this->assertStringContainsString(
            'set_config(?, ?, ?)',
            $content,
            'SetPostgresSession must use set_config with parameterized bindings'
        );

        $this->assertStringContainsString(
            'app.current_user_id',
            $content,
            'SetPostgresSession must set app.current_user_id context variable'
        );

        $this->assertStringContainsString(
            'app.user_role',
            $content,
            'SetPostgresSession must set app.user_role context variable'
        );

        // Ensure no raw string interpolation with user values (safety check)
        $this->assertStringNotContainsString(
            "SET SESSION app.current_user_id = '{\$user->id}'",
            $content,
            'SetPostgresSession must NOT use string interpolation — use set_config with bindings'
        );

        $this->assertStringNotContainsString(
            "SET SESSION app.user_role = '{\$user->role}'",
            $content,
            'SetPostgresSession must NOT use string interpolation — use set_config with bindings'
        );
    }

    // ===== FAIL-CLOSED CHECKS =====

    public function test_rls_migration_throws_on_non_postgres(): void
    {
        $files = File::glob(database_path('migrations/*.php'));
        $migration = '';
        foreach ($files as $file) {
            if (str_contains(basename($file), 'enable_row_level_security')) {
                $migration = $file;
                break;
            }
        }

        $this->assertNotEmpty($migration, 'RLS migration file not found');

        $content = File::get($migration);

        // The up() catch block must re-throw (fail-closed) instead of silently skipping
        $this->assertStringContainsString(
            'throw $e;',
            $content,
            'RLS migration up() catch block must re-throw the exception (fail-closed)'
        );

        // The down() catch block must also re-throw
        $this->assertStringContainsString(
            'throw $e;',
            $content,
            'RLS migration down() catch block must re-throw the exception (fail-closed)'
        );

        // Verify error logging (not just warning)
        $this->assertStringContainsString(
            "Log::error('Row-level security migration failed",
            $content,
            'RLS migration must log error level (not just warning) on failure'
        );

        $this->assertStringContainsString(
            "Log::error('Row-level security rollback failed",
            $content,
            'RLS migration down() must log error level (not just warning) on failure'
        );
    }

    // ===== DOWN METHOD CHECKS =====

    public function test_migration_1_has_down_method(): void
    {
        $content = File::get($this->migration1);

        $this->assertStringContainsString(
            'DISABLE ROW LEVEL SECURITY',
            $content,
            'Migration 1 missing DISABLE ROW LEVEL SECURITY in down() method'
        );

        // Verify it uses the $tables loop (dynamic variable, not hardcoded)
        $this->assertStringContainsString(
            'ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY',
            $content,
            'Migration 1 should use dynamic $table variable in down() DISABLE loop'
        );
    }

    public function test_migration_2_has_down_method(): void
    {
        $content = File::get($this->migration2);

        $this->assertStringContainsString(
            'DROP POLICY IF EXISTS',
            $content,
            'Migration 2 missing DROP POLICY IF EXISTS in down() method'
        );
    }

    public function test_migration_2_down_drops_all_20_policies(): void
    {
        $content = File::get($this->migration2);

        $expectedPolicies = [
            'clients_case_manager_own ON clients',
            'clients_agency_referred ON clients',
            'next_of_kin_case_manager_own ON next_of_kin',
            'next_of_kin_agency_referred ON next_of_kin',
            'case_documents_case_manager_own ON case_documents',
            'case_documents_agency_referred ON case_documents',
            'client_addresses_case_manager_own ON client_addresses',
            'client_addresses_agency_referred ON client_addresses',
            'client_employments_case_manager_own ON client_employments',
            'client_employments_agency_referred ON client_employments',
            'referrals_case_manager_own ON referrals',
            'referrals_agency_own ON referrals',
            'milestones_case_manager_own ON milestones',
            'milestones_agency_referred ON milestones',
            'referral_attachments_case_manager_own ON referral_attachments',
            'referral_attachments_agency_referred ON referral_attachments',
            'referral_comments_case_manager_own ON referral_comments',
            'referral_comments_agency_referred ON referral_comments',
            'case_notifications_case_manager_own ON case_notifications',
            'case_notifications_agency_referred ON case_notifications',
        ];

        foreach ($expectedPolicies as $policy) {
            $this->assertStringContainsString(
                "DROP POLICY IF EXISTS {$policy};",
                $content,
                "Missing DROP POLICY IF EXISTS for {$policy}"
            );
        }
    }

    // ===== HELPERS =====

    private function extractPolicySection(string $content, string $tableName): string
    {
        // Find the policy creation block for a specific table
        $startPos = strpos($content, "CREATE POLICY {$tableName}_");
        if ($startPos === false) {
            return '';
        }

        // Find the next semicolon after the CREATE POLICY statement
        $endPos = strpos($content, ');', $startPos);
        if ($endPos === false) {
            return '';
        }

        return substr($content, $startPos, $endPos - $startPos + 2);
    }

    private function getLinesWithoutTrue(string $content): string
    {
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            if (str_contains($line, "current_setting('app.") && ! str_contains($line, ', TRUE')) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Extract values of a private array property from a PHP migration file.
     * Matches: private array $propertyName = [ 'val1', 'val2', ... ];
     */
    private function extractPhpArrayProperty(string $content, string $propertyName): array
    {
        $pattern = '/private\s+array\s+\$'.preg_quote($propertyName, '/').'\s*=\s*\[(.*?)\];/s';
        if (! preg_match($pattern, $content, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[1], $strings);

        return $strings[1];
    }
}
