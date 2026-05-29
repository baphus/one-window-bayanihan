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
            $msg = 'Maintenance mode disabled.';
        } else {
            $request->validate([
                'secret' => 'nullable|string|min:3',
                'retry_minutes' => 'nullable|integer|min:1|max:1440',
            ]);
            $service->enable($request->secret, $request->retry_minutes);
            $msg = 'Maintenance mode enabled.';
        }

        return back()->with('success', $msg);
    }
}
