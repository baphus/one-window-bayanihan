<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index(MaintenanceService $service)
    {
        return Inertia::render('Admin/Maintenance/Index', [
            'status' => $service->getStatus(),
        ]);
    }

    public function toggle(Request $request, MaintenanceService $service)
    {
        $status = $service->getStatus();

        if ($status['active']) {
            $service->disable();

            return back()->with('success', 'Maintenance mode disabled.');
        }

        $request->validate([
            'secret' => 'required|string|min:3',
            'retry_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $service->enable($request->secret, $request->retry_minutes);

        // Force a full browser navigation so the 503 page renders with
        // all scripts (CDN Tailwind) loaded.
        return Inertia::location('/');
    }
}
