<?php

namespace App\Http\Requests;

use App\Models\SurveyForm;
use App\Models\SurveyQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $form = $this->route('form');

        return $user !== null
            && $user->role === 'AGENCY'
            && $user->agcy_id !== null
            && $form instanceof SurveyForm
            && $form->agency_id === $user->agcy_id;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'questions' => ['sometimes', 'required', 'array', 'list', 'min:1'],
            'questions.*' => ['required', 'array'],
            'questions.*.type' => ['required', 'string', Rule::in(SurveyQuestion::TYPES)],
            'questions.*.label' => ['required', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'array', 'list', 'required_if:questions.*.type,radio,checkbox'],
            'questions.*.options.*' => ['string', 'max:255', 'distinct:strict'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.order' => ['integer', 'min:0'],
        ];
    }
}
