<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clients', 'next_of_kin'] as $table) {
            if (Schema::hasColumn($table, 'middle_initial') && ! Schema::hasColumn($table, 'middle_name')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->renameColumn('middle_initial', 'middle_name');
                });
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('middle_name', 255)->nullable()->change();
            });
        }

        DB::statement(<<<'SQL'
            UPDATE cases
            SET draft_client_data = (draft_client_data - 'middle_initial')
                || jsonb_build_object('middle_name', draft_client_data->'middle_initial')
            WHERE jsonb_exists(draft_client_data, 'middle_initial')
        SQL);
    }

    public function down(): void
    {
        foreach (['clients', 'next_of_kin'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('middle_name', 1)->nullable()->change();
            });

            if (Schema::hasColumn($table, 'middle_name') && ! Schema::hasColumn($table, 'middle_initial')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->renameColumn('middle_name', 'middle_initial');
                });
            }
        }

        DB::statement(<<<'SQL'
            UPDATE cases
            SET draft_client_data = (draft_client_data - 'middle_name')
                || jsonb_build_object('middle_initial', nullif(upper(left(trim(draft_client_data->>'middle_name'), 1)), ''))
            WHERE jsonb_exists(draft_client_data, 'middle_name')
        SQL);
    }
};
