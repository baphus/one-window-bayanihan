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

    public function toDatabase(object $notifiable): array
    {
        $newReferral = $this->newReferral;
        $peerReferral = $this->peerReferral;

        $newReferral->loadMissing(['agency', 'services']);
        $peerReferral->loadMissing(['agency']);

        $caseNumber = $newReferral->caseFile?->case_number ?? $newReferral->case_id;
        $newAgencyName = $newReferral->agency?->name ?? 'another agency';
        $services = $newReferral->relationLoaded('services')
            ? $newReferral->services->pluck('name')->implode(', ')
            : $newReferral->required_services;

        return [
            'type' => 'peer_referral_created',
            'title' => "New referral added to case {$caseNumber}",
            'referral_id' => $peerReferral->id,
            'case_id' => $newReferral->case_id,
            'case_number' => $caseNumber,
            'new_agency' => $newAgencyName,
            'required_services' => $services,
            'message' => "Case {$caseNumber} was referred to {$newAgencyName}"
                .($services ? " — {$services}" : ''),
            'url' => "/referrals/{$peerReferral->id}",
        ];
    }
}
