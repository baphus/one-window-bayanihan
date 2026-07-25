<?php

namespace App\Mail;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IntakePublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CaseFile $case,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Case Has Been Accepted ({$this->case->case_number})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.intake-published',
            with: [
                'case' => $this->case,
                'caseNumber' => $this->case->case_number,
                'trackerNumber' => $this->case->tracker_number,
            ],
        );
    }
}
