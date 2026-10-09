<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Request inbox notification. It deliberately carries identifiers and
 * presentation-safe status only; never request instructions, message bodies,
 * recipient snapshots, or access tokens.
 */
class ReferralClientRequestActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $activity,
        public readonly string $requestId,
        public readonly string $referralId,
        public readonly string $title,
        public readonly string $status,
        public readonly ?string $caseNumber = null,
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
     * Staff inbox payload with a human verb, readable case reference,
     * and actor — never a bare request title.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $requestTitle = $this->title !== '' ? $this->title : 'Untitled request';
        $verb = $this->describeActivity($this->activity);
        $caseRef = $this->caseNumber !== null && $this->caseNumber !== ''
            ? " for case {$this->caseNumber}"
            : '';
        $actor = $this->actorName !== null && $this->actorName !== ''
            ? " by {$this->actorName}"
            : '';

        return [
            'type' => 'referral_client_request_'.$this->activity,
            'title' => "{$verb}: {$requestTitle}",
            'message' => "{$verb}{$caseRef}: '{$requestTitle}'{$actor} (status: {$this->humanizeStatus($this->status)})",
            'case_number' => $this->caseNumber,
            'actor_name' => $this->actorName,
            'url' => '/referrals/'.$this->referralId.'/client-requests',
            'request_id' => $this->requestId,
            'referral_id' => $this->referralId,
            'request_title' => $requestTitle,
            'status' => $this->status,
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

    /**
     * Map the machine activity key to a plain-language inbox verb.
     */
    private function describeActivity(string $activity): string
    {
        return match ($activity) {
            'created' => 'New client request created',
            'client_reply' => 'Client responded',
            'replacement_requested' => 'Client asked for a replacement',
            'agency_message' => 'New message sent to client',
            'completed' => 'Client request completed',
            'cancelled' => 'Client request cancelled',
            'reopened' => 'Client request reopened',
            'access_revoked' => 'Client access revoked',
            default => str_starts_with($activity, 'delivery_')
                ? 'Client request delivery update'
                : 'Client request updated',
        };
    }

    private function humanizeStatus(string $status): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $status)));
    }
}
