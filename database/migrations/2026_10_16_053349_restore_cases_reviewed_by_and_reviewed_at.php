<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cases')) {
            return;
        }

        if (! Schema::hasColumn('cases', 'reviewed_by')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->uuid('reviewed_by')->nullable();
            });
        }

        if (! Schema::hasColumn('cases', 'reviewed_at')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->timestamp('reviewed_at', 0)->nullable();
            });
        }

        if (Schema::hasColumn('cases', 'reviewed_by')
            && ! Schema::hasForeignKey('cases', ['reviewed_by'])) {
            Schema::table('cases', function (Blueprint $table) {
                $table->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('cases')) {
            return;
        }

        if (Schema::hasForeignKey('cases', ['reviewed_by'])) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropForeign(['reviewed_by']);
            });
        }

        $columns = [];
        foreach (['reviewed_by', 'reviewed_at'] as $column) {
            if (Schema::hasColumn('cases', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('cases', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
