<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::firstOrCreate(
            ['key' => 'otp_expiry_seconds'],
            ['value' => '300'],
        );
    }
}
