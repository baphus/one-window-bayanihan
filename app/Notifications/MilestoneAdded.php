<?php

namespace App\Notifications;

use App\Models\Milestone;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MilestoneAdded extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Milestone $milestone,
        public readonly Referral $referral,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown('emails.notifications.milestone-added', [
                'milestone' => $this->milestone,
                'referral' => $this->referral,
                'url' => url("/referrals/{$this->referral->id}"),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'milestone_added',
            'referral_id' => $this->referral->id,
            'case_id' => $this->referral->case_id,
            'milestone_id' => $this->milestone->id,
            'milestone_title' => $this->milestone->title,
            'message' => "New milestone '{$this->milestone->title}' added to referral",
            'url' => route('referrals.show', $this->referral),
        ];
    }
}
