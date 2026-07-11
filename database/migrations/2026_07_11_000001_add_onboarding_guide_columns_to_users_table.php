<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('seen_page_guides')->nullable()->after('onboarding_step');
            $table->json('checklist_progress')->nullable()->after('seen_page_guides');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['seen_page_guides', 'checklist_progress']);
        });
    }
};
