<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicSurveySubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['present', 'array', 'list'],
            'answers.*' => ['array'],
            'answers.*.question_id' => ['required', 'uuid'],
            'answers.*.answer' => ['nullable', 'string', 'max:2000'],
            'answers.*.selected_options' => ['nullable', 'array', 'list'],
            'answers.*.selected_options.*' => ['string', 'max:255'],
        ];
    }
}
