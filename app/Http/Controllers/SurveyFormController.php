<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyFormRequest;
use App\Http\Requests\UpdateSurveyFormRequest;
use App\Models\SurveyForm;
use App\Models\SurveyQuestion;
use App\Services\SurveyFormService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SurveyFormController extends Controller
{
    public function __construct(
        private readonly SurveyFormService $surveyFormService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $forms = $this->surveyFormService->getFormsForAgency($user->agcy_id);

        return Inertia::render('Survey/FormIndex', [
            'forms' => $forms,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Survey/FormBuilder', [
            'form' => null,
            'questionTypes' => SurveyQuestion::TYPES,
            'likertLabels' => SurveyQuestion::LIKERT_LABELS,
        ]);
    }

    public function store(StoreSurveyFormRequest $request)
    {
        $user = $request->user();

        $this->surveyFormService->createForm($user->agcy_id, $request->validated());

        return redirect()
            ->route('survey.forms.index')
            ->with('success', 'Survey form created successfully.');
    }

    public function edit(Request $request, SurveyForm $form)
    {
        $user = $request->user();
        $this->authorizeAgencyOwnership($form, $user);

        $form->load('questions');

        return Inertia::render('Survey/FormBuilder', [
            'form' => $form,
            'questionTypes' => SurveyQuestion::TYPES,
            'likertLabels' => SurveyQuestion::LIKERT_LABELS,
        ]);
    }

    public function update(UpdateSurveyFormRequest $request, SurveyForm $form)
    {
        $user = $request->user();
        $this->authorizeAgencyOwnership($form, $user);

        $this->surveyFormService->updateForm($form, $request->validated());

        return redirect()
            ->route('survey.forms.index')
            ->with('success', 'Survey form updated successfully.');
    }

    public function destroy(Request $request, SurveyForm $form)
    {
        $user = $request->user();
        $this->authorizeAgencyOwnership($form, $user);

        $this->surveyFormService->deleteForm($form);

        return redirect()
            ->route('survey.forms.index')
            ->with('success', 'Survey form deleted successfully.');
    }

    public function activate(Request $request, SurveyForm $form)
    {
        $user = $request->user();
        $this->authorizeAgencyOwnership($form, $user);

        $this->surveyFormService->activateForm($form);

        return redirect()
            ->route('survey.forms.index')
            ->with('success', 'Survey form activated successfully.');
    }

    private function authorizeAgencyOwnership(SurveyForm $form, $user): void
    {
        if ($form->agency_id !== $user->agcy_id) {
            abort(403, 'You do not have access to this survey form.');
        }
    }
}
