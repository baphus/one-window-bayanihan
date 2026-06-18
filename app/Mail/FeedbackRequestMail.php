<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly CaseFile $caseFile,
        public readonly Agency $agency,
        public readonly string $trackingToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We Value Your Feedback — One Window Bayanihan',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.feedback-request',
        );
    }
}
