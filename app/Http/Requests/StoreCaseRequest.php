<?php

namespace App\Http\Requests;

use App\Rules\EmploymentStartAtWorkingAge;
use App\Rules\ServedRegion;
use App\Rules\VulnerabilityRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isCaseManager() || $user->isOfw());
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
                    $this->merge([$field => VulnerabilityRule::normalize($raw)]);
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

    private function convertEmptyStringsToNull(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->convertEmptyStringsToNull($value);
            } else {
                $result[$key] = $value === '' ? null : $value;
            }
        }

        return $result;
    }

    public function rules(): array
    {
        // Clients must be 15–100 years old.
        $dobMax = now()->subYears(15)->toDateString();
        $dobMin = now()->subYears(100)->toDateString();

        return [
            'vulnerability_indicator' => ['required', 'string', 'max:255', new VulnerabilityRule],
            'nok_vulnerability_indicator' => ['nullable', 'string', 'max:255', new VulnerabilityRule],
            'summary' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['bail', 'required_without:category_ids', 'nullable', 'string', 'uuid', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'category_ids' => ['required_without:category_id', 'nullable', 'array', 'min:1'],
            'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'case_issue_id' => ['required', 'string', 'exists:case_issues,id'],

            'client.first_name' => ['required', 'string', 'max:255'],
            'client.last_name' => ['required', 'string', 'max:255'],
            'client.middle_name' => ['nullable', 'string', 'max:255'],
            'client.suffix' => ['nullable', 'string', 'max:50'],
            'client.date_of_birth' => ['required', 'date', 'before_or_equal:'.$dobMax, 'after_or_equal:'.$dobMin],
            'client.sex' => ['required', 'string', 'max:50'],
            'client.email' => ['required', 'email', 'max:255'],
            'client.contact_number' => ['required', 'string', 'max:50'],

            'next_of_kin' => ['nullable', 'array'],
            'next_of_kin.*.first_name' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.middle_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.last_name' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.is_primary' => ['nullable', 'boolean'],
            'next_of_kin.*.relationship' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.phone_number' => ['required_with:next_of_kin', 'string', 'max:50'],
            'next_of_kin.*.email' => ['required_with:next_of_kin', 'email', 'max:255'],
            'next_of_kin.*.full_address' => ['nullable', 'string'],
            'next_of_kin.*.nok_address.region' => ['required_with:next_of_kin', 'string', 'max:255', new ServedRegion],
            'next_of_kin.*.nok_address.province' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.nok_address.city_municipality' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.nok_address.barangay' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.nok_address.street' => ['nullable', 'string'],

            'selected_client_id' => ['nullable', 'string', 'exists:clients,id'],
            'selected_nok_index' => ['nullable', 'integer', 'min:0'],

            'new_ofw' => ['nullable', 'array'],
            'new_ofw.first_name' => ['required_with:new_ofw', 'string', 'max:255'],
            'new_ofw.last_name' => ['required_with:new_ofw', 'string', 'max:255'],
            'new_ofw.middle_name' => ['nullable', 'string', 'max:255'],
            'new_ofw.suffix' => ['nullable', 'string', 'max:50'],
            'new_ofw.date_of_birth' => ['nullable', 'date'],
            'new_ofw.sex' => ['nullable', 'string', 'max:50'],
            'new_ofw.email' => ['nullable', 'email', 'max:255'],
            'new_ofw.contact_number' => ['nullable', 'string', 'max:50'],

            'confirm_duplicate_client' => ['nullable', 'boolean'],

            'consent' => ['nullable', 'boolean'],

            'address.region' => ['required', 'string', 'max:255', new ServedRegion],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.city_municipality' => ['required', 'string', 'max:255'],
            'address.barangay' => ['required', 'string', 'max:255'],
            'address.street' => ['nullable', 'string'],

            'employment.employer_name' => ['required', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['required', 'date', 'after_or_equal:client.date_of_birth', 'before_or_equal:today', new EmploymentStartAtWorkingAge($this->input('client.date_of_birth'))],
            'employment.end_date' => ['required_unless:employment.is_present,true', 'nullable', 'date', 'after_or_equal:employment.start_date', 'before_or_equal:today'],
            'employment.is_present' => ['nullable', 'boolean'],
            'employment.last_country' => ['required', 'string', 'max:255'],
            'employment.last_position' => ['required', 'string', 'max:255'],
            'employment.date_of_arrival' => ['required', 'date', 'after_or_equal:client.date_of_birth'],
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
