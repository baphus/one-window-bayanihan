<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns for context enrichment
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('user_id');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->uuid('request_id')->nullable()->after('user_agent');
            $table->string('prev_hash', 64)->nullable()->after('request_id');
        });

        // 2. Add composite indexes for common query patterns (using raw SQL for DESC ordering)
        DB::statement('CREATE INDEX idx_audit_logs_entity_lookup ON audit_logs (module, entity_id, timestamp DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_action_time ON audit_logs (action, timestamp DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_user_action ON audit_logs (user_id, action, timestamp DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_timestamp ON audit_logs (timestamp DESC)');

        // 3. Add trigram extension for ILIKE search (if not exists)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX idx_audit_logs_description_trgm ON audit_logs USING GIN (description gin_trgm_ops)');

        // 4. Add GIN indexes for JSONB column queries
        DB::statement('CREATE INDEX idx_audit_logs_old_value ON audit_logs USING GIN (old_value jsonb_path_ops)');
        DB::statement('CREATE INDEX idx_audit_logs_new_value ON audit_logs USING GIN (new_value jsonb_path_ops)');

        // 5. Create append-only trigger to prevent UPDATE/DELETE
        DB::statement('
            CREATE OR REPLACE FUNCTION block_audit_log_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION \'audit_logs are append-only: cannot modify or delete rows\';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        ');
        DB::statement('
            CREATE TRIGGER trg_audit_logs_append_only
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION block_audit_log_modification()
        ');
    }

    public function down(): void
    {
        // Drop trigger
        DB::statement('DROP TRIGGER IF EXISTS trg_audit_logs_append_only ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS block_audit_log_modification()');

        // Drop GIN indexes
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_old_value');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_new_value');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_description_trgm');
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');

        // Drop composite indexes
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_entity_lookup');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_action_time');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_user_action');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_timestamp');

        // Drop new columns
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'request_id', 'prev_hash']);
        });
    }
};
