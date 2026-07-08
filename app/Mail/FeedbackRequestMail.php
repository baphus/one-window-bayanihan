<?php

namespace App\Mail;

use App\Models\FeedbackInvitation;
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
        public readonly FeedbackInvitation $invitation,
        public readonly string $token,
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
