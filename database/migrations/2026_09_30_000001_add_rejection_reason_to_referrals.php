<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REASONS = "'INCOMPLETE_REQUIREMENTS', 'OUTSIDE_MANDATE', 'DUPLICATE_REFERRAL', 'CLIENT_WITHDREW', 'NO_SERVICE_CAPACITY', 'OTHER'";

    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('rejection_reason', 30)->nullable()->after('decision_comment');
            $table->index('rejection_reason');
        });

        DB::statement('ALTER TABLE referrals ADD CONSTRAINT referrals_rejection_reason_check CHECK (rejection_reason IS NULL OR rejection_reason IN ('.self::REASONS.'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_rejection_reason_check');

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['rejection_reason']);
            $table->dropColumn('rejection_reason');
        });
    }
};
