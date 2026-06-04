<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    /**
     * Get all active alerts for the authenticated user.
     */
    public function index(Request $request)
    {
        return response()->json(
            $this->alertService->getActiveAlerts(Auth::user()),
        );
    }

    /**
     * Dismiss an alert (soft-hide, never show again).
     */
    public function dismiss(string $id)
    {
        DB::table('alerts')
            ->where('id', $id)
            ->where('assigned_to_id', Auth::id())
            ->update(['dismissed_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark an alert as read.
     */
    public function read(string $id)
    {
        DB::table('alerts')
            ->where('id', $id)
            ->where('assigned_to_id', Auth::id())
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
