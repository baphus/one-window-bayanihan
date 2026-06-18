<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->uuid('referral_id')->nullable()->after('agency_id');

            $table->foreign('referral_id')
                ->references('id')
                ->on('referrals')
                ->onDelete('set null');

            $table->unique(['case_id', 'agency_id', 'referral_id'], 'feedback_case_agency_referral_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
            $table->dropUnique('feedback_case_agency_referral_unique');
            $table->dropColumn('referral_id');
        });
    }
};
