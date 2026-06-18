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
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'agcy_id' => ['required', 'string', 'exists:agencies,id'],
            'required_services' => ['nullable', 'string', 'max:5000'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:255'],
            'compliance_requirements' => ['nullable', 'array'],
            'compliance_requirements.*.service_name' => ['required_with:compliance_requirements', 'string', 'max:255'],
            'compliance_requirements.*.requirement_name' => ['required_with:compliance_requirements', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }
}
