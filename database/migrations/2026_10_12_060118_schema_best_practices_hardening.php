<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // (a) clients name/contact cols string(255) -> TEXT for encrypted-ciphertext headroom.
        // Raw ALTER avoids doctrine/dbal dependency. TYPE change preserves NOT NULL / nullable.
        foreach (['first_name', 'last_name', 'middle_name', 'suffix', 'email', 'contact_number'] as $column) {
            if (Schema::hasColumn('clients', $column)) {
                DB::statement("ALTER TABLE clients ALTER COLUMN {$column} TYPE TEXT");
            }
        }

        // (b) next_of_kin names -> TEXT. middle_name already widened to 255 by
        // 2026_08_13 rename; handle leftover middle_initial defensively.
        foreach (['first_name', 'last_name', 'middle_name'] as $column) {
            if (Schema::hasColumn('next_of_kin', $column)) {
                DB::statement("ALTER TABLE next_of_kin ALTER COLUMN {$column} TYPE TEXT");
            }
        }
        if (Schema::hasColumn('next_of_kin', 'middle_initial')) {
            DB::statement('ALTER TABLE next_of_kin ALTER COLUMN middle_initial TYPE VARCHAR(255)');
        }

        // (c) PSGC code cols for future writes (names stay source of truth, no backfill).
        foreach (['client_addresses', 'next_of_kin'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('region_code', 20)->nullable();
                $table->string('province_code', 20)->nullable();
                $table->string('city_municipality_code', 20)->nullable();
                $table->string('barangay_code', 20)->nullable();
            });
        }

        // (d) audit_logs append-only: trigger blocks soft-delete UPDATE, zero app reads
        // of is_deleted — drop the dead cols + FK.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['is_deleted', 'deleted_at', 'deleted_by']);
        });

        // (e) trgm index on referrals.required_services (pg_trgm already enabled).
        DB::statement('CREATE INDEX IF NOT EXISTS referrals_required_services_trgm ON referrals USING gin (required_services gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS referrals_required_services_trgm');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        foreach (['client_addresses', 'next_of_kin'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['region_code', 'province_code', 'city_municipality_code', 'barangay_code']);
            });
        }

        if (Schema::hasColumn('next_of_kin', 'middle_initial')) {
            DB::statement('ALTER TABLE next_of_kin ALTER COLUMN middle_initial TYPE VARCHAR(1)');
        }
        foreach (['first_name', 'last_name', 'middle_name'] as $column) {
            if (Schema::hasColumn('next_of_kin', $column)) {
                DB::statement("ALTER TABLE next_of_kin ALTER COLUMN {$column} TYPE VARCHAR(255)");
            }
        }

        foreach (['first_name', 'last_name', 'middle_name', 'suffix', 'email', 'contact_number'] as $column) {
            if (Schema::hasColumn('clients', $column)) {
                DB::statement("ALTER TABLE clients ALTER COLUMN {$column} TYPE VARCHAR(255)");
            }
        }
    }
};
