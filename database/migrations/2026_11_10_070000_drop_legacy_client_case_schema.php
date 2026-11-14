<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyTables = ['case_category', 'client_addresses', 'client_employments', 'next_of_kin', 'clients', 'case_issues'];
        $nonEmptyTables = array_filter($legacyTables, fn (string $table): bool => Schema::hasTable($table) && DB::table($table)->exists());
        $legacyLinks = [];

        foreach (['cases' => ['client_id', 'category_id', 'case_issue_id'], 'users' => ['client_id']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && DB::table($table)->whereNotNull($column)->exists()) {
                    $legacyLinks[] = "{$table}.{$column}";
                }
            }
        }

        if ($nonEmptyTables !== [] || $legacyLinks !== []) {
            throw new RuntimeException('Legacy client cutover refused: non-empty tables ['.implode(', ', $nonEmptyTables).'] or populated legacy links ['.implode(', ', $legacyLinks).'].');
        }

        Schema::table('cases', function ($table): void {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['case_issue_id']);
        });

        Schema::table('users', function ($table): void {
            $table->dropForeign(['client_id']);
        });

        foreach (['case_category', 'client_addresses', 'client_employments', 'next_of_kin', 'case_issues', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('cases', function ($table): void {
            $table->dropColumn(['client_id', 'category_id', 'case_issue_id']);
        });

        Schema::table('users', function ($table): void {
            $table->dropColumn('client_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This migration is irreversible: dropped legacy client data cannot be restored.');
    }
};
