<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_services', function (Blueprint $table) {
            $table->string('status')->default('ASSIGNED');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::statement("UPDATE referral_services SET status = 'COMPLETED' WHERE referral_id IN (SELECT id FROM referrals WHERE status = 'COMPLETED')");
        DB::statement("ALTER TABLE referral_services ADD CONSTRAINT referral_services_status_check CHECK (status IN ('ASSIGNED', 'PROCESSING', 'COMPLETED'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE referral_services DROP CONSTRAINT referral_services_status_check');
        Schema::table('referral_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['status', 'started_at', 'completed_at']);
        });
    }
};
