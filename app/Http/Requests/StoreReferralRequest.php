<?php

namespace App\Http\Requests;

use App\Models\Referral;
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
            'documents.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Prevent duplicate referrals: same case cannot be referred to the same agency twice.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $caseId = $this->input('case_id');
            $agcyId = $this->input('agcy_id');

            if (! $caseId || ! $agcyId) {
                return;
            }

            $exists = Referral::where('case_id', $caseId)
                ->where('agcy_id', $agcyId)
                ->where('is_deleted', false)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'agcy_id',
                    'This case has already been referred to the selected agency.',
                );
            }
        });
    }
}
