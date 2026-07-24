<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Models\User;
use App\Notifications\DownloadReady;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\Reports\ReportsExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportDataToExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $type,
        public readonly array $criteria,
        public readonly string $userId,
        public readonly string $generatedDocumentId,
    ) {
        $this->onQueue('heavy');
    }

    public function handle(ReportsExportService $reportsExportService): void
    {
        $user = User::findOrFail($this->userId);
        $queries = new DataExportQueries;
        $exportService = new DataExportService;

        $tempPath = tempnam(sys_get_temp_dir(), 'export_');

        try {
            match ($this->type) {
                'cases_export' => $this->generateSingleSheetExport(
                    $exportService, $queries, $user, $tempPath,
                    'Cases',
                    fn () => $queries->getCasesExport($user, $this->criteria['filters'] ?? []),
                    \App\Http\Controllers\CaseController::casesExportColumnMap(),
                ),
                'clients_export' => $this->generateSingleSheetExport(
                    $exportService, $queries, $user, $tempPath,
                    'Clients',
                    fn () => $queries->getClientsExport($user, $this->criteria['filters'] ?? []),
                    \App\Http\Controllers\ClientController::clientsExportColumnMap(),
                ),
                'referrals_export' => $this->generateSingleSheetExport(
                    $exportService, $queries, $user, $tempPath,
                    'Referrals',
                    fn () => $queries->getReferralsExport($user, $this->criteria['filters'] ?? []),
                    \App\Http\Controllers\ReferralController::referralsExportColumnMap(),
                ),
                'reports_export' => $this->generateReportsExport(
                    $exportService, $reportsExportService, $tempPath,
                ),
                'admin_full_export' => $this->generateAdminFullExport(
                    $exportService, $queries, $user, $tempPath,
                ),
                default => throw new \InvalidArgumentException("Unknown export type: {$this->type}"),
            };

            $fileContent = file_get_contents($tempPath);
            $document = GeneratedDocument::findOrFail($this->generatedDocumentId);
            $s3Path = "generated/{$this->userId}/{$document->filename}";

            Storage::disk('supabase')->put($s3Path, $fileContent);

            $document->update([
                'status' => 'completed',
                'path' => $s3Path,
                'file_size' => strlen($fileContent),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

            $user->notify(new DownloadReady($document->fresh()));
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function generateSingleSheetExport(
        DataExportService $exportService,
        DataExportQueries $queries,
        User $user,
        string $tempPath,
        string $sheetTitle,
        callable $dataFn,
        array $columnMap,
    ): void {
        $data = $dataFn();

        $now = now()->format('Y-m-d H:i:s');
        $data = $data->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $exportService->generateSingleSheetToFile($sheetTitle, $columnMap, $data, $tempPath);
    }

    private function generateReportsExport(
        DataExportService $exportService,
        ReportsExportService $reportsExportService,
        string $tempPath,
    ): void {
        $sheets = $reportsExportService->buildExcelSheetsFromCriteria($this->criteria);
        $exportService->generateMultiSheetToFile($sheets, $tempPath);
    }

    private function generateAdminFullExport(
        DataExportService $exportService,
        DataExportQueries $queries,
        User $user,
        string $tempPath,
    ): void {
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

        $exportService->generateMultiSheetToFile($sheets, $tempPath);
    }

    public function failed(Throwable $e): void
    {
        $document = GeneratedDocument::find($this->generatedDocumentId);
        if ($document) {
            $document->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new DownloadReady($document->fresh(), failed: true));
            }
        }
    }
}
