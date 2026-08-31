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
     * Next of kin is optional. Drop fully-empty contact entries so restored
     * sessions and older clients with placeholder rows do not trip the
     * per-entry required_with rule.
     */
    protected function prepareForValidation(): void
    {
        $noks = $this->input('next_of_kin');

        if (! is_array($noks)) {
            return;
        }

        $filled = array_values(array_filter($noks, function ($nok) {
            if (! is_array($nok)) {
                return false;
            }

            foreach ($nok as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return true;
                }
            }

            return false;
        }));

        if (empty($filled)) {
            $this->merge(['next_of_kin' => []]);

            return;
        }

        $this->merge(['next_of_kin' => $filled]);
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
            'client.middle_name' => ['nullable', 'string', 'max:255'],
            'client.suffix' => ['nullable', 'string', 'max:20'],
            'client.date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'client.sex' => ['nullable', 'string', 'in:Male,Female,male,female,MALE,FEMALE'],
            'client.contact_number' => ['required', 'string', 'max:20'],
            'address.region' => ['required', 'string'],
            'address.province' => ['nullable', 'string'],
            'address.city_municipality' => ['required', 'string'],
            'address.barangay' => ['required', 'string'],
            'address.street' => ['nullable', 'string'],
            'employment.employer_name' => ['nullable', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:100'],
            'employment.start_date' => ['nullable', 'date', 'after_or_equal:client.date_of_birth'],
            'employment.end_date' => ['nullable', 'date', 'after_or_equal:employment.start_date'],
            'employment.is_present' => ['nullable', 'boolean'],
            'employment.last_country' => ['nullable', 'string', 'max:100'],
            'employment.last_position' => ['nullable', 'string', 'max:255'],
            'employment.date_of_arrival' => ['nullable', 'date', 'after_or_equal:client.date_of_birth'],
            'vulnerability' => ['nullable', 'array'],
            'vulnerability.*' => ['nullable', 'string', 'in:'.implode(',', VulnerabilityRule::VALUES)],
            'next_of_kin' => ['sometimes', 'array'],
            'next_of_kin.*.first_name' => ['required_with:next_of_kin', 'string', 'max:255'],
            'next_of_kin.*.last_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.middle_name' => ['nullable', 'string', 'max:255'],
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
            'client.date_of_birth.required' => 'Please provide your date of birth.',
            'client.contact_number.required' => 'Please provide your contact number.',
            'address.region.required' => 'Please select your region.',
            'address.city_municipality.required' => 'Please select your city/municipality.',
            'address.barangay.required' => 'Please select your barangay.',
            'next_of_kin.*.first_name.required_with' => 'Emergency contact name is required when a contact is provided.',
            'consent.required' => 'You must consent to data processing to submit this form.',
            'consent.accepted' => 'You must consent to data processing to submit this form.',

        ];
    }
}
