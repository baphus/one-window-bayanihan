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
        return new Content(
            markdown: 'mail.referral-overdue',
        );
    }
}
