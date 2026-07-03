<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'middle_name') && ! Schema::hasColumn('clients', 'middle_initial')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->renameColumn('middle_name', 'middle_initial');
            });
        }

        DB::table('clients')
            ->whereNotNull('middle_initial')
            ->where('middle_initial', '!=', '')
            ->update([
                'middle_initial' => DB::raw('upper(left(trim(middle_initial), 1))'),
            ]);

        DB::statement(<<<'SQL'
            UPDATE cases
            SET draft_client_data = (draft_client_data - 'middle_name')
                || jsonb_build_object('middle_initial', nullif(upper(left(trim(draft_client_data->>'middle_name'), 1)), ''))
            WHERE jsonb_exists(draft_client_data, 'middle_name')
        SQL);

        Schema::table('clients', function (Blueprint $table) {
            $table->string('middle_initial', 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('middle_initial')->nullable()->change();
        });

        if (Schema::hasColumn('clients', 'middle_initial') && ! Schema::hasColumn('clients', 'middle_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->renameColumn('middle_initial', 'middle_name');
            });
        }

        DB::statement(<<<'SQL'
            UPDATE cases
            SET draft_client_data = (draft_client_data - 'middle_initial')
                || jsonb_build_object('middle_name', draft_client_data->>'middle_initial')
            WHERE jsonb_exists(draft_client_data, 'middle_initial')
        SQL);
    }
};
