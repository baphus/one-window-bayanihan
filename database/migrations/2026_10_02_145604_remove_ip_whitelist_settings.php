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
            ->whereIn('key', ['ip_whitelist_enabled', 'ip_whitelist_ips', 'ip_whitelist_addresses'])
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
