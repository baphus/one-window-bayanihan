<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Http\Requests\Concerns\ValidatesUserIdentity;
use App\Models\User;

class UpdateAdminUserRequest extends AvatarUploadRequest
{
    use ValidatesUserIdentity;

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $target = User::findOrFail($this->route('user', $this->route('id')));
        $roles = $target->isOfw() ? [UserRole::OFW->value] : UserRole::staffValues();

        return $this->identityRules(requireDemographics: $target->isOfw(), partial: ! $target->isOfw()) + [
            'profile_picture' => parent::rules()['profile_picture'],
            'remove_profile_picture' => ['sometimes', 'boolean'],
            'email' => ['required', 'email', 'unique:users,email,'.$this->route('user', $this->route('id'))],
            'role' => ['required', 'in:'.implode(',', $roles)],
            'mfa_requirement_override' => ['sometimes', 'in:inherit,required,optional'],
            'mfa_change_reason' => ['nullable', 'string', 'max:500'],
            'otp' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
            'agcy_id' => ['nullable', 'exists:agencies,id'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'json'],
            'is_active' => ['boolean'],
        ];
    }
}
