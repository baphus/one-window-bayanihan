<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            $table->uuid('sender_user_id');
            $table->text('body');
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('referral_id')->references('id')->on('referrals')->cascadeOnDelete();
            $table->foreign('sender_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['referral_id', 'created_at']);
        });

        Schema::create('agency_thread_reads', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('case_id');
            $table->uuid('peer_agency_id');
            $table->timestamp('last_read_at');

            $table->primary(['user_id', 'case_id', 'peer_agency_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->foreign('peer_agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });

        // Agency message threads are private agency-to-agency conversations scoped
        // to a case. Only AGENCY users whose agency is working on the case may
        // read or write their own pair's messages; case managers and admins are
        // deliberately excluded here — referral comments remain the CM<->agency
        // channel, while these threads exist for agency-to-agency coordination.
        foreach (['referral_messages', 'agency_thread_reads'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }

        $this->createPolicies();
    }

    private function createPolicies(): void
    {
        // referral_messages: a row is reachable by an AGENCY user when that user's
        // agency is working on the same case AND either the user's agency sent the
        // message or the anchoring referral belongs to the user's agency.
        DB::statement(<<<'SQL'
            CREATE POLICY referral_messages_agency_pair ON referral_messages FOR ALL TO PUBLIC
            USING (
                current_setting('app.user_role', TRUE) = 'AGENCY'
                AND EXISTS (
                    SELECT 1 FROM referrals r
                    WHERE r.case_id = (SELECT r2.case_id FROM referrals r2 WHERE r2.id = referral_id)
                      AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                )
                AND (
                    EXISTS (
                        SELECT 1 FROM users us
                        WHERE us.id = sender_user_id
                          AND us.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                    )
                    OR EXISTS (
                        SELECT 1 FROM referrals r3
                        WHERE r3.id = referral_id
                          AND r3.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                    )
                )
            )
            WITH CHECK (
                current_setting('app.user_role', TRUE) = 'AGENCY'
                AND EXISTS (
                    SELECT 1 FROM referrals r
                    WHERE r.case_id = (SELECT r2.case_id FROM referrals r2 WHERE r2.id = referral_id)
                      AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                )
                AND (
                    EXISTS (
                        SELECT 1 FROM users us
                        WHERE us.id = sender_user_id
                          AND us.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                    )
                    OR EXISTS (
                        SELECT 1 FROM referrals r3
                        WHERE r3.id = referral_id
                          AND r3.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                    )
                )
            )
            SQL);

        // agency_thread_reads: each agency user may only touch their own read
        // markers, and only for a peer agency that is actually working on the
        // same case alongside the user's own agency.
        DB::statement(<<<'SQL'
            CREATE POLICY agency_thread_reads_agency_own ON agency_thread_reads FOR ALL TO PUBLIC
            USING (
                current_setting('app.user_role', TRUE) = 'AGENCY'
                AND user_id = current_setting('app.current_user_id', TRUE)::uuid
                AND EXISTS (
                    SELECT 1 FROM referrals r
                    WHERE r.case_id = agency_thread_reads.case_id
                      AND r.agcy_id = peer_agency_id
                )
                AND EXISTS (
                    SELECT 1 FROM referrals r2
                    WHERE r2.case_id = agency_thread_reads.case_id
                      AND r2.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                )
            )
            WITH CHECK (
                current_setting('app.user_role', TRUE) = 'AGENCY'
                AND user_id = current_setting('app.current_user_id', TRUE)::uuid
                AND EXISTS (
                    SELECT 1 FROM referrals r
                    WHERE r.case_id = agency_thread_reads.case_id
                      AND r.agcy_id = peer_agency_id
                )
                AND EXISTS (
                    SELECT 1 FROM referrals r2
                    WHERE r2.case_id = agency_thread_reads.case_id
                      AND r2.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)
                )
            )
            SQL);
    }

    public function down(): void
    {
        // Both the current pair-based policies and the ones created by earlier
        // versions of this migration (which allowed case managers/admins and
        // used per-referral read markers) must be removed so rollbacks are
        // complete on databases that ran the older file content.
        $policies = [
            ['referral_messages', 'referral_messages_agency_pair'],
            ['referral_messages', 'referral_messages_admin_all'],
            ['referral_messages', 'referral_messages_agency_referred'],
            ['referral_messages', 'referral_messages_case_manager_own'],
            ['agency_thread_reads', 'agency_thread_reads_agency_own'],
        ];
        foreach ($policies as [$table, $policy]) {
            if (Schema::hasTable($table)) {
                DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
            }
        }

        foreach (['referral_messages', 'agency_thread_reads'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('agency_thread_reads');
        Schema::dropIfExists('referral_messages');
        Schema::dropIfExists('referral_message_reads');
    }
};
