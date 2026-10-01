<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $message,
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
            ->markdown('emails.notifications.system-alert', [
                'type' => $this->type,
                'severity' => $this->severity,
                'message' => $this->message,
            ]);
    }

    /**
     * Admin digest payload. Uses the same inbox keys as every other
     * notification; there is no case or actor for system alerts.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'system_alert',
            'title' => $this->title(),
            'message' => $this->message,
            'case_number' => null,
            'actor_name' => 'System',
            'url' => null,
            'alert_type' => $this->type,
            'severity' => $this->severity,
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

    private function title(): string
    {
        $label = ucwords(strtolower(str_replace('_', ' ', $this->type)));

        return "System alert: {$label} ({$this->severity})";
    }
}
