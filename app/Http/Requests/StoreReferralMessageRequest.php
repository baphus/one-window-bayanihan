<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Thread access is enforced in ReferralMessageService.
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
