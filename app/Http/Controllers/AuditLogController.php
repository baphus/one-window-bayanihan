<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $formatter = app(AuditLogFormatter::class);

        $query = AuditLog::with('user');

        $query->when($request->filled('action'), function ($q) use ($request) {
            $actions = explode(',', $request->input('action'));
            $q->whereIn('action', $actions);
        });

        $query->when($request->filled('module'), function ($q) use ($request) {
            $modules = explode(',', $request->input('module'));
            $q->whereIn('module', $modules);
        });

        $query->when($request->filled('user_id'), function ($q) use ($request) {
            $q->where('user_id', $request->input('user_id'));
        });

        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('timestamp', '>=', $request->input('date_from'));
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('timestamp', '<=', $request->input('date_to').' 23:59:59');
        });

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where(function ($sub) use ($search) {
                $sub->where('description', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        });

        $query->orderBy('timestamp', 'desc');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $logs = $query->paginate($perPage);

        $logs->getCollection()->transform(function ($log) use ($formatter) {
            if ($log->description === null) {
                try {
                    $log->description = $formatter->format($log);
                    $log->save();
                } catch (\Throwable $e) {
                    $log->description = $log->action.' '.$log->module;
                }
            }

            return $log;
        });

        $availableActions = AuditLog::distinct()->pluck('action')->values()->toArray();
        $availableModules = AuditLog::distinct()->pluck('module')->values()->toArray();
        $availableModulesLabels = collect($availableModules)->mapWithKeys(fn ($m) => [
            $m => $formatter->formatModule($m),
        ])->toArray();

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'availableActions' => $availableActions,
            'availableModules' => $availableModules,
            'availableModulesLabels' => $availableModulesLabels,
            'filterValues' => $request->only(['action', 'module', 'user_id', 'date_from', 'date_to', 'search', 'per_page']),
        ]);
    }
}
