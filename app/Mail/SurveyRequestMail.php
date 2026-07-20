<?php

namespace App\Mail;

use App\Models\SurveyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyRequestMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public string $survey_url;

    public string $caseNumber;

    public function __construct(
        public readonly SurveyInvitation $invitation,
        public readonly string $rawToken,
    ) {
        $this->survey_url = route('survey.public.show', $rawToken);
        $this->caseNumber = $this->invitation->caseFile?->case_number ?? '';
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
                'caseNumber' => $this->caseNumber,
            ],
        );
    }
}
