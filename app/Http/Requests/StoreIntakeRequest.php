<?php

namespace App\Http\Requests;

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
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'uuid'],
            'case_issue_id' => ['nullable', 'uuid'],
            'vulnerability_indicator' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'min:20'],
            'consent' => ['required', 'accepted'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
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
            'category_ids.required' => 'Please select at least one type of help you need.',
            'summary.required' => 'Please describe your situation.',
            'summary.min' => 'Please provide more details about your situation (at least 20 characters).',
            'consent.required' => 'You must consent to data processing to submit this form.',
            'consent.accepted' => 'You must consent to data processing to submit this form.',
            'password.required' => 'Please create a password for your account.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password_confirmation.same' => 'Password confirmation does not match.',
        ];
    }
}
