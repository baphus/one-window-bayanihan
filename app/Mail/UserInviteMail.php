<?php

namespace App\Mail;

use App\Models\UserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserInvite $invite,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to One Window Bayanihan',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-invite',
            with: [
                'inviteUrl' => route('register-via-invite', $this->token),
                'role' => $this->invite->role,
                'agencyName' => $this->invite->agency?->name,
                'expiresAt' => $this->invite->expires_at->format('F j, Y'),
            ],
        );
    }
}
