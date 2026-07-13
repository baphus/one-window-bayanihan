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
            'invitation' => $invitation->only(['id', 'client_name', 'service_name', 'token']),
            'surveyForm' => $invitation->surveyForm,
            'questions' => $invitation->surveyForm->questions,
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

        $this->surveyInvitationService->submitResponse($invitation, $request->validated()['answers']);

        return redirect()
            ->back()
            ->with('success', 'Thank you for completing the survey!');
    }
}
