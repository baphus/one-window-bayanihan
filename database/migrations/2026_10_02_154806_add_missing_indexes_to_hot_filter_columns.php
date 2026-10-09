<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These tables define uuid() + foreign() without index(), and
     * PostgreSQL does not index foreign keys automatically. Every
     * dashboard/listing query filters on these columns (including the
     * is_deleted = false soft-delete flag), so without indexes they
     * resolve to sequential scans.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->index('user_id', 'cases_user_id_index');
            $table->index('client_id', 'cases_client_id_index');
            $table->index('status', 'cases_status_index');
            $table->index('is_deleted', 'cases_is_deleted_index');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->index('case_id', 'referrals_case_id_index');
            $table->index('agcy_id', 'referrals_agcy_id_index');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->index('refr_id', 'milestones_refr_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex('milestones_refr_id_index');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex('referrals_case_id_index');
            $table->dropIndex('referrals_agcy_id_index');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex('cases_user_id_index');
            $table->dropIndex('cases_client_id_index');
            $table->dropIndex('cases_status_index');
            $table->dropIndex('cases_is_deleted_index');
        });
    }
};
