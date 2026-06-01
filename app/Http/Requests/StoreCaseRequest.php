<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_draft')) {
            $this->merge(['is_draft' => true]);
        }
    }

    public function rules(): array
    {
        $required = $this->boolean('is_draft') ? 'nullable' : 'required';

        return [
            'is_draft' => ['nullable', 'boolean'],
            'client_type' => ['required', Rule::in(['OFW', 'NEXT_OF_KIN'])],
            'vulnerability_indicator' => ['nullable', 'string', Rule::in(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'])],
            'summary' => ['nullable', 'string', 'max:5000'],

            'client.first_name' => [$required, 'string', 'max:255'],
            'client.last_name' => [$required, 'string', 'max:255'],
            'client.middle_name' => ['nullable', 'string', 'max:255'],
            'client.suffix' => ['nullable', 'string', 'max:50'],
            'client.date_of_birth' => ['nullable', 'date'],
            'client.sex' => ['nullable', 'string', 'max:50'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'client.contact_number' => ['nullable', 'string', 'max:50'],

            'next_of_kin.first_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.middle_initial' => ['nullable', 'string', 'max:10'],
            'next_of_kin.last_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.is_primary' => ['nullable', 'boolean'],
            'next_of_kin.relationship' => ['nullable', 'string', 'max:255'],
            'next_of_kin.phone_number' => ['nullable', 'string', 'max:50'],
            'next_of_kin.email' => ['nullable', 'email', 'max:255'],
            'next_of_kin.full_address' => ['nullable', 'string'],

            'selected_client_id' => ['nullable', 'string', 'exists:clients,id'],

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
        ];
    }
}
