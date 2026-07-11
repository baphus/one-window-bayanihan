<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable until audit:backfill-categories has run; write paths stamp it from deploy onward.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('module');
        });

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_category_check CHECK (category IS NULL OR category::text IN ('security', 'data', 'admin', 'system'))");
        DB::statement('CREATE INDEX idx_audit_logs_category_time ON audit_logs (category, timestamp DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_category_time');
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_category_check');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
