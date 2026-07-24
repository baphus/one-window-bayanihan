<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Models\User;
use App\Notifications\DownloadReady;
use App\Services\Reports\ReportsExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateSystemReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly array $criteria,
        public readonly string $userId,
        public readonly string $generatedDocumentId,
    ) {
        $this->onQueue('heavy');
    }

    public function handle(ReportsExportService $exportService): void
    {
        $data = $exportService->buildPdfPayloadFromCriteria($this->criteria);

        $pdf = Pdf::loadView('pdf.report', $data);
        $pdfContent = $pdf->output();

        $filename = 'bayanihan-report-'.now()->format('Ymd-His').'.pdf';
        $path = "generated/{$this->userId}/{$filename}";

        Storage::disk('supabase')->put($path, $pdfContent);

        $document = GeneratedDocument::findOrFail($this->generatedDocumentId);
        $document->update([
            'status' => 'completed',
            'path' => $path,
            'file_size' => strlen($pdfContent),
            'mime_type' => 'application/pdf',
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new DownloadReady($document->fresh()));
        }
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
