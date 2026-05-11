<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->orderBy('timestamp', 'desc')
            ->paginate(20);

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
        ]);
    }
}
