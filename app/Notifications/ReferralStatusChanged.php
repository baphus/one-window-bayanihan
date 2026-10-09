<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Referral $referral,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $actorName = null,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email && config('mail.default') !== 'log') {
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
            ->markdown('emails.notifications.referral-status-changed', [
                'referral' => $this->referral,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'url' => url("/referrals/{$this->referral->id}"),
            ]);
    }

    /**
     * Staff inbox payload with case reference, actor, and deep link.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $referral = $this->referral;
        // Never lazy-load on a non-persisted model: fall back to raw identifiers.
        if ($referral->relationLoaded('caseFile') || $referral->exists) {
            $referral->loadMissing('caseFile');
        }
        $caseNumber = $referral->relationLoaded('caseFile')
            ? ($referral->caseFile?->case_number ?? $referral->case_id)
            : $referral->case_id;
        $old = $this->humanizeStatus($this->oldStatus);
        $new = $this->humanizeStatus($this->newStatus);

        $message = "Case {$caseNumber}: referral status changed from {$old} to {$new}";
        if ($this->actorName !== null && $this->actorName !== '') {
            $message .= " by {$this->actorName}";
        }
        if ($this->reason !== null && $this->reason !== '') {
            $message .= " — {$this->reason}";
        }

        return [
            'type' => 'referral_status_changed',
            'title' => "Referral for case {$caseNumber} is now {$new}",
            'message' => $message,
            'case_number' => $caseNumber,
            'actor_name' => $this->actorName,
            'url' => "/referrals/{$referral->id}",
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
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

    private function humanizeStatus(string $status): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $status)));
    }
}
