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
            'debug_tracking_otp_enabled' => SystemSetting::getValue('debug_tracking_otp_enabled', false),
            'referral_overdue_days' => (int) SystemSetting::getValue('referral_overdue_days', 7),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'debug_otp_enabled' => ['nullable', 'boolean'],
            'debug_tracking_otp_enabled' => ['nullable', 'boolean'],
            'referral_overdue_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if ($request->has('debug_otp_enabled')) {
            SystemSetting::setValue('debug_otp_enabled', $request->boolean('debug_otp_enabled'));
        }

        if ($request->has('debug_tracking_otp_enabled')) {
            SystemSetting::setValue('debug_tracking_otp_enabled', $request->boolean('debug_tracking_otp_enabled'));
        }

        if ($request->has('referral_overdue_days')) {
            SystemSetting::setValue('referral_overdue_days', (int) $request->input('referral_overdue_days'));
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
