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
            ['value' => 'false', 'description' => 'Enable IP whitelist restrictions for admin routes'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'ip_whitelist_addresses'],
            ['value' => '127.0.0.1', 'description' => 'Comma-separated list of allowed IPs/CIDRs for admin routes'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'max_login_attempts'],
            ['value' => '5', 'description' => 'Maximum login attempts before rate limiting'],
        );

        SystemSetting::firstOrCreate(
            ['key' => 'otp_expiry_seconds'],
            ['value' => '300', 'description' => 'OTP expiry in seconds'],
        );
    }
}
