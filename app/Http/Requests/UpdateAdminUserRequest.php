<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$this->route('user', $this->route('id'))],
            'role' => ['required', 'in:'.implode(',', UserRole::staffValues())],
            'mfa_requirement_override' => ['sometimes', 'in:inherit,required,optional'],
            'mfa_change_reason' => ['nullable', 'string', 'max:500'],
            'otp' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
            'agcy_id' => ['nullable', 'exists:agencies,id'],
            'contact_number' => ['nullable', 'string'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'json'],
            'is_active' => ['boolean'],
        ];
    }
}
