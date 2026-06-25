<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->where('key', 'like', 'chatbot_%')->delete();
    }

    public function down(): void
    {
        DB::table('system_settings')->insert([
            ['key' => 'chatbot_enabled', 'value' => 'false'],
            ['key' => 'chatbot_provider', 'value' => 'openai'],
            ['key' => 'chatbot_api_key', 'value' => ''],
            ['key' => 'chatbot_model', 'value' => 'gpt-4o-mini'],
            ['key' => 'chatbot_system_prompt', 'value' => ''],
            ['key' => 'chatbot_temperature', 'value' => '0.7'],
            ['key' => 'chatbot_max_tokens', 'value' => '500'],
            ['key' => 'chatbot_custom_endpoint', 'value' => ''],
        ]);
    }
};
