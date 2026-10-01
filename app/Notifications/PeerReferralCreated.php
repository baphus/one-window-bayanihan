<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies agencies already involved in a case when a new referral
 * is added to the same case by another agency or a case manager.
 */
class PeerReferralCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Referral $newReferral,
        public readonly Referral $peerReferral,
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
     * Staff inbox payload with actor and deep link.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $newReferral = $this->newReferral;
        $peerReferral = $this->peerReferral;

        // Never lazy-load on a non-persisted model: fall back to raw identifiers.
        if ($newReferral->relationLoaded('agency') || $newReferral->exists) {
            $newReferral->loadMissing(['agency', 'services']);
        }
        if ($peerReferral->relationLoaded('agency') || $peerReferral->exists) {
            $peerReferral->loadMissing(['agency']);
        }
        if ($newReferral->relationLoaded('caseFile') || $newReferral->exists) {
            $newReferral->loadMissing('caseFile');
        }

        $caseNumber = $newReferral->relationLoaded('caseFile')
            ? ($newReferral->caseFile?->case_number ?? $newReferral->case_id)
            : $newReferral->case_id;
        $newAgencyName = $newReferral->relationLoaded('agency')
            ? ($newReferral->agency?->name ?? 'another agency')
            : 'another agency';
        $services = $newReferral->relationLoaded('services')
            ? $newReferral->services->pluck('name')->implode(', ')
            : $newReferral->required_services;
        $servicesDisplay = $services !== '' ? $services : 'Services pending agency assignment';

        $message = "Case {$caseNumber} was referred to {$newAgencyName} — {$servicesDisplay}";
        if ($this->actorName !== null && $this->actorName !== '') {
            $message .= " by {$this->actorName}";
        }

        return [
            'type' => 'peer_referral_created',
            'title' => "New referral added to case {$caseNumber}",
            'message' => $message,
            'case_number' => $caseNumber,
            'actor_name' => $this->actorName,
            'url' => "/referrals/{$peerReferral->id}",
            'referral_id' => $peerReferral->id,
            'case_id' => $newReferral->case_id,
            'new_agency' => $newAgencyName,
            'required_services' => $servicesDisplay,
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
