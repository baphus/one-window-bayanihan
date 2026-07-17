<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
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

        foreach (['vulnerability_indicator', 'nok_vulnerability_indicator'] as $field) {
            if ($this->has($field)) {
                $raw = $this->input($field);
                if (is_string($raw)) {
                    $segments = array_map('trim', explode(',', $raw));
                    $segments = array_filter($segments, fn ($s) => $s !== '');
                    $valid = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'];
                    $segments = array_values(array_intersect($segments, $valid));
                    $this->merge([$field => empty($segments) ? 'None' : implode(', ', $segments)]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['OPEN', 'CLOSED', 'ARCHIVED'])],
            'client_type' => ['required', Rule::in(CaseFile::CLIENT_TYPES)],
            'vulnerability_indicator' => ['nullable', 'string', 'max:255'],
            'nok_vulnerability_indicator' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
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
