<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'referral_client_requests',
        'referral_client_request_items',
        'referral_client_access_links',
        'referral_client_messages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }

        DB::statement("CREATE POLICY referral_client_requests_admin_all ON referral_client_requests FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'ADMIN') WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN')");
        DB::statement("CREATE POLICY referral_client_requests_agency_own ON referral_client_requests FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = referral_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid))) WITH CHECK (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = referral_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
        DB::statement("CREATE POLICY referral_client_requests_case_manager_read ON referral_client_requests FOR SELECT TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referrals r JOIN cases c ON c.id = r.case_id WHERE r.id = referral_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid))");

        DB::statement("CREATE POLICY referral_client_request_items_admin_all ON referral_client_request_items FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'ADMIN') WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN')");
        DB::statement("CREATE POLICY referral_client_request_items_agency_own ON referral_client_request_items FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id WHERE q.id = request_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid))) WITH CHECK (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id WHERE q.id = request_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
        DB::statement("CREATE POLICY referral_client_request_items_case_manager_read ON referral_client_request_items FOR SELECT TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id JOIN cases c ON c.id = r.case_id WHERE q.id = request_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid))");

        // No public/client policy exists here. Capability traffic is mediated
        // by Laravel and never receives direct database access to token_hash
        // or recipient_snapshot.
        DB::statement("CREATE POLICY referral_client_access_links_admin_all ON referral_client_access_links FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'ADMIN') WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN')");
        DB::statement("CREATE POLICY referral_client_access_links_agency_own ON referral_client_access_links FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id WHERE q.id = request_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid))) WITH CHECK (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id WHERE q.id = request_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
        DB::statement("CREATE POLICY referral_client_access_links_case_manager_read ON referral_client_access_links FOR SELECT TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id JOIN cases c ON c.id = r.case_id WHERE q.id = request_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid))");
        DB::statement("CREATE POLICY referral_client_access_links_case_manager_revoke ON referral_client_access_links FOR UPDATE TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id JOIN cases c ON c.id = r.case_id WHERE q.id = request_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid)) WITH CHECK (revoked_at IS NOT NULL AND revoked_by = current_setting('app.current_user_id', TRUE)::uuid)");

        DB::statement("CREATE POLICY referral_client_messages_admin_all ON referral_client_messages FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'ADMIN') WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN')");
        DB::statement("CREATE POLICY referral_client_messages_agency_own ON referral_client_messages FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id WHERE q.id = request_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid))) WITH CHECK (current_setting('app.user_role', TRUE) = 'AGENCY' AND sender_kind = 'AGENCY_USER' AND user_id = current_setting('app.current_user_id', TRUE)::uuid AND access_link_id IS NULL AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id WHERE q.id = request_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
        DB::statement("CREATE POLICY referral_client_messages_case_manager_read ON referral_client_messages FOR SELECT TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referral_client_requests q JOIN referrals r ON r.id = q.referral_id JOIN cases c ON c.id = r.case_id WHERE q.id = request_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid))");
    }

    public function down(): void
    {
        $policies = [
            'referral_client_requests_admin_all', 'referral_client_requests_agency_own', 'referral_client_requests_case_manager_read',
            'referral_client_request_items_admin_all', 'referral_client_request_items_agency_own', 'referral_client_request_items_case_manager_read',
            'referral_client_access_links_admin_all', 'referral_client_access_links_agency_own', 'referral_client_access_links_case_manager_read', 'referral_client_access_links_case_manager_revoke',
            'referral_client_messages_admin_all', 'referral_client_messages_agency_own', 'referral_client_messages_case_manager_read',
        ];

        foreach ($policies as $policy) {
            foreach ($this->tables as $table) {
                DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
            }
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
