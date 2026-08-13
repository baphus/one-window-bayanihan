<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_client_message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('message_id')->references('id')->on('referral_client_messages')->cascadeOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['message_id']);
        });

        // Attachments inherit the same access model as the message they hang
        // off: administrators manage all rows, the receiving agency can read
        // and write rows belonging to its own referrals, and case managers
        // have read access to rows on cases they own. Client capability
        // traffic is mediated by Laravel (session + access link checks), so
        // no public policy exists here — mirroring referral_client_messages.
        DB::statement('ALTER TABLE referral_client_message_attachments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE referral_client_message_attachments FORCE ROW LEVEL SECURITY');

        DB::statement("CREATE POLICY referral_client_message_attachments_admin_all ON referral_client_message_attachments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'ADMIN') WITH CHECK (current_setting('app.user_role', TRUE) = 'ADMIN')");
        DB::statement("CREATE POLICY referral_client_message_attachments_agency_own ON referral_client_message_attachments FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_messages m JOIN referral_client_requests q ON q.id = m.request_id JOIN referrals r ON r.id = q.referral_id WHERE m.id = message_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid))) WITH CHECK (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM referral_client_messages m JOIN referral_client_requests q ON q.id = m.request_id JOIN referrals r ON r.id = q.referral_id WHERE m.id = message_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
        DB::statement("CREATE POLICY referral_client_message_attachments_case_manager_read ON referral_client_message_attachments FOR SELECT TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'CASE_MANAGER' AND EXISTS (SELECT 1 FROM referral_client_messages m JOIN referral_client_requests q ON q.id = m.request_id JOIN referrals r ON r.id = q.referral_id JOIN cases c ON c.id = r.case_id WHERE m.id = message_id AND c.user_id = current_setting('app.current_user_id', TRUE)::uuid))");
    }

    public function down(): void
    {
        foreach (['referral_client_message_attachments_admin_all', 'referral_client_message_attachments_agency_own', 'referral_client_message_attachments_case_manager_read'] as $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON referral_client_message_attachments");
        }

        DB::statement('ALTER TABLE referral_client_message_attachments NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE referral_client_message_attachments DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('referral_client_message_attachments');
    }
};
