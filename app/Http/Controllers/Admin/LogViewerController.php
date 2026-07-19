<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogViewerRequest;
use App\Services\LogViewerService;
use Inertia\Inertia;

class LogViewerController extends Controller
{
    public function index(LogViewerService $service)
    {
        return Inertia::render('Admin/LogViewer/Index', [
            'dates' => $service->getAvailableDates(),
        ]);
    }

    public function entries(LogViewerService $service, LogViewerRequest $request)
    {
        return response()->json($service->getLogs(
            perPage: (int) $request->validated('per_page', 50),
            level: $request->validated('level'),
            search: $request->validated('search'),
            dateFrom: $request->validated('date_from'),
            dateTo: $request->validated('date_to'),
            page: (int) $request->validated('page', 1),
        ));
    }

    public function download(LogViewerService $service, LogViewerRequest $request)
    {
        $logs = $service->getLogs(
            perPage: 100000,
            level: $request->validated('level'),
            search: $request->validated('search'),
            dateFrom: $request->validated('date_from'),
            dateTo: $request->validated('date_to'),
        );

        $content = '';
        foreach ($logs['entries'] as $entry) {
            $content .= "[{$entry['timestamp']}] {$entry['environment']}.{$entry['level']}: {$entry['message']}\n";
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="system-logs-'.date('Y-m-d').'.txt"',
        ]);
    }
}
