<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every table whose rows point at a storage object the orphan sweep owns.
     *
     * @var list<string>
     */
    private const TABLES = [
        'case_documents',
        'referral_attachments',
        'referral_client_message_attachments',
    ];

    /**
     * Run the migrations.
     *
     * files_deleted_at records that the bucket object behind this row is gone.
     * Without it a row the sweep has already emptied is indistinguishable from
     * one it has not reached, so every nightly run re-issues the same DELETE for
     * the same key forever — which is what made the sweep retry (and log) the
     * same misses on every run.
     *
     * The stamp is a separate nullable column rather than a cleared file_path
     * because file_path is NOT NULL on all three tables and is the durable
     * record of what used to exist: it still drives the audit trail and tells a
     * reviewer that a document row survived while its bytes did not.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'files_deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('files_deleted_at', 0)->nullable()->after('deleted_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'files_deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('files_deleted_at');
            });
        }
    }
};
