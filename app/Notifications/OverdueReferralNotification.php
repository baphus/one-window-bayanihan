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
        return [
            'type' => 'overdue_referral',
            'referral_id' => $this->referral->id,
            'case_number' => $this->referral->case?->case_number,
            'tracker_number' => $this->referral->case?->tracker_number,
            'overdue_days' => $this->overdueDays,
            'message' => 'A referral has been overdue for '.$this->overdueDays.' days and requires attention.',
        ];
    }
}
