<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmailChangeVerifyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('new_email') && $this->session()->has('pending_new_email')) {
            $this->merge([
                'new_email' => $this->session()->get('pending_new_email'),
            ]);
        }
    }
}
