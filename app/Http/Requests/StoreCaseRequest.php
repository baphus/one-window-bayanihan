<?php

namespace App\Http\Requests;

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
        if ($this->boolean('is_draft')) {
            $this->merge(['is_draft' => true]);
        }

        if ($this->has('category_id') && $this->category_id === '') {
            $this->merge(['category_id' => null]);
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
    }

    public function rules(): array
    {
        return [
            'is_draft' => ['nullable', 'boolean'],
            'client_type' => ['required', Rule::in(['OFW', 'NEXT_OF_KIN'])],
            'vulnerability_indicator' => ['required', 'string', Rule::in(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'])],
            'nok_vulnerability_indicator' => ['required_if:client_type,NEXT_OF_KIN', 'string', Rule::in(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'])],
            'summary' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'string', 'exists:case_categories,id'],
            'case_issue_id' => ['required', 'string', 'exists:case_issues,id'],

            'client.first_name' => ['required', 'string', 'max:255'],
            'client.last_name' => ['required', 'string', 'max:255'],
            'client.middle_initial' => ['nullable', 'string', 'max:1'],
            'client.suffix' => ['nullable', 'string', 'max:50'],
            'client.date_of_birth' => ['required', 'date'],
            'client.sex' => ['required', 'string', 'max:50'],
            'client.email' => ['required', 'email', 'max:255'],
            'client.contact_number' => ['required', 'string', 'max:50'],

            'next_of_kin' => ['nullable', 'array'],
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

            'selected_client_id' => ['nullable', 'string', 'exists:clients,id'],
            'selected_nok_index' => ['nullable', 'integer', 'min:0'],

            'consent' => ['nullable', 'boolean'],

            'address.region' => ['required', 'string', 'max:255'],
            'address.province' => ['required', 'string', 'max:255'],
            'address.city_municipality' => ['required', 'string', 'max:255'],
            'address.barangay' => ['required', 'string', 'max:255'],
            'address.street' => ['nullable', 'string'],

            'employment.employer_name' => ['required', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['nullable', 'date'],
            'employment.end_date' => ['nullable', 'date', 'after_or_equal:employment.start_date'],
            'employment.last_country' => ['required', 'string', 'max:255'],
            'employment.last_position' => ['required', 'string', 'max:255'],
            'employment.date_of_arrival' => ['required', 'date'],
        ];
    }
}
