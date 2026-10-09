<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CaseFile $case,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $actorName = null,
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
            ->markdown('emails.notifications.case-status-updated', [
                'case' => $this->case,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'url' => url("/cases/{$this->case->id}"),
            ]);
    }

    /**
     * Staff inbox payload with a readable title, actor, and deep link.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $caseNumber = $this->case->case_number ?? $this->case->id;
        $old = $this->humanizeStatus($this->oldStatus);
        $new = $this->humanizeStatus($this->newStatus);

        $message = "Case {$caseNumber} status changed from {$old} to {$new}";
        if ($this->actorName !== null && $this->actorName !== '') {
            $message .= " by {$this->actorName}";
        }

        return [
            'type' => 'case_status_updated',
            'title' => "Case {$caseNumber} is now {$new}",
            'message' => $message,
            'case_number' => $caseNumber,
            'actor_name' => $this->actorName,
            'url' => route('cases.show', $this->case->id),
            'case_id' => $this->case->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }

    /**
     * Keep array/broadcast serialization identical to the database payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function humanizeStatus(string $status): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $status)));
    }
}
