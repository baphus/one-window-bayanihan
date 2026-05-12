<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferralStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'])],
            'decision' => ['nullable', Rule::in(['ACCEPT', 'REJECT'])],
            'decision_comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
