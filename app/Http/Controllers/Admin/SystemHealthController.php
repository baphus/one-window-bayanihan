<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Inertia\Inertia;

/**
 * System health overview for admins.
 *
 * Read-only. The same checks backing /api/readyz, presented with the detail
 * that endpoint deliberately withholds from its response body (it answers by
 * status code for an external monitor; this page is for a human).
 */
class SystemHealthController extends Controller
{
    public function index(SystemHealthService $health)
    {
        $checks = $health->checks();

        return Inertia::render('Admin/Health/Index', [
            'checks' => $checks,
            'healthy' => $health->isHealthy($checks),
            'checkedAt' => now()->toIso8601String(),
        ]);
    }
}
