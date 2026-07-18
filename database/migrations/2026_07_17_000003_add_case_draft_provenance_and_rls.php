<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_drafts', function (Blueprint $table) {
            $table->string('consent_notice_version', 100)->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->uuid('selected_nok_id')->nullable();
            $table->jsonb('selected_nok_evidence')->nullable();
            $table->foreign('selected_nok_id')->references('id')->on('next_of_kin')->onDelete('restrict');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->string('consent_notice_version', 100)->nullable();
            $table->uuid('selected_nok_id')->nullable();
            $table->jsonb('selected_nok_evidence')->nullable();
            $table->foreign('selected_nok_id')->references('id')->on('next_of_kin')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_consent_notice_check CHECK (consent_accepted_at IS NULL OR consent_notice_version IS NOT NULL)');
        DB::statement("ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_nok_evidence_check CHECK (state = 'EDITING' OR selected_nok_id IS NULL OR selected_nok_evidence IS NOT NULL)");
        DB::statement('ALTER TABLE case_drafts ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY case_drafts_owner_all ON case_drafts FOR ALL TO PUBLIC USING (owner_id = NULLIF(current_setting('app.current_user_id', TRUE), '')::uuid) WITH CHECK (owner_id = NULLIF(current_setting('app.current_user_id', TRUE), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS case_drafts_owner_all ON case_drafts');
        DB::statement('ALTER TABLE case_drafts DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE case_drafts DROP CONSTRAINT IF EXISTS case_drafts_consent_notice_check');
        DB::statement('ALTER TABLE case_drafts DROP CONSTRAINT IF EXISTS case_drafts_nok_evidence_check');

        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['selected_nok_id']);
            $table->dropColumn(['consent_notice_version', 'selected_nok_id', 'selected_nok_evidence']);
        });

        Schema::table('case_drafts', function (Blueprint $table) {
            $table->dropForeign(['selected_nok_id']);
            $table->dropColumn(['consent_notice_version', 'consent_accepted_at', 'selected_nok_id', 'selected_nok_evidence']);
        });
    }
};
