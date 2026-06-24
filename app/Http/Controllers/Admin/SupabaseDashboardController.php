<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupabaseDashboardService;
use Inertia\Inertia;

class SupabaseDashboardController extends Controller
{
    public function index(SupabaseDashboardService $service)
    {
        return Inertia::render('Admin/Supabase/Index', $service->getDashboard());
    }
}
