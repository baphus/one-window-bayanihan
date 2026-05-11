<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_type' => ['required', Rule::in(['OFW', 'NEXT_OF_KIN'])],
            'summary' => ['nullable', 'string', 'max:5000'],

            'client.first_name' => ['required', 'string', 'max:255'],
            'client.last_name' => ['required', 'string', 'max:255'],
            'client.middle_name' => ['nullable', 'string', 'max:255'],
            'client.suffix' => ['nullable', 'string', 'max:50'],
            'client.date_of_birth' => ['nullable', 'date'],
            'client.sex' => ['nullable', 'string', 'max:50'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'client.contact' => ['nullable', 'string', 'max:50'],

            'next_of_kin.first_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.last_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.relationship' => ['nullable', 'string', 'max:255'],
            'next_of_kin.contact' => ['nullable', 'string', 'max:50'],

            'consent' => ['nullable', 'boolean'],

            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:255'],

            'employment.employer_name' => ['nullable', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['nullable', 'date'],
            'employment.end_date' => ['nullable', 'date', 'after_or_equal:employment.start_date'],
        ];
    }
}
