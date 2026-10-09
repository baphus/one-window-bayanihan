<?php

namespace App\Notifications;

use App\Models\Milestone;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MilestoneAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Milestone $milestone,
        public readonly Referral $referral,
        public readonly ?string $actorName = null,
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
            ->markdown('emails.notifications.milestone-added', [
                'milestone' => $this->milestone,
                'referral' => $this->referral,
                'url' => url("/referrals/{$this->referral->id}"),
            ]);
    }

    /**
     * Staff inbox payload. Every key is always present so old and new
     * rows render without blank titles or missing links.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $referral = $this->referral;
        // Never lazy-load on a non-persisted model (e.g. unit tests with
        // force-filled attributes): fall back to the raw identifiers.
        if ($referral->relationLoaded('caseFile') || $referral->exists) {
            $referral->loadMissing('caseFile');
        }
        if ($referral->relationLoaded('agency') || $referral->exists) {
            $referral->loadMissing('agency');
        }
        $caseNumber = $referral->relationLoaded('caseFile')
            ? ($referral->caseFile?->case_number ?? $referral->case_id)
            : $referral->case_id;
        $agencyName = $referral->relationLoaded('agency')
            ? ($referral->agency?->name ?? 'the assigned agency')
            : 'the assigned agency';
        $actor = $this->actorName
            ?? ($this->milestone->relationLoaded('user') ? $this->milestone->user?->name : null);
        $milestoneTitle = $this->milestone->title ?? 'Untitled milestone';

        $message = "New milestone '{$milestoneTitle}' added to case {$caseNumber} ({$agencyName})";
        if ($actor !== null && $actor !== '') {
            $message .= " by {$actor}";
        }

        return [
            'type' => 'milestone_added',
            'title' => "New milestone added to case {$caseNumber}",
            'message' => $message,
            'case_number' => $caseNumber,
            'actor_name' => $actor,
            'url' => route('referrals.show', $referral),
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'milestone_id' => $this->milestone->id,
            'milestone_title' => $milestoneTitle,
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
}
