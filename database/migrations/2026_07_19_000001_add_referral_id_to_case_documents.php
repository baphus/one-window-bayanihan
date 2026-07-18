<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_documents', function (Blueprint $table) {
            $table->foreignUuid('referral_id')->nullable()->constrained('referrals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('case_documents', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
            $table->dropColumn('referral_id');
        });
    }
};
