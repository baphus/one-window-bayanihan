<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('SystemSettings/Index', [
            'debug_otp_enabled' => SystemSetting::getValue('debug_otp_enabled', false),
            'referral_overdue_days' => (int) SystemSetting::getValue('referral_overdue_days', 7),
            'chatbot_enabled' => SystemSetting::getValue('chatbot_enabled', false) === 'true' || SystemSetting::getValue('chatbot_enabled', false) === true,
            'chatbot_provider' => SystemSetting::getValue('chatbot_provider', 'openai'),
            'chatbot_model' => SystemSetting::getValue('chatbot_model', 'gpt-4o-mini'),
            'chatbot_system_prompt' => SystemSetting::getValue('chatbot_system_prompt', ''),
            'chatbot_temperature' => (float) SystemSetting::getValue('chatbot_temperature', 0.7),
            'chatbot_max_tokens' => (int) SystemSetting::getValue('chatbot_max_tokens', 500),
            'chatbot_custom_endpoint' => SystemSetting::getValue('chatbot_custom_endpoint', ''),
            'has_chatbot_api_key' => ! empty(SystemSetting::getValue('chatbot_api_key', '')),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'debug_otp_enabled' => ['nullable', 'boolean'],
            'referral_overdue_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'chatbot_enabled' => ['nullable', 'boolean'],
            'chatbot_provider' => ['nullable', 'in:openai,anthropic,gemini,custom'],
            'chatbot_api_key' => ['nullable', 'string', 'max:500'],
            'chatbot_model' => ['nullable', 'string', 'max:255'],
            'chatbot_system_prompt' => ['nullable', 'string', 'max:2000'],
            'chatbot_temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'chatbot_max_tokens' => ['nullable', 'integer', 'min:100', 'max:4000'],
            'chatbot_custom_endpoint' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->has('debug_otp_enabled')) {
            SystemSetting::setValue('debug_otp_enabled', $request->boolean('debug_otp_enabled'));
        }

        if ($request->has('referral_overdue_days')) {
            SystemSetting::setValue('referral_overdue_days', (int) $request->input('referral_overdue_days'));
        }

        if ($request->has('chatbot_enabled')) {
            SystemSetting::setValue('chatbot_enabled', $request->boolean('chatbot_enabled') ? 'true' : 'false');
        }
        if ($request->has('chatbot_provider')) {
            SystemSetting::setValue('chatbot_provider', $request->input('chatbot_provider'));
        }
        if ($request->has('chatbot_api_key') && ! empty($request->input('chatbot_api_key'))) {
            SystemSetting::setValue('chatbot_api_key', $request->input('chatbot_api_key'));
        }
        if ($request->has('chatbot_model')) {
            SystemSetting::setValue('chatbot_model', $request->input('chatbot_model'));
        }
        if ($request->has('chatbot_system_prompt')) {
            SystemSetting::setValue('chatbot_system_prompt', $request->input('chatbot_system_prompt'));
        }
        if ($request->has('chatbot_temperature')) {
            SystemSetting::setValue('chatbot_temperature', $request->input('chatbot_temperature'));
        }
        if ($request->has('chatbot_max_tokens')) {
            SystemSetting::setValue('chatbot_max_tokens', $request->input('chatbot_max_tokens'));
        }
        if ($request->has('chatbot_custom_endpoint')) {
            SystemSetting::setValue('chatbot_custom_endpoint', $request->input('chatbot_custom_endpoint'));
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
