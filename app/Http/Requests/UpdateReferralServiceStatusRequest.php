<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferralServiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::AGENCY->value
            && $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(['PROCESSING', 'COMPLETED'])]];
    }
}
