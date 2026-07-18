<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isCaseManager());
    }

    protected function prepareForValidation(): void
    {
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

        // Normalize vulnerability fields — accept comma-separated multi-select, validate each segment
        foreach (['vulnerability_indicator', 'nok_vulnerability_indicator'] as $field) {
            if ($this->has($field)) {
                $raw = $this->input($field);
                if (is_string($raw)) {
                    $segments = array_map('trim', explode(',', $raw));
                    $segments = array_filter($segments, fn ($s) => $s !== '');
                    $valid = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'];
                    $segments = array_values(array_intersect($segments, $valid));
                    $this->merge([$field => empty($segments) ? 'None' : implode(', ', $segments)]);
                }
            }
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
    }

    public function rules(): array
    {
        // Case creation requires all fields — drafts are handled by the case_drafts aggregate
        $r = 'required';

        return [
            'client_type' => [$r, 'string', Rule::in(CaseFile::CLIENT_TYPES)],
            'vulnerability_indicator' => [$r, 'string', 'max:255'],
            'nok_vulnerability_indicator' => [$r === 'required' ? 'required_if:client_type,'.CaseFile::CLIENT_TYPE_NEXT_OF_KIN : 'nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            // Case creation must contain at least one category in either format.
            'category_id' => ['bail', 'required_without:category_ids', 'nullable', 'string', 'uuid', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'category_ids' => ['required_without:category_id', 'nullable', 'array', 'min:1'],
            'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'case_issue_id' => [$r, 'string', 'exists:case_issues,id'],

            'client.first_name' => [$r, 'string', 'max:255'],
            'client.last_name' => [$r, 'string', 'max:255'],
            'client.middle_initial' => ['nullable', 'string', 'max:1'],
            'client.suffix' => ['nullable', 'string', 'max:50'],
            'client.date_of_birth' => [$r, 'date'],
            'client.sex' => [$r, 'string', 'max:50'],
            'client.email' => [$r, 'email', 'max:255'],
            'client.contact_number' => [$r, 'string', 'max:50'],

            'next_of_kin' => ['nullable', 'array'],
            'next_of_kin.*.id' => ['bail', 'nullable', 'uuid'],
            'next_of_kin.*.first_name' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.middle_initial' => ['nullable', 'string', 'max:1'],
            'next_of_kin.*.last_name' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.is_primary' => ['nullable', 'boolean'],
            'next_of_kin.*.relationship' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.phone_number' => ['required_with:next_of_kin', 'string', 'max:50'],
            'next_of_kin.*.email' => ['required_with:next_of_kin', 'email', 'max:255'],
            'next_of_kin.*.full_address' => ['nullable', 'string'],
            'next_of_kin.*.nok_address.region' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.nok_address.province' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.nok_address.city_municipality' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.nok_address.barangay' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.nok_address.street' => ['nullable', 'string'],

            'selected_client_id' => [
                'bail',
                'nullable',
                'string',
                'uuid',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->whereRaw('is_deleted IS FALSE')),
            ],
            'selected_nok_index' => ['nullable', 'integer', 'min:0'],

            'consent' => ['nullable', 'boolean'],

            'address.region' => [$r, 'string', 'max:255'],
            'address.province' => [$r, 'string', 'max:255'],
            'address.city_municipality' => [$r, 'string', 'max:255'],
            'address.barangay' => [$r, 'string', 'max:255'],
            'address.street' => ['nullable', 'string'],

            'employment.employer_name' => [$r, 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => [$r, 'date'],
            'employment.end_date' => [$r === 'required' ? 'required_unless:employment.is_present,true' : 'nullable', 'nullable', 'date', 'after_or_equal:employment.start_date'],
            'employment.is_present' => ['nullable', 'boolean'],
            'employment.last_country' => [$r, 'string', 'max:255'],
            'employment.last_position' => [$r, 'string', 'max:255'],
            'employment.date_of_arrival' => [$r, 'date'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('category_id') && $this->has('category_ids')) {
                $validator->errors()->add('category_ids', 'Use either category_id or category_ids, not both.');
            }

            if (! $this->filled('category_id') && empty($this->input('category_ids'))) {
                $validator->errors()->add('category_ids', 'At least one category is required.');
            }
        });
    }
}
