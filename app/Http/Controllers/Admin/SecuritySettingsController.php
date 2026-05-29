<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SecuritySettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecuritySettingsController extends Controller
{
    public function index(SecuritySettingsService $service)
    {
        return Inertia::render('Admin/Security/Index', [
            'settings' => $service->getSettings(),
        ]);
    }

    public function update(Request $request, SecuritySettingsService $service)
    {
        $validated = $request->validate([
            'password_min_length' => 'int|min:6|max:64',
            'password_require_special' => 'boolean',
            'password_require_numbers' => 'boolean',
            'password_expiry_days' => 'int|min:0|max:365',
            'session_lifetime_minutes' => 'int|min:15|max:1440',
            'max_login_attempts' => 'int|min:1|max:50',
            'lockout_duration_minutes' => 'int|min:1|max:1440',
            'ip_whitelist_enabled' => 'boolean',
            'ip_whitelist_ips' => 'nullable|string',
            'two_factor_required' => 'boolean',
        ]);

        $service->update($validated);

        return back()->with('success', 'Security settings updated.');
    }
}
