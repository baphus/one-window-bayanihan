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
        return true; // Public form, no auth required
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client.first_name' => ['required', 'string', 'max:255'],
            'client.last_name' => ['required', 'string', 'max:255'],
            'client.middle_initial' => ['nullable', 'string', 'max:1'],
            'client.suffix' => ['nullable', 'string', 'max:20'],
            'client.date_of_birth' => ['nullable', 'date'],
            'client.sex' => ['nullable', 'string', 'in:Male,Female,male,female,MALE,FEMALE'],
            'client.contact_number' => ['nullable', 'string', 'max:20'],
            'address.region' => ['nullable', 'string'],
            'address.province' => ['nullable', 'string'],
            'address.city_municipality' => ['nullable', 'string'],
            'address.barangay' => ['nullable', 'string'],
            'address.street' => ['nullable', 'string'],
            'employment.employer_name' => ['nullable', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:100'],
            'employment.start_date' => ['nullable', 'date'],
            'employment.end_date' => ['nullable', 'date'],
            'employment.is_present' => ['nullable', 'boolean'],
            'employment.last_country' => ['nullable', 'string', 'max:100'],
            'employment.last_position' => ['nullable', 'string', 'max:255'],
            'employment.date_of_arrival' => ['nullable', 'date'],
            'vulnerability' => ['nullable', 'array'],
            'vulnerability.*' => ['nullable', 'string', 'in:'.implode(',', VulnerabilityRule::VALUES)],
            'next_of_kin' => ['required', 'array', 'min:1'],
            'next_of_kin.*.first_name' => ['required', 'string', 'max:255'],
            'next_of_kin.*.last_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.middle_initial' => ['nullable', 'string', 'max:1'],
            'next_of_kin.*.relationship' => ['nullable', 'string', 'max:100'],
            'next_of_kin.*.phone_number' => ['nullable', 'string', 'max:50'],
            'next_of_kin.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'next_of_kin.*.region' => ['nullable', 'string'],
            'next_of_kin.*.province' => ['nullable', 'string'],
            'next_of_kin.*.city_municipality' => ['nullable', 'string'],
            'next_of_kin.*.barangay' => ['nullable', 'string'],
            'next_of_kin.*.street' => ['nullable', 'string'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'consent' => ['required', 'accepted'],

        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'client.first_name.required' => 'Please provide your first name.',
            'client.last_name.required' => 'Please provide your last name.',
            'next_of_kin.required' => 'Please provide at least one emergency contact.',
            'next_of_kin.*.first_name.required' => 'Emergency contact name is required.',
            'consent.required' => 'You must consent to data processing to submit this form.',
            'consent.accepted' => 'You must consent to data processing to submit this form.',

        ];
    }
}
