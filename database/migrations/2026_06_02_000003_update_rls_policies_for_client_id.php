<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // ===================================================================
            // Drop the 6 old policies that reference clients.case_id (being dropped)
            // ===================================================================
            DB::statement('DROP POLICY IF EXISTS clients_case_manager_own ON clients;');
            DB::statement('DROP POLICY IF EXISTS clients_agency_referred ON clients;');

            DB::statement('DROP POLICY IF EXISTS client_addresses_case_manager_own ON client_addresses;');
            DB::statement('DROP POLICY IF EXISTS client_addresses_agency_referred ON client_addresses;');

            DB::statement('DROP POLICY IF EXISTS client_employments_case_manager_own ON client_employments;');
            DB::statement('DROP POLICY IF EXISTS client_employments_agency_referred ON client_employments;');

            // ===================================================================
            // Recreate clients policies — was `c.id = case_id` (clients.case_id),
            // now uses `c.client_id = clients.id`
            // ===================================================================
            DB::statement("
                CREATE POLICY clients_case_manager_own ON clients FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM cases c
                        WHERE c.client_id = clients.id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY clients_agency_referred ON clients FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM cases c
                        JOIN referrals r ON r.case_id = c.id
                        WHERE c.client_id = clients.id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // ===================================================================
            // Recreate client_addresses policies — was `c.id = cl.case_id`,
            // now uses `c.client_id = cl.id`
            // ===================================================================
            DB::statement("
                CREATE POLICY client_addresses_case_manager_own ON client_addresses FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.client_id = cl.id
                        WHERE cl.id = client_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY client_addresses_agency_referred ON client_addresses FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.client_id = cl.id
                        JOIN referrals r ON r.case_id = c.id
                        WHERE cl.id = client_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // ===================================================================
            // Recreate client_employments policies — was `c.id = cl.case_id`,
            // now uses `c.client_id = cl.id`
            // ===================================================================
            DB::statement("
                CREATE POLICY client_employments_case_manager_own ON client_employments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.client_id = cl.id
                        WHERE cl.id = client_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY client_employments_agency_referred ON client_employments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.client_id = cl.id
                        JOIN referrals r ON r.case_id = c.id
                        WHERE cl.id = client_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            echo "✅ Updated RLS policies — replaced clients.case_id with cases.client_id join.\n";
        } catch (Throwable $e) {
            Log::warning('RLS Migration 3 requires PostgreSQL. Skipping — ensure it runs on production.', [
                'error' => $e->getMessage(),
            ]);
            echo "⚠️  Row-level security migration requires PostgreSQL. Skipping — ensure it runs on production.\n";
        }
    }

    public function down(): void
    {
        try {
            // Drop the 6 updated policies
            DB::statement('DROP POLICY IF EXISTS clients_case_manager_own ON clients;');
            DB::statement('DROP POLICY IF EXISTS clients_agency_referred ON clients;');

            DB::statement('DROP POLICY IF EXISTS client_addresses_case_manager_own ON client_addresses;');
            DB::statement('DROP POLICY IF EXISTS client_addresses_agency_referred ON client_addresses;');

            DB::statement('DROP POLICY IF EXISTS client_employments_case_manager_own ON client_employments;');
            DB::statement('DROP POLICY IF EXISTS client_employments_agency_referred ON client_employments;');

            // ===================================================================
            // Restore original clients policies (with clients.case_id join)
            // ===================================================================
            DB::statement("
                CREATE POLICY clients_case_manager_own ON clients FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM cases c
                        WHERE c.id = case_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY clients_agency_referred ON clients FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM cases c
                        JOIN referrals r ON r.case_id = c.id
                        WHERE c.id = case_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // ===================================================================
            // Restore original client_addresses policies (with cl.case_id join)
            // ===================================================================
            DB::statement("
                CREATE POLICY client_addresses_case_manager_own ON client_addresses FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.id = cl.case_id
                        WHERE cl.id = client_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY client_addresses_agency_referred ON client_addresses FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.id = cl.case_id
                        JOIN referrals r ON r.case_id = c.id
                        WHERE cl.id = client_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // ===================================================================
            // Restore original client_employments policies (with cl.case_id join)
            // ===================================================================
            DB::statement("
                CREATE POLICY client_employments_case_manager_own ON client_employments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.id = cl.case_id
                        WHERE cl.id = client_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY client_employments_agency_referred ON client_employments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM clients cl
                        JOIN cases c ON c.id = cl.case_id
                        JOIN referrals r ON r.case_id = c.id
                        WHERE cl.id = client_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            echo "✅ Rolled back RLS policies to original clients.case_id join pattern.\n";
        } catch (Throwable $e) {
            Log::warning('Failed to roll back RLS Migration 3.', [
                'error' => $e->getMessage(),
            ]);
            echo "⚠️  Failed to drop RLS policies. See log for details.\n";
        }
    }
};
