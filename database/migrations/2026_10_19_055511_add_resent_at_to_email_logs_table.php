<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * resent_at marks the moment an admin re-queued a failed row. While it is
     * set the row is a pending retry: a second resend request is refused so a
     * single admin click can never put two copies of the same mail in flight,
     * and the delivery listener uses it to find the row to promote instead of
     * inserting a duplicate. Cleared again if the retry fails.
     */
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        if (! Schema::hasColumn('email_logs', 'resent_at')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->timestamp('resent_at', 0)->nullable()->after('delivered_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        if (Schema::hasColumn('email_logs', 'resent_at')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->dropColumn('resent_at');
            });
        }
    }
};
