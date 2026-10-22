<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogViewerRequest;
use App\Services\LogViewerService;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogViewerController extends Controller
{
    public function index(LogViewerService $service)
    {
        return Inertia::render('Admin/LogViewer/Index');
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

    /**
     * Stream the filtered log file contents to the client instead of building
     * the whole export in memory. The lines, filters, redaction and the
     * MAX_SCANNED_LINES cap are identical to the paginated viewer path
     * (LogViewerService::streamLogs), and the download is delivered as a
     * text/plain attachment named system-logs-{Y-m-d}.txt.
     *
     * The generator is consumed in a single pass, so the scan cap applies to
     * the download as a whole rather than once per page.
     */
    public function download(LogViewerService $service, LogViewerRequest $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($service, $request): void {
            foreach ($service->streamLogs(
                level: $request->validated('level'),
                search: $request->validated('search'),
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ) as $entry) {
                echo "[{$entry['timestamp']}] {$entry['environment']}.{$entry['level']}: {$entry['message']}\n";
            }
        }, 'system-logs-'.date('Y-m-d').'.txt', [
            'Content-Type' => 'text/plain',
        ]);
    }
}
