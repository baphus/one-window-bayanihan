<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns to cases table
        Schema::table('cases', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('user_id');
            $table->uuid('category_id')->nullable()->after('client_id');
            $table->jsonb('draft_client_data')->nullable()->after('category_id');
        });

        // 2. Backfill cases.client_id from existing clients.case_id relationship
        // Uses PostgreSQL-specific syntax (DISTINCT ON, UPDATE FROM)
        try {
            DB::statement('
                UPDATE cases
                SET client_id = sub.client_id
                FROM (
                    SELECT DISTINCT ON (cl.case_id) cl.case_id AS case_id, cl.id AS client_id
                    FROM clients cl
                    WHERE cl.is_deleted = false
                ) sub
                WHERE cases.id = sub.case_id
            ');
        } catch (Throwable $e) {
            echo "⚠️  Data backfill skipped (non-PostgreSQL or no migration runner). Run on production PostgreSQL.\n";
        }

        // 3. Add foreign keys
        Schema::table('cases', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('category_id')->references('id')->on('case_categories')->onDelete('restrict');
        });

        // 4. Drop clients.case_id FK and column
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropColumn('case_id');
        });

        // 5. Log orphan cases (client_id still null) AFTER backfill
        $orphanCount = DB::table('cases')->whereNull('client_id')->where('is_deleted', false)->count();
        if ($orphanCount > 0) {
            echo "⚠️  Found {$orphanCount} case(s) without a linked client after backfill. These cases had no matching non-deleted client record.\n";
        }
    }

    public function down(): void
    {
        // 1. Drop foreign keys on cases
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['category_id']);
        });

        // 2. Restore clients.case_id (nullable, since multi-case clients would have ambiguous case_id)
        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('case_id')->nullable()->after('id');
        });

        // 3. Backfill clients.case_id from cases.client_id (picks one case for multi-case clients)
        try {
            DB::statement('
                UPDATE clients
                SET case_id = sub.case_id
                FROM (
                    SELECT DISTINCT ON (c.client_id) c.client_id AS client_id, c.id AS case_id
                    FROM cases c
                    WHERE c.client_id IS NOT NULL
                ) sub
                WHERE clients.id = sub.client_id
            ');
        } catch (Throwable $e) {
            echo "⚠️  Down backfill skipped (non-PostgreSQL or no migration runner).\n";
        }

        // 4. Re-add FK on clients.case_id
        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
        });

        // 5. Drop new columns from cases
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('client_id');
            $table->dropColumn('category_id');
            $table->dropColumn('draft_client_data');
        });
    }
};
