<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $legacyTables = [
        'cases', 'clients', 'client_addresses', 'client_employments', 'next_of_kin',
        'referrals', 'milestones', 'referral_attachments', 'referral_comments',
        'case_documents', 'case_notifications',
    ];

    public function up(): void
    {
        $runtime = $this->identifier((string) config('database.rls.runtime_role'));
        $maintenance = $this->identifier((string) config('database.rls.maintenance_role'));

        // Existing policies remain in place during rollout. These grants are the
        // compatibility bridge from the former owner connection to the runtime role.
        // TODO: Once staged rollout is complete, DROP POLICY case_drafts_owner_all ON case_drafts
        // to remove the PUBLIC-scoped owner policy that makes the runtime role check a no-op at RLS level.
        foreach ($this->legacyTables as $table) {
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE {$table} TO {$runtime}");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE {$table} TO {$maintenance}");
            DB::statement("DROP POLICY IF EXISTS legacy_maintenance_all_{$table} ON {$table}");
            DB::statement("CREATE POLICY legacy_maintenance_all_{$table} ON {$table} FOR ALL TO {$maintenance} USING (true) WITH CHECK (true)");
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE case_drafts TO {$runtime}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE case_drafts TO {$maintenance}");
        DB::statement('DROP POLICY IF EXISTS case_drafts_runtime_owner ON case_drafts');
        DB::statement("CREATE POLICY case_drafts_runtime_owner ON case_drafts FOR ALL TO {$runtime} USING (owner_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid AND current_setting('app.user_role', true) = 'CASE_MANAGER') WITH CHECK (owner_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid AND current_setting('app.user_role', true) = 'CASE_MANAGER')");
        DB::statement('DROP POLICY IF EXISTS case_drafts_maintenance_all ON case_drafts');
        DB::statement("CREATE POLICY case_drafts_maintenance_all ON case_drafts FOR ALL TO {$maintenance} USING (true) WITH CHECK (true)");
    }

    public function down(): void
    {
        $runtime = $this->identifier((string) config('database.rls.runtime_role'));
        $maintenance = $this->identifier((string) config('database.rls.maintenance_role'));
        DB::statement('DROP POLICY IF EXISTS case_drafts_runtime_owner ON case_drafts');
        DB::statement('DROP POLICY IF EXISTS case_drafts_maintenance_all ON case_drafts');
        foreach ($this->legacyTables as $table) {
            DB::statement("DROP POLICY IF EXISTS legacy_maintenance_all_{$table} ON {$table}");
            DB::statement("REVOKE ALL PRIVILEGES ON TABLE {$table} FROM {$runtime}, {$maintenance}");
        }
        DB::statement("REVOKE ALL PRIVILEGES ON TABLE case_drafts FROM {$runtime}, {$maintenance}");
    }

    private function identifier(string $value): string
    {
        if ($value === '' || ! preg_match('/^[a-z_][a-z0-9_]*$/', $value)) {
            throw new RuntimeException('RLS database role configuration is missing or invalid.');
        }

        return '"'.$value.'"';
    }
};
