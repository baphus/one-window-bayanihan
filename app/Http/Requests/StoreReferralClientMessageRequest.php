<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralClientMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'AGENCY' && $this->user()->is_active;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
