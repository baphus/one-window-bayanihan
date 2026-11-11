<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrations run without a cache service during deployment; avoid SystemSetting::setValue().
        DB::table('system_settings')->where('key', 'mfa_role_ofw_required')->update([
            'value' => 'false',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the new policy and any administrator changes.
    }
};
