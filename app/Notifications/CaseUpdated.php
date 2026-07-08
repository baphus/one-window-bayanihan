<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseUpdated extends Notification
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

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Case #{$this->case->case_number} Updated")
            ->greeting('Case Updated')
            ->line('**'.$this->updatedBy.'** made changes to **Case #'.$this->case->case_number.'**.')
            ->line('The following changes were made:');

        foreach ($this->changes as $field => $change) {
            $message->line('**'.$field.':** '.$change['old'].' &rarr; '.$change['new']);
        }

        return $message
            ->action('View Case', route('cases.show', $this->case->id))
            ->line('Please review the updated case details.');
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
