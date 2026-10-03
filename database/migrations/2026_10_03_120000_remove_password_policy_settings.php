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
                'password_min_length',
                'password_require_special',
                'password_require_numbers',
                'password_expiry_days',
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
