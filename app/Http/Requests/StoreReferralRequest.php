<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isCaseManager());
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'uuid', 'exists:cases,id'],
            'agcy_id' => ['required', 'uuid', 'exists:agencies,id'],
            'category_id' => ['required', 'uuid', 'exists:case_categories,id'],
            'concern' => ['required', 'string', 'max:5000'],
            'override_reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
