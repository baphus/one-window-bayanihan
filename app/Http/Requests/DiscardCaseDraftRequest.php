<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscardCaseDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discard', $this->route('draft')) ?? false;
    }

    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
