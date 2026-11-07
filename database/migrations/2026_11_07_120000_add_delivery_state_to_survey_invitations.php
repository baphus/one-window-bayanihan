<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_invitations', function (Blueprint $table) {
            // Legacy invitations have no pending token and must never be resent.
            $table->text('pending_token')->nullable();
            $table->timestampTz('queued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('survey_invitations', function (Blueprint $table) {
            $table->dropColumn(['pending_token', 'queued_at']);
        });
    }
};
