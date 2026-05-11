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
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'debug_otp_enabled' => ['required', 'boolean'],
        ]);

        SystemSetting::setValue('debug_otp_enabled', $request->boolean('debug_otp_enabled'));

        return back()->with('success', 'Settings updated successfully.');
    }
}
