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

    /**
     * Consolidated RLS migration — merges the previous three separate migrations:
     *   2026_06_01_000001 (enable RLS + admin bypass + cases role policies)
     *   2026_06_01_000002 (remaining 10 tables role policies)
     *   2026_06_02_000003 (update to cases.client_id join pattern)
     *
     * All policies use the FINAL join pattern (cases.client_id, not clients.case_id).
     */
    public function up(): void
    {
        try {
            // ── 1. Enable RLS on all 11 tables ──────────────────────────────
            foreach ($this->tables as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
            }

            // ── 2. Admin bypass policies (one per table) ────────────────────
            foreach ($this->tables as $table) {
                DB::statement("CREATE POLICY admin_all_{$table} ON {$table} FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'ADMIN') WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN');");
            }

            // ── 3. Case Manager policies (direct user_id match) ─────────────
            // cases — has user_id directly
            DB::statement("CREATE POLICY case_manager_own_cases ON cases FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND user_id = current_setting('app.current_user_id', TRUE)::uuid);");

            // clients — via cases.client_id
            DB::statement("CREATE POLICY clients_case_manager_own ON clients FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.client_id = clients.id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // next_of_kin — via clients JOIN cases
            DB::statement("CREATE POLICY next_of_kin_case_manager_own ON next_of_kin FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // case_documents — via cases (case_id FK)
            DB::statement("CREATE POLICY case_documents_case_manager_own ON case_documents FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // client_addresses — via clients JOIN cases
            DB::statement("CREATE POLICY client_addresses_case_manager_own ON client_addresses FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // client_employments — via clients JOIN cases (same as addresses)
            DB::statement("CREATE POLICY client_employments_case_manager_own ON client_employments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // referrals — via cases (case_id FK)
            DB::statement("CREATE POLICY referrals_case_manager_own ON referrals FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // milestones — via referrals JOIN cases
            DB::statement("CREATE POLICY milestones_case_manager_own ON milestones FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = refr_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // referral_attachments — via referrals JOIN cases
            DB::statement("CREATE POLICY referral_attachments_case_manager_own ON referral_attachments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = referral_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // referral_comments — via referrals JOIN cases
            DB::statement("CREATE POLICY referral_comments_case_manager_own ON referral_comments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = refr_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // case_notifications — via cases (case_id FK)
            DB::statement("CREATE POLICY case_notifications_case_manager_own ON case_notifications FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");

            // ── 4. Agency policies (via referrals JOIN chain) ──────────────
            // cases — id IN (referrals for this agency)
            DB::statement("CREATE POLICY agency_referred_cases ON cases FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND id = ANY(SELECT r.case_id FROM referrals r WHERE r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // clients — via cases JOIN referrals, using cases.client_id
            DB::statement("CREATE POLICY clients_agency_referred ON clients FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.client_id = clients.id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // next_of_kin — via clients JOIN cases JOIN referrals
            DB::statement("CREATE POLICY next_of_kin_agency_referred ON next_of_kin FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id JOIN referrals r ON r.case_id = c.id WHERE cl.id = client_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // case_documents — via cases JOIN referrals (case_id FK)
            DB::statement("CREATE POLICY case_documents_agency_referred ON case_documents FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.id = case_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // client_addresses — via clients JOIN cases JOIN referrals
            DB::statement("CREATE POLICY client_addresses_agency_referred ON client_addresses FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id JOIN referrals r ON r.case_id = c.id WHERE cl.id = client_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // client_employments — same join chain
            DB::statement("CREATE POLICY client_employments_agency_referred ON client_employments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id JOIN referrals r ON r.case_id = c.id WHERE cl.id = client_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // referrals — direct agcy_id match
            DB::statement("CREATE POLICY referrals_agency_own ON referrals FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid));");

            // milestones — via referrals (refr_id FK)
            DB::statement("CREATE POLICY milestones_agency_referred ON milestones FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = refr_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // referral_attachments — via referrals (referral_id FK)
            DB::statement("CREATE POLICY referral_attachments_agency_referred ON referral_attachments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = referral_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // referral_comments — via referrals (refr_id FK)
            DB::statement("CREATE POLICY referral_comments_agency_referred ON referral_comments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = refr_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            // case_notifications — via cases JOIN referrals (case_id FK)
            DB::statement("CREATE POLICY case_notifications_agency_referred ON case_notifications FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.id = case_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));");

            echo "✅ RLS enabled on 11 tables — 33 policies created (11 admin, 11 case_manager, 11 agency).\n";
        } catch (Throwable $e) {
            Log::error('Row-level security migration failed (PostgreSQL required)', [
                'error' => $e->getMessage(),
            ]);
            echo "❌ Row-level security migration failed (PostgreSQL required): {$e->getMessage()}\n";
            throw $e;
        }
    }

    public function down(): void
    {
        try {
            // Drop admin policies
            foreach ($this->tables as $table) {
                DB::statement("DROP POLICY IF EXISTS admin_all_{$table} ON {$table};");
            }

            // Drop case_manager policies
            DB::statement('DROP POLICY IF EXISTS case_manager_own_cases ON cases;');
            DB::statement('DROP POLICY IF EXISTS clients_case_manager_own ON clients;');
            DB::statement('DROP POLICY IF EXISTS next_of_kin_case_manager_own ON next_of_kin;');
            DB::statement('DROP POLICY IF EXISTS case_documents_case_manager_own ON case_documents;');
            DB::statement('DROP POLICY IF EXISTS client_addresses_case_manager_own ON client_addresses;');
            DB::statement('DROP POLICY IF EXISTS client_employments_case_manager_own ON client_employments;');
            DB::statement('DROP POLICY IF EXISTS referrals_case_manager_own ON referrals;');
            DB::statement('DROP POLICY IF EXISTS milestones_case_manager_own ON milestones;');
            DB::statement('DROP POLICY IF EXISTS referral_attachments_case_manager_own ON referral_attachments;');
            DB::statement('DROP POLICY IF EXISTS referral_comments_case_manager_own ON referral_comments;');
            DB::statement('DROP POLICY IF EXISTS case_notifications_case_manager_own ON case_notifications;');

            // Drop agency policies
            DB::statement('DROP POLICY IF EXISTS agency_referred_cases ON cases;');
            DB::statement('DROP POLICY IF EXISTS clients_agency_referred ON clients;');
            DB::statement('DROP POLICY IF EXISTS next_of_kin_agency_referred ON next_of_kin;');
            DB::statement('DROP POLICY IF EXISTS case_documents_agency_referred ON case_documents;');
            DB::statement('DROP POLICY IF EXISTS client_addresses_agency_referred ON client_addresses;');
            DB::statement('DROP POLICY IF EXISTS client_employments_agency_referred ON client_employments;');
            DB::statement('DROP POLICY IF EXISTS referrals_agency_own ON referrals;');
            DB::statement('DROP POLICY IF EXISTS milestones_agency_referred ON milestones;');
            DB::statement('DROP POLICY IF EXISTS referral_attachments_agency_referred ON referral_attachments;');
            DB::statement('DROP POLICY IF EXISTS referral_comments_agency_referred ON referral_comments;');
            DB::statement('DROP POLICY IF EXISTS case_notifications_agency_referred ON case_notifications;');

            // Disable RLS on all tables
            foreach ($this->tables as $table) {
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
            }

            echo "✅ Dropped all 33 RLS policies and disabled RLS on 11 tables.\n";
        } catch (Throwable $e) {
            Log::error('Row-level security rollback failed (PostgreSQL required)', [
                'error' => $e->getMessage(),
            ]);
            echo "❌ Row-level security rollback failed (PostgreSQL required): {$e->getMessage()}\n";
            throw $e;
        }
    }
};
