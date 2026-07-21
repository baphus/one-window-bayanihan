<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Referral $referral,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email && config('mail.mailer') !== 'log') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown('emails.notifications.referral-status-changed', [
                'referral' => $this->referral,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'url' => url("/referrals/{$this->referral->id}"),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'referral_status_changed',
            'referral_id' => $this->referral->id,
            'case_id' => $this->referral->case_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => "Referral status changed from {$this->humanizeStatus($this->oldStatus)} to {$this->humanizeStatus($this->newStatus)}",
            'url' => "/referrals/{$this->referral->id}",
        ];
    }

    private function humanizeStatus(string $status): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $status)));
    }
}
