<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicSurveySubmitRequest;
use App\Services\SurveyInvitationService;
use Inertia\Inertia;
use RuntimeException;

class PublicSurveyController extends Controller
{
    public function __construct(
        private readonly SurveyInvitationService $surveyInvitationService,
    ) {}

    public function show(string $token)
    {
        try {
            $invitation = $this->surveyInvitationService->validateToken($token);
        } catch (RuntimeException $e) {
            return Inertia::render('Survey/PublicFormError', [
                'error' => $e->getMessage(),
            ]);
        }

        return Inertia::render('Survey/PublicForm', [
            'invitation' => $invitation->only(['id', 'client_name', 'service_name']),
            'surveyForm' => $invitation->surveyForm->only(['id', 'title', 'description']),
            'questions' => $invitation->surveyForm->questions->map(fn ($question) => $question->only([
                'id', 'type', 'label', 'options', 'is_required', 'order',
            ]))->values(),
        ]);
    }

    public function submit(PublicSurveySubmitRequest $request, string $token)
    {
        try {
            $invitation = $this->surveyInvitationService->validateToken($token);
        } catch (RuntimeException $e) {
            return Inertia::render('Survey/PublicFormError', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->surveyInvitationService->submitResponse($invitation, $request->validated()['answers']);
        } catch (RuntimeException $e) {
            return Inertia::render('Survey/PublicFormError', [
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Thank you for completing the survey!');
    }
}
