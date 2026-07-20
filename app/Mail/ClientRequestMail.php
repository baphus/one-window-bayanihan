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
    ) {
        $this->clientRequest->loadMissing('referral.agency', 'referral.caseFile.client');
    }

    public function getRequestTypeLabel(): string
    {
        return match ($this->clientRequest->type) {
            'document_request' => 'Document Request',
            'question' => 'Question',
            'information_update' => 'Information Update',
            default => 'Request',
        };
    }

    public function getChecklistItems(): array
    {
        return $this->clientRequest->items?->pluck('label')->toArray() ?? [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action needed for your agency request');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.client-request',
            with: [
                'clientRequest' => $this->clientRequest,
                'rawToken' => $this->rawToken,
                'magicLink' => $this->magicLink,
                'requestTypeLabel' => $this->getRequestTypeLabel(),
                'checklistItems' => $this->getChecklistItems(),
                'agencyName' => $this->clientRequest->referral?->agency?->name ?? 'the agency',
                'clientName' => $this->clientRequest->referral?->caseFile?->client?->first_name ?? '',
                'caseNumber' => $this->clientRequest->referral?->caseFile?->case_number ?? '',
                'dueDate' => $this->clientRequest->due_at?->format('F j, Y'),
            ],
        );
    }
}
