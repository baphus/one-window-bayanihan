<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::firstOrCreate(
            ['key' => 'ip_whitelist_enabled'],
            ['value' => 'false'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'ip_whitelist_addresses'],
            ['value' => '127.0.0.1'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'max_login_attempts'],
            ['value' => '5'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'otp_expiry_seconds'],
            ['value' => '300'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'debug_tracking_otp_enabled'],
            ['value' => 'false'],
        );
    }
}
