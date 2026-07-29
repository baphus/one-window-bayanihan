<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateSystemReport;
use App\Models\AuditLog;
use App\Models\GeneratedDocument;
use App\Services\Export\ColumnMaps;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DataExportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/DataExport/Index', [
            'tables' => ColumnMaps::getAllTables(),
        ]);
    }

    public function export()
    {
        $user = auth()->user();

        $filename = 'bayanihan-full-export-'.now()->format('Ymd-His').'.xlsx';

        $document = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'admin_full_export',
            'filename' => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => 'pending',
        ]);

        GenerateSystemReport::dispatch($document->id, 'admin_full_export', [
            'user_id' => $user->id,
        ]);

        AuditLog::create([
            'action' => AuditAction::EXPORT->value,
            'module' => AuditModule::DATA_EXPORT->value,
            'entity_id' => $user->id,
            'description' => sprintf('%s queued a full data workbook export (%d tables)', $user->name, count(ColumnMaps::getAllTables())),
            'new_value' => ['tables' => ColumnMaps::getAllTables(), 'filename' => $filename, 'document_id' => $document->id],
            'user_id' => $user->id,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->attributes->get('correlation_id') ?? request()->header('X-Request-ID') ?? (string) Str::uuid(),
        ]);

        return response()->json([
            'status' => 'pending',
            'id' => $document->id,
        ]);
    }
}
