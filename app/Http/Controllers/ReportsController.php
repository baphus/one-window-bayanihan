<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
        );

        $data['managedCases'] = $this->reportsService->getManagedCases(
            userId: $user->id,
            role: $user->role,
        );
        $data['managedReferrals'] = $this->reportsService->getManagedReferrals(
            userId: $user->id,
            role: $user->role,
        );
        $data['managedClients'] = $this->reportsService->getManagedClients();
        $data['role'] = $user->role;

        return Inertia::render('Reports/Index', $data);
    }
}
