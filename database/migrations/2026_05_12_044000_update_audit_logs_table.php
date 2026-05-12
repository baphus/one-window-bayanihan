<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('description')->nullable()->after('entity_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check");
            DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action::text IN ('CREATE', 'UPDATE', 'DELETE', 'VIEW', 'LOGIN', 'LOGOUT'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check");
            DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action::text IN ('CREATE', 'UPDATE', 'DELETE', 'LOGIN'))");
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
