<?php

namespace App\Listeners;

use App\Events\CaseDraftPublished;
use App\Models\CaseFile;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendCaseDraftPublishedNotification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly ?NotificationService $notifications = null) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(CaseDraftPublished $event): void
    {
        if (! $event->recipientEmail) {
            return;
        }

        $case = CaseFile::find($event->caseId);
        if (! $case) {
            return;
        }

        try {
            ($this->notifications ?? app(NotificationService::class))->notifyOfw(
                $case,
                $event->recipientEmail,
                'case_published',
                'Case Published',
                'Your case has been opened.',
                ['event_key' => $event->eventKey],
                route('cases.show', $case->id),
            );
        } catch (\Throwable $exception) {
            report($exception);
            throw $exception;
        }
    }

    public function failed(CaseDraftPublished $event, \Throwable $exception): void
    {
        report($exception);
    }
}
