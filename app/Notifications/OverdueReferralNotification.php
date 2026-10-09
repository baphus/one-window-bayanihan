<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OverdueReferralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Referral $referral,
        public readonly int $overdueDays,
        public readonly ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'default',
        ];
    }

    /**
     * Staff inbox payload with an actionable title and deep link.
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
        if ($referral->relationLoaded('agency') || $referral->exists) {
            $referral->loadMissing('agency');
        }
        $caseFile = $referral->relationLoaded('caseFile') ? $referral->caseFile : null;
        $caseNumber = $caseFile?->case_number ?? $referral->case_id;
        $trackerNumber = $caseFile?->tracker_number;
        $agencyName = $referral->relationLoaded('agency')
            ? ($referral->agency?->name ?? 'the assigned agency')
            : 'the assigned agency';

        return [
            'type' => 'overdue_referral',
            'title' => "Overdue referral needs attention — case {$caseNumber}",
            'message' => "Case {$caseNumber} ({$agencyName}) has been inactive for {$this->overdueDays} days and requires attention.",
            'case_number' => $caseNumber,
            'actor_name' => $this->actorName,
            'url' => "/referrals/{$referral->id}",
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'tracker_number' => $trackerNumber,
            'agency' => $agencyName,
            'overdue_days' => $this->overdueDays,
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
