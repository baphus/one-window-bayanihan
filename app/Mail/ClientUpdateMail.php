<?php

namespace App\Mail;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CaseFile $case,
        public readonly string $message,
        public readonly string $updatedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update on Your Case ({$this->case->case_number})",
        );
    }

    public function content(): Content
    {
        $this->case->loadMissing('client', 'caseEvents');

        return new Content(
            markdown: 'emails.client-update',
            with: [
                'case' => $this->case,
                'message' => $this->message,
                'updatedBy' => $this->updatedBy,
            ],
        );
    }
}
