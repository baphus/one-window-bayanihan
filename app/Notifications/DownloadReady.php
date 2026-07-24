<?php

namespace App\Notifications;

use App\Models\GeneratedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DownloadReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GeneratedDocument $document,
        public readonly bool $failed = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'default',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        if ($this->failed) {
            return [
                'type' => 'download_failed',
                'generated_document_id' => $this->document->id,
                'filename' => $this->document->filename,
                'document_type' => $this->document->type,
                'status' => 'failed',
                'error_message' => $this->document->error_message,
                'message' => "Failed to generate {$this->humanizeType($this->document->type)}: {$this->document->filename}",
            ];
        }

        return [
            'type' => 'download_ready',
            'generated_document_id' => $this->document->id,
            'filename' => $this->document->filename,
            'document_type' => $this->document->type,
            'status' => 'ready',
            'url' => "/documents/{$this->document->id}/download",
            'message' => "Your {$this->humanizeType($this->document->type)} is ready: {$this->document->filename}",
        ];
    }

    private function humanizeType(string $type): string
    {
        return match ($type) {
            'case_report_pdf' => 'case report',
            'system_report_pdf' => 'system report',
            'cases_export' => 'cases export',
            'clients_export' => 'clients export',
            'referrals_export' => 'referrals export',
            'reports_export' => 'reports export',
            'admin_full_export' => 'full export',
            default => 'file',
        };
    }
}
