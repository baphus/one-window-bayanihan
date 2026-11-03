<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MfaStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $change,
        public readonly bool $required,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function viaQueues(): array
    {
        return ['database' => 'default', 'mail' => 'notifications'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('MFA policy update')
            ->line($this->message());
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'mfa_policy_changed',
            'title' => 'Two-factor authentication update',
            'message' => $this->message(),
            'actor_name' => 'System',
            'url' => route('profile.edit'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function message(): string
    {
        return match ($this->change) {
            'reset' => 'Your MFA enrollment was reset. Enroll again before accessing protected areas.',
            'disabled' => 'Two-factor authentication was disabled for your account.',
            default => $this->required
                ? 'MFA is now required for your account. Enroll before accessing protected areas.'
                : 'MFA is now optional for your account. Existing enrollment remains active.',
        };
    }
}
