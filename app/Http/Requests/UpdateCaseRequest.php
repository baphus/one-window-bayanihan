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
        ];
    }
}
