<?php

namespace App\Mail;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IntakeReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CaseFile $case,
        public readonly bool $hasAccount = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your case is being evaluated ({$this->case->tracker_number})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.intake-received',
            with: [
                'case' => $this->case,
                'caseNumber' => $this->case->case_number,
                'trackerNumber' => $this->case->tracker_number,
                'hasAccount' => $this->hasAccount,
            ],
        );
    }
}
