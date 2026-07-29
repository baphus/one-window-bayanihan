<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Models\User;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\Reports\ReportsExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateSystemReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  string  $documentId  UUID of the pre-created GeneratedDocument record.
     * @param  string  $type  'system_report_pdf' | 'admin_full_export'
     * @param  array  $criteria  Serializable criteria from ReportsExportService::extractCriteria()
     *                           (used for PDF and reports-excel types only).
     */
    public function __construct(
        private readonly string $documentId,
        private readonly string $type,
        private readonly array $criteria = [],
    ) {}

    public function handle(
        ReportsExportService $reportsExportService,
        DataExportService $dataExportService,
    ): void {
        $document = GeneratedDocument::find($this->documentId);

        if (! $document) {
            Log::error('GenerateSystemReport: GeneratedDocument not found', ['id' => $this->documentId]);

            return;
        }

        try {
            $tempPath = sys_get_temp_dir().'/'.Str::uuid();

            match ($this->type) {
                'system_report_pdf' => $this->generatePdf($reportsExportService, $tempPath),
                'admin_full_export' => $this->generateAdminExport($dataExportService, $tempPath),
                default => throw new \InvalidArgumentException("Unknown report type: {$this->type}"),
            };

            $storagePath = 'generated-documents/'.$this->documentId.'/'.$document->filename;
            Storage::disk('object-storage')->put($storagePath, file_get_contents($tempPath));

            $document->update([
                'status' => 'completed',
                'path' => $storagePath,
                'file_size' => filesize($tempPath),
            ]);

            @unlink($tempPath);
        } catch (\Throwable $e) {
            Log::error('GenerateSystemReport failed', [
                'document_id' => $this->documentId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            @unlink($tempPath ?? null);

            $this->fail($e);
        }
    }

    private function generatePdf(ReportsExportService $service, string $tempPath): void
    {
        $payload = $service->buildPdfPayloadFromCriteria($this->criteria);
        $pdf = Pdf::loadView('pdf.report', $payload);
        $pdf->save($tempPath);
    }

    private function generateAdminExport(DataExportService $dataExportService, string $tempPath): void
    {
        // Rebuild the user from criteria so queries are scoped correctly.
        $user = User::findOrFail($this->criteria['user_id']);

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

        $dataExportService->generateMultiSheetToFile($sheets, $tempPath);
    }
}
