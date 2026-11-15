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
            'agcy_ids' => ['required', 'array', 'min:1'],
            'agcy_ids.*' => ['required', 'uuid', 'distinct'],
            'category_id' => ['prohibited'],
            'agcy_id' => ['prohibited'],
            'concern' => ['required', 'string', 'max:5000'],
            'override_reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.config('file-uploads.max_size', 5120)],
        ];
    }
}
