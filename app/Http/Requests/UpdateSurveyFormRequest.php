<?php

namespace App\Http\Requests;

use App\Models\SurveyQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === 'AGENCY';
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'questions' => ['sometimes', 'required', 'array', 'min:1'],
            'questions.*.type' => ['sometimes', 'required', 'string', Rule::in(SurveyQuestion::TYPES)],
            'questions.*.label' => ['sometimes', 'required', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'array', 'required_if:questions.*.type,radio,checkbox'],
            'questions.*.options.*' => ['string', 'max:255'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.order' => ['integer', 'min:0'],
        ];
    }
}
