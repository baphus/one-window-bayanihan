<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailChangeSendOtpRequest extends FormRequest
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
        $hasPendingEmailChange = $this->session()->has('pending_new_email');

        return [
            'password' => $hasPendingEmailChange
                ? ['nullable', 'string']
                : ['required', 'string', 'current_password'],
            'new_email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()->id),
            ],
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
