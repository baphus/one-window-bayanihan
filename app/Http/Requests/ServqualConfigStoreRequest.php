<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServqualConfigStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'AGENCY';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', 'uuid', Rule::exists('services', 'id')->where('agcy_id', $this->user()?->agcy_id)->where('is_deleted', 0)],
            'service_name' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.dimension' => ['required', 'string', 'in:Tangibles,Reliability,Responsiveness,Assurance,Empathy'],
            'questions.*.question' => ['required', 'string', 'max:500'],
            'questions.*.order' => ['required', 'integer', 'min:1'],
        ];
    }
}
