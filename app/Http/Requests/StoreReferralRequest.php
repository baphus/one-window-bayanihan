<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasRole('ADMIN')
            || $user->hasRole('CASE_MANAGER')
            || $user->role === 'ADMIN'
            || $user->role === 'CASE_MANAGER'
        );
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'agcy_id' => ['required', 'string', 'exists:agencies,id'],
            'required_services' => ['nullable', 'string', 'max:5000'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array'],
        ];
    }
}
