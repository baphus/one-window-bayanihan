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

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'system_alert',
            'alert_type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
        ];
    }
}
