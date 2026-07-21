<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/DataExport/Index', [
            'tables' => ColumnMaps::getAllTables(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $user = auth()->user();
        $queries = new DataExportQueries;

        $tableQueryMap = [
            'cases' => fn () => $queries->getCases($user),
            'clients' => fn () => $queries->getClients($user),
            'referrals' => fn () => $queries->getReferrals($user),
            'users' => fn () => $queries->getUsers($user),
            'agencies' => fn () => $queries->getAgencies(),
            'services' => fn () => $queries->getServices(),
            'milestones' => fn () => $queries->getMilestones($user),
            'next_of_kin' => fn () => $queries->getNextOfKins($user),
            'feedback' => fn () => $queries->getFeedbacks($user),
            'case_documents' => fn () => $queries->getCaseDocuments($user),
            'client_addresses' => fn () => $queries->getClientAddresses($user),
            'client_employments' => fn () => $queries->getClientEmployments($user),
            'case_categories' => fn () => $queries->getCaseCategories(),
            'case_statuses' => fn () => $queries->getCaseStatuses(),
        ];

        $sheets = [];
        foreach (ColumnMaps::getAllTables() as $table) {
            $data = isset($tableQueryMap[$table]) ? $tableQueryMap[$table]() : collect();
            $sheets[] = [
                'title' => ucfirst($table),
                'columnMap' => ColumnMaps::getMap($table),
                'rows' => $data,
            ];
        }

        $filename = 'bayanihan-full-export-'.now()->format('Ymd-His').'.xlsx';

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

        return (new DataExportService)->generateMultiSheet($sheets, $filename);
    }
}
