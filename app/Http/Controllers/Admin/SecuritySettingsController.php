<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecuritySettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Security/Index', [
            'settings' => [
                'two_factor_required' => SystemSetting::getValue('two_factor_required', false),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'two_factor_required' => 'boolean',
        ]);

        $changedKeys = [];

        if (array_key_exists('two_factor_required', $validated)) {
            SystemSetting::setValue('two_factor_required', $validated['two_factor_required'], 'security', 'Security setting: two_factor_required');
            $changedKeys[] = 'two_factor_required';
        }

        if ($changedKeys !== []) {
            AuditLogger::updateSecurity()
                ->byId(auth()->id())
                ->module(AuditModule::SYSTEM_SETTING)
                ->context(null, ['keys' => $changedKeys])
                ->write();
        }

        return back()->with('success', 'Security settings updated.');
    }
}
