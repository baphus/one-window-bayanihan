<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InterventionCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Referral $referral,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $caseNumber = $this->referral->caseFile?->case_number ?? 'N/A';

        return [
            'title' => "DMW Intervention Created — Case #{$caseNumber}",
            'message' => "A DMW intervention referral has been automatically created for case #{$caseNumber}.",
            'referral_id' => $this->referral->id,
            'case_id' => $this->referral->case_id,
            'action_url' => "/referrals/{$this->referral->id}",
            'type' => 'intervention',
        ];
    }
}
