<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly string $purpose,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) {
            'login' => 'Your Login Verification Code',
            'track' => 'Your Case Tracking Verification Code',
            'email_change' => 'Your Email Change Verification Code',
            default => 'Your Verification Code',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.otp',
        );
    }

    /**
     * The log mailer writes the whole rendered MIME body to the app log,
     * which the admin LogViewer serves. Substitute a masked code so OTP
     * digits never reach log files; real transports (smtp, resend) and the
     * array mailer used in tests still receive the live code.
     */
    public function buildViewData(): array
    {
        $data = parent::buildViewData();

        $mailer = (string) ($this->mailer ?: config('mail.default'));
        if ((string) config("mail.mailers.{$mailer}.transport") === 'log') {
            $data['otp'] = str_repeat('*', strlen($this->otp));
        }

        return $data;
    }
}
