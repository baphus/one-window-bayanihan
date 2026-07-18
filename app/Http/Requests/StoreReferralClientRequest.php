<?php

namespace App\Http\Requests;

use App\Models\ReferralClientRequest as ClientRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'AGENCY' && $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:10000'],
            'type' => ['required', 'string', Rule::in([
                ClientRequest::TYPE_DOCUMENT_REQUEST,
                ClientRequest::TYPE_QUESTION,
                ClientRequest::TYPE_INFORMATION_UPDATE,
            ])],
            'due_at' => ['nullable', 'date', 'after:now'],
            'checklist' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->input('type') === ClientRequest::TYPE_DOCUMENT_REQUEST),
                'min:'.($this->input('type') === ClientRequest::TYPE_DOCUMENT_REQUEST ? 1 : 0),
            ],
            'checklist.*' => ['required', 'string', 'max:255'],
        ];
    }
}
