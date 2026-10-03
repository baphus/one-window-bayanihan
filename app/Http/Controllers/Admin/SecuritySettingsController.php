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
            'two_factor_required' => 'boolean',
        ]);

        $service->update($validated);

        return back()->with('success', 'Security settings updated.');
    }
}
