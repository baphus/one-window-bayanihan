<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cases DROP CONSTRAINT IF EXISTS cases_status_check');
        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_sex_check');

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action::text IN ('CREATE', 'UPDATE', 'DELETE', 'VIEW', 'LOGIN', 'LOGOUT', 'ARCHIVE', 'UNARCHIVE', 'PUBLISH'))");
    }

    public function down(): void {}
};
