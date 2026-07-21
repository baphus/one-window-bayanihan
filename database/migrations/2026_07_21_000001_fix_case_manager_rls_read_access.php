<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix CASE_MANAGER RLS policies: case managers should have full read+write
 * access to all cases/referrals/clients per project rules and product decision.
 *
 * Previous policies (from 2026_06_01_000008) restricted CASE_MANAGER to
 * user_id-matched rows only. This migration replaces those with unrestricted
 * FOR ALL policies — same access level as ADMIN for these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isCM = "current_setting('app.user_role', TRUE) = 'CASE_MANAGER'";

        // ── Drop existing CASE_MANAGER policies ──────────────────────────
        $oldPolicies = [
            'cases' => 'case_manager_own_cases',
            'clients' => 'clients_case_manager_own',
            'next_of_kin' => 'next_of_kin_case_manager_own',
            'case_documents' => 'case_documents_case_manager_own',
            'client_addresses' => 'client_addresses_case_manager_own',
            'client_employments' => 'client_employments_case_manager_own',
            'referrals' => 'referrals_case_manager_own',
            'milestones' => 'milestones_case_manager_own',
            'referral_attachments' => 'referral_attachments_case_manager_own',
            'referral_comments' => 'referral_comments_case_manager_own',
            'case_notifications' => 'case_notifications_case_manager_own',
        ];

        foreach ($oldPolicies as $table => $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table};");
        }

        // ── New FOR ALL policies: CASE_MANAGER has full access ───────────
        foreach ($oldPolicies as $table => $_) {
            $policyName = "case_manager_all_access_{$table}";
            DB::statement("DROP POLICY IF EXISTS {$policyName} ON {$table};");
            DB::statement("CREATE POLICY {$policyName} ON {$table} FOR ALL TO PUBLIC USING ({$isCM});");
        }
    }

    public function down(): void
    {
        $isCM = "current_setting('app.user_role', TRUE) = 'CASE_MANAGER'";

        // Drop the new policies
        $tables = [
            'cases', 'clients', 'next_of_kin', 'case_documents',
            'client_addresses', 'client_employments', 'referrals',
            'milestones', 'referral_attachments', 'referral_comments',
            'case_notifications',
        ];
        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS case_manager_all_access_{$table} ON {$table};");
        }

        // Re-create the original FOR ALL policies (own rows only)
        DB::statement("CREATE POLICY case_manager_own_cases ON cases FOR ALL TO PUBLIC USING ({$isCM} AND user_id = current_setting('app.current_user_id', TRUE)::uuid);");
        DB::statement("CREATE POLICY clients_case_manager_own ON clients FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM cases c WHERE c.client_id = clients.id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY next_of_kin_case_manager_own ON next_of_kin FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = next_of_kin.id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY case_documents_case_manager_own ON case_documents FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_documents.case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY client_addresses_case_manager_own ON client_addresses FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_addresses.client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY client_employments_case_manager_own ON client_employments FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_employments.client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY referrals_case_manager_own ON referrals FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM cases c WHERE c.id = referrals.case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY milestones_case_manager_own ON milestones FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = milestones.refr_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY referral_attachments_case_manager_own ON referral_attachments FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = referral_attachments.referral_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY referral_comments_case_manager_own ON referral_comments FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = referral_comments.refr_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
        DB::statement("CREATE POLICY case_notifications_case_manager_own ON case_notifications FOR ALL TO PUBLIC USING ({$isCM} AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_notifications.case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));");
    }
};
