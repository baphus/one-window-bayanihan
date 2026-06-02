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
            // DIRECT CASE-OWNED TABLES (case_id FK → cases)
            //   clients, next_of_kin, case_documents
            // ===================================================================

            // --- clients (has case_id FK) ---
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

            // --- next_of_kin (has case_id FK) ---
            DB::statement("
                CREATE POLICY next_of_kin_case_manager_own ON next_of_kin FOR ALL TO PUBLIC
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
                CREATE POLICY next_of_kin_agency_referred ON next_of_kin FOR ALL TO PUBLIC
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

            // --- case_documents (has case_id FK) ---
            DB::statement("
                CREATE POLICY case_documents_case_manager_own ON case_documents FOR ALL TO PUBLIC
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
                CREATE POLICY case_documents_agency_referred ON case_documents FOR ALL TO PUBLIC
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
            // CLIENT-CHILD TABLES (client_id FK → clients → clients.case_id)
            //   client_addresses, client_employments
            // ===================================================================

            // --- client_addresses (has client_id FK, no direct case_id) ---
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

            // --- client_employments (has client_id FK, no direct case_id) ---
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

            // ===================================================================
            // REFERRAL-CHAIN TABLES (access via referrals → cases)
            //   referrals, milestones, referral_attachments, referral_comments
            // ===================================================================

            // --- referrals (has case_id + agcy_id FKs directly) ---
            DB::statement("
                CREATE POLICY referrals_case_manager_own ON referrals FOR ALL TO PUBLIC
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
                CREATE POLICY referrals_agency_own ON referrals FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND agcy_id = (
                        SELECT u.agcy_id FROM users u
                        WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");

            // --- milestones (has refr_id FK → referrals, no direct case_id) ---
            DB::statement("
                CREATE POLICY milestones_case_manager_own ON milestones FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM referrals r
                        JOIN cases c ON c.id = r.case_id
                        WHERE r.id = refr_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY milestones_agency_referred ON milestones FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM referrals r
                        WHERE r.id = refr_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // --- referral_attachments (has referral_id FK → referrals) ---
            DB::statement("
                CREATE POLICY referral_attachments_case_manager_own ON referral_attachments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM referrals r
                        JOIN cases c ON c.id = r.case_id
                        WHERE r.id = referral_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY referral_attachments_agency_referred ON referral_attachments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM referrals r
                        WHERE r.id = referral_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // --- referral_comments (has refr_id FK → referrals) ---
            DB::statement("
                CREATE POLICY referral_comments_case_manager_own ON referral_comments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'CASE_MANAGER'
                    AND EXISTS (
                        SELECT 1 FROM referrals r
                        JOIN cases c ON c.id = r.case_id
                        WHERE r.id = refr_id
                        AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid
                    )
                );
            ");
            DB::statement("
                CREATE POLICY referral_comments_agency_referred ON referral_comments FOR ALL TO PUBLIC
                USING (
                    current_setting('app.user_role', TRUE) = 'AGENCY'
                    AND EXISTS (
                        SELECT 1 FROM referrals r
                        WHERE r.id = refr_id
                        AND r.agcy_id = (
                            SELECT u.agcy_id FROM users u
                            WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid
                        )
                    )
                );
            ");

            // ===================================================================
            // CASE NOTIFICATIONS (subquery — case_id FK, no user_id column)
            // ===================================================================

            DB::statement("
                CREATE POLICY case_notifications_case_manager_own ON case_notifications FOR ALL TO PUBLIC
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
                CREATE POLICY case_notifications_agency_referred ON case_notifications FOR ALL TO PUBLIC
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

            echo "✅ Created RLS policies for remaining 10 business tables.\n";
        } catch (Throwable $e) {
            Log::warning('RLS Migration 2 requires PostgreSQL. Skipping — ensure it runs on production.', [
                'error' => $e->getMessage(),
            ]);
            echo "⚠️  Row-level security migration requires PostgreSQL. Skipping — ensure it runs on production.\n";
        }
    }

    public function down(): void
    {
        try {
            // Direct case-owned tables
            DB::statement('DROP POLICY IF EXISTS clients_case_manager_own ON clients;');
            DB::statement('DROP POLICY IF EXISTS clients_agency_referred ON clients;');

            DB::statement('DROP POLICY IF EXISTS next_of_kin_case_manager_own ON next_of_kin;');
            DB::statement('DROP POLICY IF EXISTS next_of_kin_agency_referred ON next_of_kin;');

            DB::statement('DROP POLICY IF EXISTS case_documents_case_manager_own ON case_documents;');
            DB::statement('DROP POLICY IF EXISTS case_documents_agency_referred ON case_documents;');

            // Client-child tables (via client_id → clients → cases)
            DB::statement('DROP POLICY IF EXISTS client_addresses_case_manager_own ON client_addresses;');
            DB::statement('DROP POLICY IF EXISTS client_addresses_agency_referred ON client_addresses;');

            DB::statement('DROP POLICY IF EXISTS client_employments_case_manager_own ON client_employments;');
            DB::statement('DROP POLICY IF EXISTS client_employments_agency_referred ON client_employments;');

            // Referral-chain tables
            DB::statement('DROP POLICY IF EXISTS referrals_case_manager_own ON referrals;');
            DB::statement('DROP POLICY IF EXISTS referrals_agency_own ON referrals;');

            DB::statement('DROP POLICY IF EXISTS milestones_case_manager_own ON milestones;');
            DB::statement('DROP POLICY IF EXISTS milestones_agency_referred ON milestones;');

            DB::statement('DROP POLICY IF EXISTS referral_attachments_case_manager_own ON referral_attachments;');
            DB::statement('DROP POLICY IF EXISTS referral_attachments_agency_referred ON referral_attachments;');

            DB::statement('DROP POLICY IF EXISTS referral_comments_case_manager_own ON referral_comments;');
            DB::statement('DROP POLICY IF EXISTS referral_comments_agency_referred ON referral_comments;');

            // Case notifications
            DB::statement('DROP POLICY IF EXISTS case_notifications_case_manager_own ON case_notifications;');
            DB::statement('DROP POLICY IF EXISTS case_notifications_agency_referred ON case_notifications;');

            echo "✅ Dropped all RLS policies from Migration 2.\n";
        } catch (Throwable $e) {
            Log::warning('Failed to roll back RLS Migration 2.', [
                'error' => $e->getMessage(),
            ]);
            echo "⚠️  Failed to drop RLS policies. See log for details.\n";
        }
    }
};
