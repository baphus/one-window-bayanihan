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

    public function toDatabase(object $notifiable): array
    {
        $referral = $this->referral;
        $referral->loadMissing(['caseFile.client', 'agency']);

        $caseNumber = $referral->caseFile?->case_number ?? $referral->case_id;
        $agencyName = $referral->agency?->name ?? 'agency';
        $services = $referral->relationLoaded('services')
            ? $referral->services->pluck('name')->implode(', ')
            : $referral->required_services;

        return [
            'type' => 'referral_created',
            'title' => "New referral assigned to {$agencyName}",
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'case_number' => $caseNumber,
            'agency' => $agencyName,
            'required_services' => $services,
            'message' => "Case {$caseNumber} referred to {$agencyName}".($services ? " — {$services}" : ''),
            'url' => "/referrals/{$referral->id}",
        ];
    }
}
