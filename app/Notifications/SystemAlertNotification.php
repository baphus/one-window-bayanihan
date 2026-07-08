<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $alertType;

    public string $severity;

    public string $message;

    public function __construct(string $alertType, string $severity, string $message)
    {
        $this->alertType = $alertType;
        $this->severity = $severity;
        $this->message = $message;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $config = DB::table('alert_configs')
            ->where('alert_type', $this->alertType)
            ->first();

        if ($config && ! empty(json_decode($config->email_recipients ?? '[]', true))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("System Alert: {$this->severity} - {$this->alertType}")
            ->greeting('System Alert')
            ->line('A system alert requires your attention.')
            ->line('**Type:** '.$this->alertType)
            ->line('**Severity:** '.$this->severity)
            ->line('**Message:** '.$this->message)
            ->action('View Dashboard', url('/admin/system/health'))
            ->line('This is an automated system alert.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'alert_type' => $this->alertType,
            'severity' => $this->severity,
            'message' => $this->message,
        ];
    }
}
