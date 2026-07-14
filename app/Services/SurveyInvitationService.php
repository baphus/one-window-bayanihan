<?php

namespace App\Services;

use App\DTOs\CreatedSurveyInvitation;
use App\Models\Referral;
use App\Models\SurveyForm;
use App\Models\SurveyInvitation;
use App\Models\SurveyQuestion;
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
        string $referralId,
        string $clientName,
        string $clientEmail,
        string $serviceName,
        ?string $surveyFormId = null,
    ): ?CreatedSurveyInvitation {
        $rawToken = Str::random(64);
        $invitation = DB::transaction(function () use ($referralId, $clientName, $clientEmail, $serviceName, $surveyFormId, $rawToken): ?SurveyInvitation {
            $referral = Referral::query()->lockForUpdate()->findOrFail($referralId);
            if (SurveyInvitation::where('referral_id', $referral->id)->exists()) {
                return null;
            }

            $form = SurveyForm::query()
                ->lockForUpdate()
                ->when($surveyFormId, fn ($query) => $query->whereKey($surveyFormId))
                ->where('agency_id', $referral->agcy_id)
                ->where('is_active', true)
                ->first();

            if (! $form) {
                throw new RuntimeException('No active survey form is available for this agency.');
            }

            return SurveyInvitation::create([
                'survey_form_id' => $form->id,
                'case_id' => $referral->case_id,
                'agency_id' => $referral->agcy_id,
                'referral_id' => $referral->id,
                'client_name' => $clientName,
                'client_email' => $clientEmail,
                'service_name' => $serviceName,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addDays(30),
            ]);
        });

        return $invitation ? new CreatedSurveyInvitation($invitation, $rawToken) : null;
    }

    /**
     * Find by token, check not expired/submitted, throw RuntimeException if invalid.
     * Load surveyForm.questions relation.
     */
    public function validateToken(string $token): SurveyInvitation
    {
        $hash = hash('sha256', $token);
        $invitation = SurveyInvitation::where(function ($query) use ($hash, $token) {
            $query->where('token_hash', $hash)->orWhere('token', $token);
        })
            ->with('surveyForm.questions')
            ->first();

        if (! $invitation || ! $invitation->surveyForm) {
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
            if (! array_is_list($answers)) {
                throw new RuntimeException('Invalid survey answers.');
            }

            $invitation = SurveyInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $form = SurveyForm::query()
                ->lockForUpdate()
                ->with('questions')
                ->find($invitation->survey_form_id);
            if (! $invitation->isUsable() || ! $form || $form->agency_id !== $invitation->agency_id) {
                throw new RuntimeException($invitation->isSubmitted() ? 'This survey has already been submitted.' : 'This survey link is no longer usable.');
            }

            $questions = $form->questions->keyBy('id');
            $seen = [];
            foreach ($answers as $answer) {
                if (! is_array($answer) || ! array_key_exists('question_id', $answer)) {
                    throw new RuntimeException('Invalid survey answers.');
                }
                $questionId = $answer['question_id'] ?? null;
                $question = $questions->get($questionId);
                if (! $question || isset($seen[$questionId])) {
                    throw new RuntimeException('Invalid survey answers.');
                }
                $seen[$questionId] = true;
                $this->validateAnswer($question, $answer);
                SurveyResponse::create([
                    'survey_invitation_id' => $invitation->id,
                    'survey_question_id' => $questionId,
                    'answer' => $answer['answer'] ?? null,
                    'selected_options' => $answer['selected_options'] ?? null,
                ]);
            }

            foreach ($questions as $question) {
                if ($question->is_required && ! isset($seen[$question->id])) {
                    throw new RuntimeException('Please answer all required questions.');
                }
            }

            $invitation->update(['submitted_at' => now()]);
        });
    }

    private function validateAnswer(SurveyQuestion $question, array $answer): void
    {
        $hasAnswer = array_key_exists('answer', $answer) && trim((string) $answer['answer']) !== '';
        $options = $answer['selected_options'] ?? null;
        if ($options !== null && (! is_array($options) || ! array_is_list($options))) {
            throw new RuntimeException('Invalid survey answers.');
        }
        $hasOptions = is_array($options) && count($options) > 0;
        if ($hasAnswer && $hasOptions) {
            throw new RuntimeException('Invalid survey answers.');
        }
        if ($question->is_required && ! $hasAnswer && ! $hasOptions) {
            throw new RuntimeException('Please answer all required questions.');
        }
        if (! $hasAnswer && ! $hasOptions) {
            return;
        }

        if ($question->type === 'text' && ! $hasAnswer) {
            throw new RuntimeException('Invalid survey answers.');
        }
        if (in_array($question->type, ['rating', 'likert'], true)) {
            $value = filter_var($answer['answer'] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 5) {
                throw new RuntimeException('Invalid survey answers.');
            }
        } elseif ($question->type === 'radio') {
            if (! $hasAnswer || ! in_array($answer['answer'], $question->options ?? [], true)) {
                throw new RuntimeException('Invalid survey answers.');
            }
        } elseif ($question->type === 'checkbox') {
            if (! $hasOptions || count($options) !== count(array_unique($options)) || array_diff($options, $question->options ?? [])) {
                throw new RuntimeException('Invalid survey answers.');
            }
        } elseif ($question->type !== 'text') {
            throw new RuntimeException('Invalid survey answers.');
        }
    }
}
