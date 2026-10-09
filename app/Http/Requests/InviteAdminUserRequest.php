<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:ADMIN,AGENCY,CASE_MANAGER'],
            'agcy_id' => ['nullable', 'exists:agencies,id'],
        ];
    }
}
