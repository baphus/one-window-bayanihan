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
            ->subject("Referral Status Updated: {$this->newStatus}")
            ->greeting('Referral Status Updated')
            ->line('The status of a referral has been updated.')
            ->line('**Referral:** '.$this->referral->required_services)
            ->line('**Status Change:** '.$this->oldStatus.' &rarr; **'.$this->newStatus.'**')
            ->action('View Referral', url("/referrals/{$this->referral->id}"))
            ->line('Please review the updated referral details.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'referral_status_changed',
            'referral_id' => $this->referral->id,
            'case_id' => $this->referral->case_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => "Referral status changed from {$this->oldStatus} to {$this->newStatus}",
            'url' => "/referrals/{$this->referral->id}",
        ];
    }
}
