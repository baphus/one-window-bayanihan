<?php

namespace App\Mail;

use App\Models\ReferralClientRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Encrypted queued delivery primitive for a temporary client access token. */
class ClientRequestMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ReferralClientRequest $clientRequest,
        public readonly string $rawToken,
        /** Build this in the future controller using the opaque token. */
        public readonly string $magicLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action needed for your agency request');
    }

    public function content(): Content
    {
        $this->clientRequest->loadMissing('referral.agency');

        return new Content(
            markdown: 'emails.client-request',
            with: [
                'agencyName' => $this->clientRequest->referral?->agency?->name ?? 'the agency',
                'dueDate' => $this->clientRequest->due_at?->format('F j, Y'),
                'magicLink' => $this->magicLink,
            ],
        );
    }
}
