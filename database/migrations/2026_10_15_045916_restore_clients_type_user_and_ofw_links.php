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
        if (! Schema::hasTable('clients')) {
            return;
        }

        if (! Schema::hasColumn('clients', 'type')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('type', 20)->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('clients', 'user_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->uuid('user_id')->nullable()->after('type');
            });
        }

        if (! Schema::hasColumn('clients', 'ofw_client_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->uuid('ofw_client_id')->nullable()->after('user_id');
            });
        }

        if (Schema::hasColumn('clients', 'user_id')
            && ! Schema::hasForeignKey('clients', ['user_id'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('clients', 'ofw_client_id')
            && ! Schema::hasForeignKey('clients', ['ofw_client_id'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreign('ofw_client_id')->references('id')->on('clients')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('clients', 'user_id')
            && ! Schema::hasIndex('clients', ['user_id'], 'unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }

        if (Schema::hasColumn('clients', 'ofw_client_id')
            && ! Schema::hasIndex('clients', ['ofw_client_id'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->index('ofw_client_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        if (Schema::hasForeignKey('clients', ['user_id'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        if (Schema::hasForeignKey('clients', ['ofw_client_id'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropForeign(['ofw_client_id']);
            });
        }

        if (Schema::hasIndex('clients', ['user_id'], 'unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique(['user_id']);
            });
        }

        if (Schema::hasIndex('clients', ['ofw_client_id'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex(['ofw_client_id']);
            });
        }

        $columns = [];
        foreach (['type', 'user_id', 'ofw_client_id'] as $column) {
            if (Schema::hasColumn('clients', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('clients', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
