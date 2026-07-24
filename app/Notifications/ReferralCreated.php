<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralCreated extends Notification implements ShouldQueue
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
            ->markdown('emails.notifications.referral-created', [
                'referral' => $this->referral,
                'url' => url("/referrals/{$this->referral->id}"),
            ]);
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
