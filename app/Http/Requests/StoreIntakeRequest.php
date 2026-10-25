<?php

namespace App\Http\Requests;

use App\Rules\VulnerabilityRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIntakeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isOfw() || $user->isAdmin() || $user->isCaseManager());
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize vulnerability fields — accept comma-separated multi-select, validate each segment
        foreach (['vulnerability_indicator'] as $field) {
            if ($this->has($field)) {
                $raw = $this->input($field);
                if (is_string($raw)) {
                    $this->merge([$field => trim($raw) === '' ? null : VulnerabilityRule::normalize($raw)]);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:2000'],
            'vulnerability_indicator' => ['nullable', 'string', 'max:255', new VulnerabilityRule],
            'consent' => ['required', 'accepted'],

            // Intake deliberately validates ONLY the fields an OFW may set.
            // category_id / category_ids / case_issue_id are staff-review
            // decisions made at approve time, so they are absent from this
            // list: anything a caller sends is never validated into the
            // payload and therefore cannot reach case creation.
            //
            // Profile fields that are prefilled are likewise excluded; the
            // Inertia form must not send them.
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'summary.required' => 'Please provide a summary of your case.',
            'summary.max' => 'Summary must not exceed 2000 characters.',
            'vulnerability_indicator.*.*' => 'Please provide valid vulnerability indicator values.',
            'consent.required' => 'You must consent to data processing to submit this form.',
            'consent.accepted' => 'You must consent to data processing to submit this form.',
        ];
    }
}
