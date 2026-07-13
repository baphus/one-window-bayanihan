<?php

namespace App\Mail;

use App\Models\SurveyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $survey_url;

    public function __construct(
        public readonly SurveyInvitation $invitation,
    ) {
        $this->survey_url = route('survey.public.show', $invitation->token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We'd like your feedback on our service",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.survey-request',
            with: [
                'invitation' => $this->invitation,
                'survey_url' => $this->survey_url,
            ],
        );
    }
}
