<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function entries(LogViewerService $service)
    {
        return response()->json($service->getLogs(
            perPage: (int) request('per_page', 50),
            level: request('level'),
            search: request('search'),
            dateFrom: request('date_from'),
            dateTo: request('date_to'),
        ));
    }

    public function download(LogViewerService $service)
    {
        $logs = $service->getLogs(
            perPage: 100000,
            level: request('level'),
            search: request('search'),
            dateFrom: request('date_from'),
            dateTo: request('date_to'),
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
