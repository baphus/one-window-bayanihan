<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['intake_reviewed_by']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'client_type', 'draft_client_data', 'intake_reviewed_by']);
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('client_type', 20)->nullable();
            $table->jsonb('draft_client_data')->nullable();
            $table->string('source', 20)->default('internal');
            $table->uuid('intake_reviewed_by')->nullable();

            $table->foreign('intake_reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index('source');
        });
    }
};
