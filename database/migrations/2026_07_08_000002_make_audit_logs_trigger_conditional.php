<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Replace the trigger function to allow mutations when a session
        // variable is set. The PruneAuditLogs and BackfillAuditDescriptions
        // commands set app.allow_audit_mutations = 'true' before performing
        // their authorized mutations.
        DB::statement("
            CREATE OR REPLACE FUNCTION block_audit_log_modification()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF current_setting('app.allow_audit_mutations', true) = 'true' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'audit_logs are append-only: cannot modify or delete rows';
            END;
            \$\$ LANGUAGE plpgsql
        ");
    }

    public function down(): void
    {
        // Restore the strict append-only function
        DB::statement("
            CREATE OR REPLACE FUNCTION block_audit_log_modification()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'audit_logs are append-only: cannot modify or delete rows';
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql
        ");
    }
};
