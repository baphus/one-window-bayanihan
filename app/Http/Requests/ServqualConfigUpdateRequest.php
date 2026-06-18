<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServqualConfigUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'AGENCY';
    }

    public function rules(): array
    {
        return [
            'service_name' => ['sometimes', 'required', 'string', 'max:255'],
            'questions' => ['sometimes', 'required', 'array', 'min:1'],
            'questions.*.dimension' => ['required_with:questions', 'string', 'in:Tangibles,Reliability,Responsiveness,Assurance,Empathy'],
            'questions.*.question' => ['required_with:questions', 'string', 'max:500'],
            'questions.*.order' => ['required_with:questions', 'integer', 'min:1'],
        ];
    }
}
