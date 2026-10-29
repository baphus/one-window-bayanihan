<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category_id') && $this->category_id === '') {
            $this->merge(['category_id' => null]);
        }

        if ($this->has('category_ids') && $this->input('category_ids') === '') {
            $this->merge(['category_ids' => null]);
        }

        if ($this->has('case_issue_id') && $this->case_issue_id === '') {
            $this->merge(['case_issue_id' => null]);
        }
    }

    public function rules(): array
    {
        // Only issue/concern and category are editable on the case detail
        // screen. Status, vulnerability and narrative are deliberately not
        // accepted here: status changes go through the dedicated toggle /
        // archive routes, and narrative + vulnerability are set at intake and
        // approval time. Keys outside this list are ignored, so a caller that
        // still sends them gets a silent no-op rather than a partial write.
        return [
            'case_issue_id' => ['nullable', 'string', 'exists:case_issues,id'],
            // Category fields are optional on partial edits; existing assignments
            // remain unchanged when neither field is submitted.
            'category_id' => ['bail', 'nullable', 'string', 'uuid', Rule::exists('case_categories', 'id')->where('is_active', true)],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('category_id') && $this->has('category_ids')) {
                $validator->errors()->add('category_ids', 'Use either category_id or category_ids, not both.');
            }

            if ($this->has('category_id') && ! $this->filled('category_id')) {
                $validator->errors()->add('category_id', 'At least one category is required.');
            }

            if ($this->has('category_ids') && empty($this->input('category_ids'))) {
                $validator->errors()->add('category_ids', 'At least one category is required.');
            }
        });
    }
}
