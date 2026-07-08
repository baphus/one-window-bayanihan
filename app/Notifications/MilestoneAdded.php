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
            ->subject('New Milestone Added')
            ->greeting('New Milestone Added')
            ->line('A new milestone has been added to a referral under your care.')
            ->line('**Milestone:** '.$this->milestone->title)
            ->line('**Case Number:** '.($this->referral->caseFile?->case_number ?? 'N/A'))
            ->action('View Referral', route('referrals.show', $this->referral));
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
