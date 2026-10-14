<?php

namespace App\Http\Requests;

use App\Rules\VulnerabilityRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Convert empty strings to null for category fields so 'nullable' rules skip them
        if ($this->has('category_id') && $this->category_id === '') {
            $this->merge(['category_id' => null]);
        }

        if ($this->has('category_ids') && $this->input('category_ids') === '') {
            $this->merge(['category_ids' => null]);
        }

        if ($this->has('case_issue_id') && $this->case_issue_id === '') {
            $this->merge(['case_issue_id' => null]);
        }

        // Normalize vulnerability fields — accept comma-separated multi-select, validate each segment
        foreach (['vulnerability_indicator'] as $field) {
            if ($this->has($field)) {
                $raw = $this->input($field);
                if (is_string($raw)) {
                    $this->merge([$field => VulnerabilityRule::normalize($raw)]);
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
            'vulnerability_indicator' => ['required', 'string', 'max:255', new VulnerabilityRule],
            'consent' => ['required', 'accepted'],

            // Intake-specific: category and case_issue are NOT required here.
            // They are prohibited for OFW self-intake (will be empty/null on create).
            'category_id' => ['nullable', 'string', 'uuid', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'category_ids' => ['nullable', 'array', 'min:1'],
            'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'case_issue_id' => ['nullable', 'string', 'exists:case_issues,id'],

            // Profile fields that are prefilled and NOT editable through intake.
            // These are explicitly excluded; Inertia should not send them.
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
            'vulnerability_indicator.required' => 'Please indicate if you have a vulnerability indicator.',
            'vulnerability_indicator.*.*' => 'Please provide valid vulnerability indicator values.',
            'consent.required' => 'You must consent to data processing to submit this form.',
            'consent.accepted' => 'You must consent to data processing to submit this form.',
        ];
    }
}
