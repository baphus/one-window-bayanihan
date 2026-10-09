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

    /**
     * Staff inbox payload with a readable title and actor alias.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $caseNumber = $this->case->case_number ?? $this->case->id;

        return [
            'type' => 'case_updated',
            'title' => "Case {$caseNumber} updated by {$this->updatedBy}",
            'message' => "Case {$caseNumber} updated by {$this->updatedBy}",
            'case_number' => $caseNumber,
            'actor_name' => $this->updatedBy,
            'url' => route('cases.show', $this->case->id),
            'case_id' => $this->case->id,
            'updated_by' => $this->updatedBy,
            'changes' => $this->changes,
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
}
