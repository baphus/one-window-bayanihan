<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CaseFile $case,
        public readonly string $updatedBy,
        public readonly array $changes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'default',
            'mail' => 'notifications',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown('emails.notifications.case-updated', [
                'case' => $this->case,
                'updatedBy' => $this->updatedBy,
                'changes' => $this->changes,
                'url' => url("/cases/{$this->case->id}"),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'case_updated',
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'updated_by' => $this->updatedBy,
            'changes' => $this->changes,
            'message' => "Case updated by {$this->updatedBy}",
            'url' => route('cases.show', $this->case->id),
        ];
    }
}
