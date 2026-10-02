<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'visibility' => ['sometimes', 'in:INTERNAL,AGY_ONLY'],
        ];
    }
}
