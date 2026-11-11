<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user()->isOfw()) {
            return array_fill_keys([
                'name', 'email', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'sex', 'suffix',
                'address', 'employment', 'next_of_kin', 'type', 'client_type', 'client_id', 'ofw_client_id',
                'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'position', 'department',
                'office_location', 'bio', 'emergency_contact', 'notifications_config', 'timezone', 'avatar',
            ], ['prohibited']) + [
                'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            ];
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::in([$this->user()->email]),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->user()->role !== UserRole::OFW->value) {
            $rules += [
                'position' => ['nullable', 'string', 'max:255'],
                'department' => ['nullable', 'string', 'max:255'],
                'office_location' => ['nullable', 'string', 'max:500'],
                'bio' => ['nullable', 'string', 'max:2000'],
                'notifications_config' => ['nullable', 'array'],
                'emergency_contact' => ['nullable', 'array'],
                'emergency_contact.name' => ['nullable', 'string', 'max:255'],
                'emergency_contact.relation' => ['nullable', 'string', 'max:255'],
                'emergency_contact.phone' => ['nullable', 'string', 'max:50'],
            ];
        }

        return $rules;
    }
}
