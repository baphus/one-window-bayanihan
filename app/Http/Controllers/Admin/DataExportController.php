<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Jobs\ExportDataToExcel;
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
            'status' => 'pending',
        ]);

        AuditLog::create([
            'action' => AuditAction::EXPORT->value,
            'module' => AuditModule::DATA_EXPORT->value,
            'entity_id' => $user->id,
            'description' => sprintf('%s exported the full data workbook (%d tables)', $user->name, count(ColumnMaps::getAllTables())),
            'new_value' => ['tables' => ColumnMaps::getAllTables(), 'filename' => $filename],
            'user_id' => $user->id,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->attributes->get('correlation_id') ?? request()->header('X-Request-ID') ?? (string) Str::uuid(),
        ]);

        ExportDataToExcel::dispatch(
            'admin_full_export',
            [],
            $user->id,
            $document->id,
        );

        return response()->json([
            'id' => $document->id,
            'status' => 'pending',
        ]);
    }
}
