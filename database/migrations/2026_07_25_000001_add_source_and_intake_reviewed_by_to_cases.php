<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('source', 20)->default('internal')->after('deletion_reason');
            $table->uuid('intake_reviewed_by')->nullable()->after('source');

            $table->foreign('intake_reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['intake_reviewed_by']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'intake_reviewed_by']);
        });
    }
};
