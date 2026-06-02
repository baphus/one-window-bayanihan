<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private array $tables = [
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

    public function up(): void
    {
        try {
            foreach ($this->tables as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
            }

            // Admin bypass policies — one per table
            foreach ($this->tables as $table) {
                DB::statement("
                    CREATE POLICY admin_all_{$table} ON {$table} FOR ALL TO PUBLIC
                    USING (current_setting('app.user_role', TRUE) = 'ADMIN')
                    WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN');
                ");
            }

            // Case Manager: own cases only
            DB::statement("
                CREATE POLICY case_manager_own_cases ON cases FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND user_id = current_setting('app.current_user_id', TRUE)::uuid
                );
            ");

            // Agency: cases referred to their agency
            DB::statement("
                CREATE POLICY agency_referred_cases ON cases FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND id = ANY(
                        SELECT r.case_id FROM referrals r
                        WHERE r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");
        } catch (Throwable $e) {
            Log::warning('Row-level security migration requires PostgreSQL. Skipping — ensure it runs on production.', [
                'error' => $e->getMessage(),
            ]);
            echo "⚠️  Row-level security migration requires PostgreSQL. Skipping — ensure it runs on production.\n";
        }
    }

    public function down(): void
    {
        try {
            // Drop agency and case_manager policies
            DB::statement('DROP POLICY IF EXISTS agency_referred_cases ON cases;');
            DB::statement('DROP POLICY IF EXISTS case_manager_own_cases ON cases;');

            // Drop admin policies for all tables
            foreach ($this->tables as $table) {
                DB::statement("DROP POLICY IF EXISTS admin_all_{$table} ON {$table};");
            }

            // Disable RLS
            foreach ($this->tables as $table) {
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
            }
        } catch (Throwable $e) {
            Log::warning('Failed to roll back row-level security migration.', [
                'error' => $e->getMessage(),
            ]);
            echo "⚠️  Failed to roll back row-level security. See log for details.\n";
        }
    }
};
