<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Capability-session policies for the public client-request inbox
     * (track.request.* routes). The capability session is a guest session
     * holding ['request_id' => ...] under the 'client_request_access' key;
     * the SetPostgresSession middleware mirrors it into
     * app.client_request_id ('' when absent, which matches nothing).
     *
     * ponytail: these policies scope to one request_id (and the single
     * capability link for sessionRequest()/sendClientMessage()) — the raw-token
     * lookup in ReferralClientAccessService::resolveUsableToken() is the one
     * guest path still uncovered, so the token exchange step relies on a
     * privileged DB role; revisit with a SECURITY DEFINER lookup if the app
     * role ever stops bypassing RLS. Staff threads (referral_messages,
     * agency_thread_reads) are deliberately excluded — no capability route
     * reads them.
     */
    public function up(): void
    {
        $this->createPolicy(
            'referral_client_access_links',
            'referral_client_access_links_capability_session',
            'FOR SELECT',
            "id::text = NULLIF(current_setting('app.client_link_id', TRUE), '')",
            null
        );

        $this->createPolicy(
            'referral_client_requests',
            'referral_client_requests_capability_session',
            'FOR SELECT',
            "id::text = NULLIF(current_setting('app.client_request_id', TRUE), '')",
            null
        );
        $this->createPolicy(
            'referral_client_requests',
            'referral_client_requests_capability_update',
            'FOR UPDATE',
            "id::text = NULLIF(current_setting('app.client_request_id', TRUE), '')",
            "id::text = NULLIF(current_setting('app.client_request_id', TRUE), '')"
        );

        $this->createPolicy(
            'referral_client_request_items',
            'referral_client_request_items_capability_session',
            'FOR SELECT',
            "request_id::text = NULLIF(current_setting('app.client_request_id', TRUE), '')",
            null
        );

        $this->createPolicy(
            'referral_client_messages',
            'referral_client_messages_capability_session',
            'FOR SELECT',
            "request_id::text = NULLIF(current_setting('app.client_request_id', TRUE), '')",
            null
        );
        $this->createPolicy(
            'referral_client_messages',
            'referral_client_messages_capability_insert',
            'FOR INSERT',
            null,
            "request_id::text = NULLIF(current_setting('app.client_request_id', TRUE), '')"
        );

        $this->createPolicy(
            'referral_client_message_attachments',
            'referral_client_message_attachments_capability_session',
            'FOR SELECT',
            "EXISTS (SELECT 1 FROM referral_client_messages m WHERE m.id = message_id AND m.request_id::text = NULLIF(current_setting('app.client_request_id', TRUE), ''))",
            null
        );
        $this->createPolicy(
            'referral_client_message_attachments',
            'referral_client_message_attachments_capability_insert',
            'FOR INSERT',
            null,
            "EXISTS (SELECT 1 FROM referral_client_messages m WHERE m.id = message_id AND m.request_id::text = NULLIF(current_setting('app.client_request_id', TRUE), ''))"
        );
    }

    public function down(): void
    {
        $policies = [
            ['referral_client_access_links', 'referral_client_access_links_capability_session'],
            ['referral_client_requests', 'referral_client_requests_capability_session'],
            ['referral_client_requests', 'referral_client_requests_capability_update'],
            ['referral_client_request_items', 'referral_client_request_items_capability_session'],
            ['referral_client_messages', 'referral_client_messages_capability_session'],
            ['referral_client_messages', 'referral_client_messages_capability_insert'],
            ['referral_client_message_attachments', 'referral_client_message_attachments_capability_session'],
            ['referral_client_message_attachments', 'referral_client_message_attachments_capability_insert'],
        ];

        foreach ($policies as [$table, $policy]) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        }
    }

    private function createPolicy(string $table, string $name, string $command, ?string $using, ?string $check): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM pg_policy p JOIN pg_class c ON c.oid = p.polrelid WHERE c.relname = ? AND p.polname = ?',
            [$table, $name]
        );

        if ($exists !== null) {
            return;
        }

        $sql = "CREATE POLICY {$name} ON {$table} {$command} TO PUBLIC";
        if ($using !== null) {
            $sql .= " USING ({$using})";
        }
        if ($check !== null) {
            $sql .= " WITH CHECK ({$check})";
        }

        DB::statement($sql);
    }
};
