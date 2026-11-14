<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $this->user()?->isCaseManager() && $case?->assigned_case_manager_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)->where('is_deleted', false)->whereNull('deleted_at')],
        ];
    }
}
