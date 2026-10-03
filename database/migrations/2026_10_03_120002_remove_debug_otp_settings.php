<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Purge retired debug-OTP settings rows.
     *
     * The debug OTP backdoor (OTP values returned in API responses and
     * auto-filled on verification screens) has been removed entirely, so
     * these toggles no longer have any consumer.
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->whereIn('key', ['debug_otp_enabled', 'debug_tracking_otp_enabled'])
            ->delete();
    }

    public function down(): void
    {
        // Settings rows are user-managed; do not restore on rollback.
    }
};
