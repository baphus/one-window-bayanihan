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
        $mail = (new MailMessage)
            ->subject("Case #{$this->case->case_number} Updated")
            ->line("{$this->updatedBy} updated the case:");

        foreach ($this->changes as $field => $change) {
            $mail->line("- {$field}: {$change['old']} -> {$change['new']}");
        }

        return $mail->action('View Case', route('cases.show', $this->case->id));
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
