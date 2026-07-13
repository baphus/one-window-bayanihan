<?php

namespace App\Services;

use App\Models\SurveyInvitation;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SurveyInvitationService
{
    /**
     * Create invitation with generated token, expires in 30 days.
     */
    public function createInvitation(
        string $caseId,
        string $agencyId,
        string $referralId,
        string $clientName,
        string $clientEmail,
        string $serviceName,
        ?string $surveyFormId = null,
    ): SurveyInvitation {
        // If no specific form provided, use the agency's active form
        if ($surveyFormId === null) {
            $activeForm = app(SurveyFormService::class)->getActiveFormForAgency($agencyId);
            $surveyFormId = $activeForm?->id;
        }

        return SurveyInvitation::create([
            'survey_form_id' => $surveyFormId,
            'case_id' => $caseId,
            'agency_id' => $agencyId,
            'referral_id' => $referralId,
            'client_name' => $clientName,
            'client_email' => $clientEmail,
            'service_name' => $serviceName,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Find by token, check not expired/submitted, throw RuntimeException if invalid.
     * Load surveyForm.questions relation.
     */
    public function validateToken(string $token): SurveyInvitation
    {
        $invitation = SurveyInvitation::where('token', $token)
            ->with('surveyForm.questions')
            ->first();

        if (! $invitation) {
            throw new RuntimeException('Invalid survey link.');
        }

        if ($invitation->isExpired()) {
            throw new RuntimeException('This survey link has expired.');
        }

        if ($invitation->isSubmitted()) {
            throw new RuntimeException('This survey has already been submitted.');
        }

        return $invitation;
    }

    /**
     * Save responses and mark invitation as submitted.
     * $answers is array of {question_id, answer?, selected_options?}.
     */
    public function submitResponse(SurveyInvitation $invitation, array $answers): void
    {
        DB::transaction(function () use ($invitation, $answers) {
            foreach ($answers as $answer) {
                SurveyResponse::create([
                    'survey_invitation_id' => $invitation->id,
                    'survey_question_id' => $answer['question_id'],
                    'answer' => $answer['answer'] ?? null,
                    'selected_options' => $answer['selected_options'] ?? null,
                ]);
            }

            $invitation->update(['submitted_at' => now()]);
        });
    }
}
