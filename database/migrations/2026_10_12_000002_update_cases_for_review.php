<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->uuid('reviewed_by')->nullable()->after('intake_reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'deleted_at'], 'cases_status_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex('cases_status_deleted_at_index');
            $table->dropColumn(['reviewed_by', 'reviewed_at']);
        });
    }
};
