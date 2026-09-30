<?php

namespace App\Http\Requests;

use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferralStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isCaseManager() || $user->isAgency());
    }

    public function rules(): array
    {
        $commentRules = ['nullable', 'string', 'max:5000'];

        // An OTHER reason is meaningless without detail — require it here so
        // the rule only binds to this reason and never to ordinary comments.
        if ($this->input('rejection_reason') === 'OTHER') {
            $commentRules[] = 'required';
            $commentRules[] = 'min:10';
        }

        return [
            'status' => ['required', Rule::in(['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'])],
            'decision' => ['nullable', Rule::in(['ACCEPT', 'REJECT'])],
            'decision_comment' => $commentRules,
            'rejection_reason' => [
                'nullable',
                Rule::in(Referral::REJECTION_REASONS),
                Rule::requiredIf(fn () => $this->input('status') === 'REJECTED' || $this->input('decision') === 'REJECT'),
                Rule::prohibitedIf(fn () => $this->input('status') !== 'REJECTED' && $this->input('decision') !== 'REJECT'),
            ],
        ];
    }
}
