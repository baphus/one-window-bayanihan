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
            ['key' => 'chatbot_enabled'],
            ['value' => 'false'],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_provider'],
            ['value' => 'openai'],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_api_key'],
            ['value' => ''],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_model'],
            ['value' => 'gpt-4o-mini'],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_system_prompt'],
            ['value' => 'You are a helpful assistant for the Bayanihan One Window support system. You help OFWs and their families with questions about case tracking, referrals, agency services, and document requirements. Keep responses concise and professional.'],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_custom_endpoint'],
            ['value' => ''],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_max_tokens'],
            ['value' => '500'],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'chatbot_temperature'],
            ['value' => '0.7'],
        );
        SystemSetting::firstOrCreate(
            ['key' => 'debug_tracking_otp_enabled'],
            ['value' => 'false'],
        );
    }
}
