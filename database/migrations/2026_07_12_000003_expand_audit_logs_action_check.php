<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS_OLD = "'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'ARCHIVE', 'UNARCHIVE', 'PUBLISH'";

    private const ACTIONS_NEW = "'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'EXPORT', 'ARCHIVE', 'UNARCHIVE', 'PUBLISH'";

    public function up(): void
    {
        // NOT VALID + VALIDATE keeps the exclusive lock short on a hot append-only table.
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action::text IN ('.self::ACTIONS_NEW.')) NOT VALID');
        DB::statement('ALTER TABLE audit_logs VALIDATE CONSTRAINT audit_logs_action_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action::text IN ('.self::ACTIONS_OLD.')) NOT VALID');
        DB::statement('ALTER TABLE audit_logs VALIDATE CONSTRAINT audit_logs_action_check');
    }
};
