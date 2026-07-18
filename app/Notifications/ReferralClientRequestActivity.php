<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Request inbox notification. It deliberately carries identifiers and
 * presentation-safe status only; never request instructions, message bodies,
 * recipient snapshots, or access tokens.
 */
class ReferralClientRequestActivity extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $activity,
        public readonly string $requestId,
        public readonly string $referralId,
        public readonly string $title,
        public readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'referral_client_request_'.$this->activity,
            'request_id' => $this->requestId,
            'referral_id' => $this->referralId,
            'title' => $this->title,
            'status' => $this->status,
            'url' => '/referrals/'.$this->referralId.'/client-requests',
        ];
    }
}
