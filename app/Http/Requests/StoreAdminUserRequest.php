<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Http\Requests\Concerns\ValidatesUserIdentity;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends AvatarUploadRequest
{
    use ValidatesUserIdentity;

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return $this->identityRules(requireDemographics: $this->input('role') === UserRole::OFW->value) + [
            'profile_picture' => parent::rules()['profile_picture'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'agcy_id' => ['nullable', 'exists:agencies,id'],
        ];
    }
}
