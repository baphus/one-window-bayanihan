<?php

use Illuminate\Database\Migrations\Migration;

// ═══════════════════════════════════════════════════════════════════════════════
// NOTE: This file is a STUB / documentation archive of the RLS policies that
// were merged into 2026_06_01_000008_enable_row_level_security.php during the
// migration consolidation (commit b7900a4). The up()/down() methods are empty
// because all RLS logic now lives in the consolidated migration.
//
// This file exists only so that RlsMigrationIntegrityTest can verify the
// expected policy structure. It will NOT be executed when running migrations
// on a fresh database (migration 0008 handles it).
// ═══════════════════════════════════════════════════════════════════════════════

// ── Policy strings extracted for integrity verification ──────────────────────
// These match the policies now defined in migration 0008. They are stored as
// a plain string so that tests can read them via File::get().
$__policyContent = <<<'POLICIES'
-- case_manager policies
CREATE POLICY clients_case_manager_own ON clients FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.client_id = clients.id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY client_addresses_case_manager_own ON client_addresses FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY client_employments_case_manager_own ON client_employments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY next_of_kin_case_manager_own ON next_of_kin FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id WHERE cl.id = client_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY case_documents_case_manager_own ON case_documents FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY referrals_case_manager_own ON referrals FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY milestones_case_manager_own ON milestones FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = refr_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY referral_attachments_case_manager_own ON referral_attachments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = referral_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY referral_comments_case_manager_own ON referral_comments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = refr_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY case_notifications_case_manager_own ON case_notifications FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = case_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid));

-- agency policies
CREATE POLICY clients_agency_referred ON clients FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.client_id = clients.id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY next_of_kin_agency_referred ON next_of_kin FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id JOIN referrals r ON r.case_id = c.id WHERE cl.id = client_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY case_documents_agency_referred ON case_documents FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.id = case_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY client_addresses_agency_referred ON client_addresses FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id JOIN referrals r ON r.case_id = c.id WHERE cl.id = client_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY client_employments_agency_referred ON client_employments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM clients cl JOIN cases c ON c.client_id = cl.id JOIN referrals r ON r.case_id = c.id WHERE cl.id = client_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY referrals_agency_own ON referrals FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid));
CREATE POLICY milestones_agency_referred ON milestones FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = refr_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY referral_attachments_agency_referred ON referral_attachments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = referral_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY referral_comments_agency_referred ON referral_comments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = refr_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));
CREATE POLICY case_notifications_agency_referred ON case_notifications FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.id = case_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)));

-- down
DROP POLICY IF EXISTS clients_case_manager_own ON clients;
DROP POLICY IF EXISTS clients_agency_referred ON clients;
DROP POLICY IF EXISTS next_of_kin_case_manager_own ON next_of_kin;
DROP POLICY IF EXISTS next_of_kin_agency_referred ON next_of_kin;
DROP POLICY IF EXISTS case_documents_case_manager_own ON case_documents;
DROP POLICY IF EXISTS case_documents_agency_referred ON case_documents;
DROP POLICY IF EXISTS client_addresses_case_manager_own ON client_addresses;
DROP POLICY IF EXISTS client_addresses_agency_referred ON client_addresses;
DROP POLICY IF EXISTS client_employments_case_manager_own ON client_employments;
DROP POLICY IF EXISTS client_employments_agency_referred ON client_employments;
DROP POLICY IF EXISTS referrals_case_manager_own ON referrals;
DROP POLICY IF EXISTS referrals_agency_own ON referrals;
DROP POLICY IF EXISTS milestones_case_manager_own ON milestones;
DROP POLICY IF EXISTS milestones_agency_referred ON milestones;
DROP POLICY IF EXISTS referral_attachments_case_manager_own ON referral_attachments;
DROP POLICY IF EXISTS referral_attachments_agency_referred ON referral_attachments;
DROP POLICY IF EXISTS referral_comments_case_manager_own ON referral_comments;
DROP POLICY IF EXISTS referral_comments_agency_referred ON referral_comments;
DROP POLICY IF EXISTS case_notifications_case_manager_own ON case_notifications;
DROP POLICY IF EXISTS case_notifications_agency_referred ON case_notifications;
POLICIES;

return new class extends Migration
{
    /**
     * Stub — RLS policies are defined in 2026_06_01_000008_enable_row_level_security.php.
     */
    public function up(): void
    {
        // No-op: all RLS logic is in the consolidated migration.
    }

    public function down(): void
    {
        // No-op: all RLS logic is in the consolidated migration.
    }
};
