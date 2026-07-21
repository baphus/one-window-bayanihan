<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly CaseFile $case,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown('emails.notifications.case-status-updated', [
                'case' => $this->case,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'url' => url("/cases/{$this->case->id}"),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'case_status_updated',
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => "Case status changed from {$this->oldStatus} to {$this->newStatus}",
            'url' => route('cases.show', $this->case->id),
        ];
    }
}
