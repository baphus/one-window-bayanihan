<?php

namespace App\Mail;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralOverdueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Referral $referral,
        public int $overdueDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Overdue Referral: {$this->referral->caseFile?->case_number}",
        );
    }

    public function content(): Content
    {
        $this->referral->loadMissing(['milestones', 'caseFile.client', 'agency']);

        return new Content(
            markdown: 'mail.referral-overdue',
            with: [
                'referral' => $this->referral,
                'overdueDays' => $this->overdueDays,
                'clientName' => ($this->referral->caseFile?->client?->first_name.' '.$this->referral->caseFile?->client?->last_name) ?? 'N/A',
                'agencyName' => $this->referral->agency?->name ?? 'N/A',
                'caseNumber' => $this->referral->caseFile?->case_number ?? 'N/A',
                'requiredServices' => $this->referral->required_services,
                'createdAt' => $this->referral->created_at,
                'milestones' => $this->referral->milestones,
                'lastUpdate' => $this->referral->updated_at,
            ],
        );
    }
}
