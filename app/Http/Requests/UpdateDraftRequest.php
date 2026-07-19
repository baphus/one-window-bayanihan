<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use App\Rules\VulnerabilityRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isCaseManager());
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_draft')) {
            $this->merge(['is_draft' => true]);
        }

        if ($this->has('category_id') && $this->category_id === '') {
            $this->merge(['category_id' => null]);
        }

        if ($this->has('category_ids') && $this->input('category_ids') === '') {
            $this->merge(['category_ids' => null]);
        }

        if ($this->has('case_issue_id') && $this->case_issue_id === '') {
            $this->merge(['case_issue_id' => null]);
        }

        // Inertia sends JSON — ConvertEmptyStringsToNull middleware doesn't apply.
        // Convert empty email strings to null so 'nullable|email' passes correctly.
        if ($this->has('client.email') && $this->input('client.email') === '') {
            $this->merge(['client' => array_merge($this->input('client', []), ['email' => null])]);
        }

        if ($this->has('next_of_kin') && is_array($this->input('next_of_kin'))) {
            $noks = $this->input('next_of_kin');
            foreach ($noks as $key => $nok) {
                if (isset($nok['email']) && $nok['email'] === '') {
                    $noks[$key]['email'] = null;
                }
            }
            $this->merge(['next_of_kin' => $noks]);
        }

        foreach (['vulnerability_indicator', 'nok_vulnerability_indicator'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => VulnerabilityRule::normalize($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'is_draft' => ['nullable', 'boolean'],
            'client_type' => ['nullable', Rule::in(CaseFile::CLIENT_TYPES)],
            'vulnerability_indicator' => ['nullable', 'string', 'max:255', new VulnerabilityRule],
            'nok_vulnerability_indicator' => ['nullable', 'string', 'max:255', new VulnerabilityRule],
            'summary' => ['nullable', 'string', 'max:5000'],
            // Drafts may be saved without a category; publishing performs the
            // completeness check in CaseService.
            'category_id' => ['bail', 'nullable', 'string', 'uuid', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'case_issue_id' => ['nullable', 'string', 'exists:case_issues,id'],

            'client.first_name' => ['nullable', 'string', 'max:255'],
            'client.last_name' => ['nullable', 'string', 'max:255'],
            'client.middle_initial' => ['nullable', 'string', 'max:1'],
            'client.suffix' => ['nullable', 'string', 'max:50'],
            'client.date_of_birth' => ['nullable', 'date'],
            'client.sex' => ['nullable', 'string', 'max:50'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'client.contact_number' => ['nullable', 'string', 'max:50'],

            'next_of_kin' => ['nullable', 'array'],
            'next_of_kin.*.first_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.middle_initial' => ['nullable', 'string', 'max:1'],
            'next_of_kin.*.last_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.is_primary' => ['nullable', 'boolean'],
            'next_of_kin.*.relationship' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.phone_number' => ['nullable', 'string', 'max:50'],
            'next_of_kin.*.email' => ['nullable', 'email', 'max:255'],
            'next_of_kin.*.full_address' => ['nullable', 'string'],
            'next_of_kin.*.nok_address.region' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.nok_address.province' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.nok_address.city_municipality' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.nok_address.barangay' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.nok_address.street' => ['nullable', 'string'],

            'selected_client_id' => ['nullable', 'string', 'exists:clients,id'],
            'selected_nok_index' => ['nullable', 'integer', 'min:0'],

            'consent' => ['nullable', 'boolean'],

            'address.region' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.city_municipality' => ['nullable', 'string', 'max:255'],
            'address.barangay' => ['nullable', 'string', 'max:255'],
            'address.street' => ['nullable', 'string'],

            'employment.employer_name' => ['nullable', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['nullable', 'date'],
            'employment.end_date' => ['nullable', 'date', 'after_or_equal:employment.start_date'],
            'employment.last_country' => ['nullable', 'string', 'max:255'],
            'employment.last_position' => ['nullable', 'string', 'max:255'],
            'employment.date_of_arrival' => ['nullable', 'date'],
            'employment.is_present' => ['nullable', 'boolean'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('category_id') && $this->has('category_ids')) {
                $validator->errors()->add('category_ids', 'Use either category_id or category_ids, not both.');
            }
        });
    }
}
