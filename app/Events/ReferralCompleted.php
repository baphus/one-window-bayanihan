<?php

namespace App\Events;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The completed referral.
     */
    public Referral $referral;

    /**
     * The case file associated with the referral.
     */
    public CaseFile $caseFile;

    /**
     * The agency that handled the referral.
     */
    public Agency $agency;

    /**
     * Create a new event instance.
     */
    public function __construct(Referral $referral)
    {
        $this->referral = $referral;
        $this->caseFile = $referral->caseFile;
        $this->agency = $referral->agency;
    }
}
