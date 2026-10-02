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
        public readonly ?string $actorName = null,
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
            ->markdown('emails.notifications.referral-created', [
                'referral' => $this->referral,
                'url' => url("/referrals/{$this->referral->id}"),
            ]);
    }

    /**
     * Staff inbox payload with actor and deep link.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $referral = $this->referral;
        // Never lazy-load on a non-persisted model: fall back to raw identifiers.
        if ($referral->relationLoaded('caseFile') || $referral->exists) {
            $referral->loadMissing(['caseFile.client', 'agency']);
        }

        $caseNumber = $referral->relationLoaded('caseFile')
            ? ($referral->caseFile?->case_number ?? $referral->case_id)
            : $referral->case_id;
        $agencyName = $referral->relationLoaded('agency')
            ? ($referral->agency?->name ?? 'agency')
            : 'agency';
        $message = "Case {$caseNumber} referred to {$agencyName}";
        if ($this->actorName !== null && $this->actorName !== '') {
            $message .= " by {$this->actorName}";
        }

        return [
            'type' => 'referral_created',
            'title' => "New referral assigned to {$agencyName}",
            'message' => $message,
            'case_number' => $caseNumber,
            'actor_name' => $this->actorName,
            'url' => "/referrals/{$referral->id}",
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'agency' => $agencyName,
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
