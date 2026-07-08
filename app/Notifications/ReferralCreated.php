<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralCreated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Referral $referral,
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
            ->subject('New Referral Assigned')
            ->greeting('New Referral Assigned')
            ->line('A new referral has been assigned to your agency for processing.')
            ->line('**Required Services:** '.$this->referral->required_services)
            ->line('**Status:** '.$this->referral->status)
            ->action('View Referral', url("/referrals/{$this->referral->id}"))
            ->line('Please review and process this referral at your earliest convenience.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'referral_created',
            'referral_id' => $this->referral->id,
            'case_id' => $this->referral->case_id,
            'message' => "New referral assigned: {$this->referral->required_services}",
            'url' => "/referrals/{$this->referral->id}",
        ];
    }
}
