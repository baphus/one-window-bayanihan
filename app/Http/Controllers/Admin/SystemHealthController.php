<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Inertia\Inertia;

class SystemHealthController extends Controller
{
    public function index(SystemHealthService $service)
    {
        return Inertia::render('Admin/SystemHealth/Index', [
            'overview' => $service->getOverview(),
        ]);
    }

    public function runChecks(SystemHealthService $service)
    {
        $service->runAllChecks();

        return back()->with('success', 'Health checks completed successfully.');
    }
}
