<?php

namespace App\Http\Requests;

use App\Models\SurveyQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === 'AGENCY';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['required', 'string', Rule::in(SurveyQuestion::TYPES)],
            'questions.*.label' => ['required', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'array', 'required_if:questions.*.type,radio,checkbox'],
            'questions.*.options.*' => ['string', 'max:255'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.order' => ['integer', 'min:0'],
        ];
    }
}
