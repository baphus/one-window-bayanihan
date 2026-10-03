<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->whereIn('key', [
                'session_lifetime_minutes',
                'max_login_attempts',
                'lockout_duration_minutes',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Settings rows are user-managed data; removal is not reversed.
    }
};
