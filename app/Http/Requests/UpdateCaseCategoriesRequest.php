<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCaseManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
        ];
    }
}
